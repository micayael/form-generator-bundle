<?php

namespace Micayael\Bundle\FormGeneratorBundle\Service;

use Micayael\Bundle\FormGeneratorBundle\Exception\InvalidConfigurationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Cascade;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\DisableAutoMapping;
use Symfony\Component\Validator\Constraints\EnableAutoMapping;
use Symfony\Component\Validator\Constraints\Existence;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Exception\ExceptionInterface as ValidatorExceptionInterface;

use function Symfony\Component\String\u;

/**
 * Crea constraints a partir de su nombre en snake_case ("not_blank" => NotBlank)
 * o de su FQCN, usando argumentos con nombre para ser compatible con Symfony 5.4 a 8.
 */
final class ConstraintFactory
{
    private const CONSTRAINTS_NAMESPACE = 'Symfony\\Component\\Validator\\Constraints\\';

    private const ALIASES = [
        'datetime' => DateTime::class,
    ];

    // Opciones que Symfony invoca como callables aunque no estén tipadas como tales
    public const CALLABLE_OPTIONS = ['normalizer', 'callback'];

    // Constraints bloqueadas en modo untrusted: evalúan expresiones o invocan código
    public const UNTRUSTED_BLOCKED_CONSTRAINTS = [
        'Symfony\\Component\\Validator\\Constraints\\Callback',
        'Symfony\\Component\\Validator\\Constraints\\Expression',
        'Symfony\\Component\\Validator\\Constraints\\When',
    ];

    // Meta constraints que no validan un campo (fallan o no hacen nada en un campo de formulario).
    // Required y Optional (Existence) solo tienen sentido como campo de una Collection.
    public const META_CONSTRAINTS = [
        Existence::class,
        Traverse::class,
        Cascade::class,
        EnableAutoMapping::class,
        DisableAutoMapping::class,
    ];

    // Opciones de constraints bloqueadas en modo untrusted: leen otros datos del objeto validado
    public const UNTRUSTED_BLOCKED_CONSTRAINT_OPTIONS = ['propertyPath', 'minPropertyPath', 'maxPropertyPath'];

    /** @var array<class-string<Constraint>, array<string, \ReflectionParameter>> */
    private array $constructorParameters = [];

    /** @var list<class-string<Constraint>> */
    private readonly array $allowedConstraints;

    /** @var array<string, class-string<Constraint>>|null */
    private ?array $availableConstraints = null;

    /**
     * @param bool         $untrusted          only the Symfony constraints (except the blocked ones) and $allowedConstraints can be used
     * @param list<string> $allowedConstraints names or FQCN of constraints allowed anyway in untrusted mode
     */
    public function __construct(
        private readonly bool $untrusted = false,
        array $allowedConstraints = [],
    ) {
        $this->allowedConstraints = array_map(fn (string $name): string => $this->resolveClass($name), $allowedConstraints);
    }

    public function isUntrusted(): bool
    {
        return $this->untrusted;
    }

    /**
     * Crea una lista de constraints a partir de un mapa "nombre: opciones",
     * una lista de nombres u objetos Constraint. Con "nombre: false" se omite la constraint.
     *
     * @return list<Constraint>
     */
    public function createMany(mixed $definitions): array
    {
        return $this->createList($definitions, false);
    }

    public function create(string $name, mixed $options = []): Constraint
    {
        return $this->createConstraint($name, $options, false);
    }

    /**
     * @param bool $collectionField la lista es la definición de un campo de una Collection (admite Required/Optional)
     *
     * @return list<Constraint>
     */
    private function createList(mixed $definitions, bool $collectionField): array
    {
        if ($definitions instanceof Constraint) {
            return [$definitions];
        }

        $definitions ??= [];

        if (!\is_array($definitions)) {
            throw new InvalidConfigurationException(sprintf('Constraints must be defined as an array, "%s" given.', get_debug_type($definitions)));
        }

        $constraints = [];

        foreach ($definitions as $name => $options) {
            if ($options instanceof Constraint) {
                $constraints[] = $options;

                continue;
            }

            if (\is_int($name)) {
                if (!\is_string($options)) {
                    throw new InvalidConfigurationException(sprintf('Invalid constraint definition: expected a constraint name, "%s" given.', get_debug_type($options)));
                }

                $name = $options;
                $options = [];
            }

            if (false === $options) {
                continue;
            }

            $constraints[] = $this->createConstraint($name, $options, $collectionField);
        }

        return $constraints;
    }

