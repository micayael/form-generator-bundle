<?php

namespace Micayael\Bundle\FormGeneratorBundle\Service;

use Micayael\Bundle\FormGeneratorBundle\Exception\InvalidConfigurationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\Existence;
use Symfony\Component\Validator\Exception\ExceptionInterface as ValidatorExceptionInterface;

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
    private const CALLABLE_OPTIONS = ['normalizer', 'callback'];

    // Constraints bloqueadas en modo untrusted: evalúan expresiones o invocan código
    public const UNTRUSTED_BLOCKED_CONSTRAINTS = [
        'Symfony\\Component\\Validator\\Constraints\\Callback',
        'Symfony\\Component\\Validator\\Constraints\\Expression',
        'Symfony\\Component\\Validator\\Constraints\\When',
    ];

    /** @var array<class-string<Constraint>, array<string, \ReflectionParameter>> */
    private array $constructorParameters = [];

    /** @var list<class-string<Constraint>> */
    private readonly array $allowedConstraints;

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

            $constraints[] = $this->create($name, $options);
        }

        return $constraints;
    }

    public function create(string $name, mixed $options = []): Constraint
    {
        $class = $this->resolveClass($name);

        if ($this->untrusted && !$this->isAllowedInUntrustedMode($class)) {
            throw new InvalidConfigurationException(sprintf('The constraint "%s" is not allowed in untrusted mode.', $name));
        }

        if (true === $options || null === $options) {
            $options = [];
        }

        if (!\is_array($options)) {
            throw new InvalidConfigurationException(sprintf('The options of the constraint "%s" must be an array, "%s" given.', $name, get_debug_type($options)));
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
     * @param class-string<Constraint> $class
     */
    private function isAllowedInUntrustedMode(string $class): bool
    {
        if (\in_array($class, $this->allowedConstraints, true)) {
            return true;
        }

        return str_starts_with($class, self::CONSTRAINTS_NAMESPACE) && !\in_array($class, self::UNTRUSTED_BLOCKED_CONSTRAINTS, true);
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
            return $this->createMany($value);
        }

        if ('fields' === $option && is_a($class, Collection::class, true) && \is_array($value)) {
            return array_map(function (mixed $fieldConstraints): Constraint|array {
                $constraints = $this->createMany($fieldConstraints);

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