    private function createConstraint(string $name, mixed $options, bool $collectionField): Constraint
    {
        $class = $this->resolveClass($name);

        if (!$this->isAllowed($class) || ($this->untrusted && $this->isMeta($class) && !($collectionField && is_a($class, Existence::class, true)))) {
            throw new InvalidConfigurationException(sprintf('The constraint "%s" is not allowed in untrusted mode.', $name));
        }

        if (true === $options || null === $options) {
            $options = [];
        }

        if (!\is_array($options)) {
            throw new InvalidConfigurationException(sprintf('The options of the constraint "%s" must be an array, "%s" given.', $name, get_debug_type($options)));
        }

        $blockedOptions = $this->untrusted ? array_intersect(array_keys($options), self::UNTRUSTED_BLOCKED_CONSTRAINT_OPTIONS) : [];

        if ($blockedOptions) {
            throw new InvalidConfigurationException(sprintf('The option(s) "%s" of the constraint "%s" are not allowed in untrusted mode.', implode('", "', $blockedOptions), $name));
        }

        $parameters = $this->getConstructorParameters($class);
        $namedParameters = array_diff_key($parameters, ['options' => true]);
        $unknownOptions = array_diff(array_keys($options), array_keys($namedParameters));

        foreach ($options as $option => $value) {
            $options[$option] = $this->normalizeOption($name, $class, (string) $option, $namedParameters[$option] ?? null, $value);
        }

        try {
            if (!$unknownOptions) {
                return $this->instantiate($class, $options);
            }

            // Constraints que solo aceptan el array de opciones legacy (Required, Optional, ... en Symfony < 7)
            if (isset($parameters['options'])) {
                return $this->instantiate($class, ['options' => $options]);
            }
        } catch (\TypeError|ValidatorExceptionInterface $e) {
            if (!$unknownOptions) {
                throw new InvalidConfigurationException(sprintf('Invalid options for the constraint "%s": %s', $name, $e->getMessage()), 0, $e);
            }
        }

        throw new InvalidConfigurationException(sprintf('Unknown option(s) "%s" for the constraint "%s". Available options: "%s".', implode('", "', $unknownOptions), $name, implode('", "', array_keys($namedParameters))), 0, $e ?? null);
    }

    /**
     * @param class-string<Constraint> $class
     * @param array<string, mixed>     $arguments
     */
    private function instantiate(string $class, array $arguments): Constraint
    {
        return new $class(...$arguments);
    }

    /**
     * @return class-string<Constraint>
     */
    public function resolveClass(string $name): string
    {
        $class = self::ALIASES[$name] ?? (str_contains($name, '\\')
            ? ltrim($name, '\\')
            : self::CONSTRAINTS_NAMESPACE.str_replace('_', '', ucwords($name, '_')));

        if (!class_exists($class) || !is_subclass_of($class, Constraint::class) || !(new \ReflectionClass($class))->isInstantiable()) {
            throw new InvalidConfigurationException(sprintf('Unknown constraint "%s".', $name));
        }

        return $class;
    }

    /**
     * Indica si la constraint se puede usar: en modo confiable todas, en untrusted solo
     * las de Symfony (menos las bloqueadas) y las de $allowedConstraints.
     *
     * @param class-string<Constraint> $class
     */
    public function isAllowed(string $class): bool
    {
        if (!$this->untrusted) {
            return true;
        }

        if (\in_array($class, $this->allowedConstraints, true)) {
            return true;
        }

        return str_starts_with($class, self::CONSTRAINTS_NAMESPACE) && !\in_array($class, self::UNTRUSTED_BLOCKED_CONSTRAINTS, true);
    }

    /**
     * @param class-string<Constraint> $class
     */
    private function isMeta(string $class): bool
    {
        foreach (self::META_CONSTRAINTS as $meta) {
            if (is_a($class, $meta, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Constraints disponibles (las de Symfony más $allowedConstraints) que pasan isAllowed(),
     * sin las meta constraints. Las de Symfony se indexan por su alias en snake_case y las demás
     * por su FQCN (así cualquier clave se puede pasar de vuelta a create()). Ordenadas por clave.
     *
     * @return array<string, class-string<Constraint>>
     */
    public function getAvailableConstraints(): array
    {
        if (null !== $this->availableConstraints) {
            return $this->availableConstraints;
        }

        $classes = $this->allowedConstraints;
        $directory = \dirname((string) (new \ReflectionClass(NotBlank::class))->getFileName());

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            // Los traits no son constraints y cargarlos puede emitir deprecaciones (p. ej. NumberConstraintTrait en 5.4)
            if (str_ends_with($file, 'Trait.php')) {
                continue;
            }

            $class = self::CONSTRAINTS_NAMESPACE.basename($file, '.php');

            try {
                if (class_exists($class) && is_subclass_of($class, Constraint::class) && (new \ReflectionClass($class))->isInstantiable()) {
                    $classes[] = $class;
                }
            } catch (\Error) {
                // Clases auxiliares del directorio con dependencias opcionales ausentes (p. ej. ExpressionLanguageProvider)
            }
        }

        $constraints = [];

        foreach (array_unique($classes) as $class) {
            if (!$this->isAllowed($class) || $this->isMeta($class)) {
                continue;
            }

            $key = str_starts_with($class, self::CONSTRAINTS_NAMESPACE)
                ? u((new \ReflectionClass($class))->getShortName())->snake()->toString()
                : $class;

            $constraints[$key] = $class;
        }

        ksort($constraints);

        return $this->availableConstraints = $constraints;
    }

    /**
     * @param class-string<Constraint> $class
     */
    private function normalizeOption(string $name, string $class, string $option, ?\ReflectionParameter $parameter, mixed $value): mixed
    {
        // Por seguridad, los callables solo se aceptan como Closure (definidos en PHP, no en YAML/JSON)
        if (null !== $value && !$value instanceof \Closure && $this->acceptsCallable($option, $parameter)) {
            throw new InvalidConfigurationException(sprintf('The option "%s" of the constraint "%s" only accepts a \Closure.', $option, $name));
        }

        // Constraints anidadas: All, AtLeastOneOf, Sequentially, When, Required, Optional, ...
        if ('constraints' === $option) {
            return $this->createList($value, false);
        }

        if ('fields' === $option && is_a($class, Collection::class, true) && \is_array($value)) {
            return array_map(function (mixed $fieldConstraints): Constraint|array {
                $constraints = $this->createList($fieldConstraints, true);

                // Required/Optional no pueden quedar envueltos en otro Required
                return 1 === \count($constraints) && $constraints[0] instanceof Existence ? $constraints[0] : $constraints;
            }, $value);
        }

        return $value;
    }

    private function acceptsCallable(string $option, ?\ReflectionParameter $parameter): bool
    {
        if (\in_array($option, self::CALLABLE_OPTIONS, true)) {
            return true;
        }

        $type = (string) $parameter?->getType();

        return str_contains($type, 'callable') || str_contains($type, 'Closure');
    }

    /**
     * @param class-string<Constraint> $class
     *
     * @return array<string, \ReflectionParameter>
     */
    private function getConstructorParameters(string $class): array
    {
        if (!isset($this->constructorParameters[$class])) {
            $parameters = [];

            foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
                $parameters[$parameter->getName()] = $parameter;
            }

            $this->constructorParameters[$class] = $parameters;
        }

        return $this->constructorParameters[$class];
    }
}
