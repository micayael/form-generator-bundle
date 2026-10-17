<?php

namespace Micayael\Bundle\FormGeneratorBundle\Service;

use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\Debug\OptionsResolverIntrospector;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsResolverExceptionInterface;

/**
 * Describe, en un array serializable a JSON, los tipos de formulario, sus opciones y las constraints
 * que un FormGenerator acepta. Lo consumen los editores visuales de formularios.
 */
final class SchemaProvider
{
    private const CALLABLE_TYPES = ['callable', 'Closure', '\\Closure'];

    // Opciones de constraints que no se configuran desde la config
    private const EXCLUDED_CONSTRAINT_OPTIONS = ['groups', 'payload', 'options'];

    public function __construct(private readonly FormRegistryInterface $formRegistry)
    {
    }

    /**
     * @return array{
     *     untrusted: bool,
     *     types: array<string, array{class: string, options: array<string, array{types: list<string>, default?: scalar|null}>}>,
     *     constraints: array<string, array{class: string, options: array<string, array{types: list<string>}>}>
     * }
     */
    public function getSchema(FormGenerator $generator): array
    {
        $classes = [];

        foreach ($generator->getTypes() as $alias => $type) {
            $classes[$alias] = $type['class'];
        }

        foreach ($generator->getAllowedClasses() as $class) {
            $classes[$class] = $class;
        }

        $types = [];

        foreach ($classes as $alias => $class) {
            $types[$alias] = ['class' => $class, 'options' => $this->getTypeOptions($generator, $class)];
        }

        $constraintFactory = $generator->getConstraintFactory();
        $constraints = [];

        foreach ($constraintFactory->getAvailableConstraints() as $alias => $class) {
            $constraints[$alias] = ['class' => $class, 'options' => $this->getConstraintOptions($constraintFactory, $class)];
        }

        return [
            'untrusted' => $generator->isUntrusted(),
            'types' => $types,
            'constraints' => $constraints,
        ];
    }

    /**
     * @return array<string, array{types: list<string>, default?: scalar|null}>
     */
    private function getTypeOptions(FormGenerator $generator, string $class): array
    {
        $resolver = $this->formRegistry->getType($class)->getOptionsResolver();
        $introspector = new OptionsResolverIntrospector($resolver);

        $blocked = $generator->isUntrusted()
            ? array_diff(FormGenerator::UNTRUSTED_BLOCKED_OPTIONS, $generator->getAllowedOptions())
            : [];

        $names = $resolver->getDefinedOptions();
        sort($names);

        $options = [];

        foreach ($names as $name) {
            // "constraints" tiene su propia sección
            if ('constraints' === $name || \in_array($name, $blocked, true)) {
                continue;
            }

            try {
                $allowedTypes = $introspector->getAllowedTypes($name);
            } catch (OptionsResolverExceptionInterface) {
                // Opción sin tipos declarados
                $allowedTypes = [];
            }

            if ($this->onlyAcceptsCallables($allowedTypes)) {
                continue;
            }

            $option = ['types' => $allowedTypes];

            try {
                $default = $introspector->getDefault($name);

                if (null === $default || \is_scalar($default)) {
                    $option['default'] = $default;
                }
            } catch (OptionsResolverExceptionInterface) {
                // Sin valor por defecto (o definido de forma lazy)
            }

            // Symfony no declara los tipos de varias opciones (p. ej. "required"): se infieren del valor por defecto
            if (!$option['types']) {
                $option['types'] = null !== ($option['default'] ?? null) ? [get_debug_type($option['default'])] : ['mixed'];
            }

            $options[$name] = $option;
        }

        return $options;
    }

    /**
     * Mismo criterio que FormGenerator::assertNoCallableStrings(): una opción con tipo callable solo
     * acepta datos de la config si también admite otro tipo (p. ej. choice_label acepta string).
     *
     * @param list<string> $allowedTypes
     */
    private function onlyAcceptsCallables(array $allowedTypes): bool
    {
        if (!array_intersect($allowedTypes, self::CALLABLE_TYPES)) {
            return false;
        }

        return !array_diff($allowedTypes, [...self::CALLABLE_TYPES, 'null']);
    }

    /**
     * @param class-string<\Symfony\Component\Validator\Constraint> $class
     *
     * @return array<string, array{types: list<string>}>
     */
    private function getConstraintOptions(ConstraintFactory $constraintFactory, string $class): array
    {
        $excluded = [...self::EXCLUDED_CONSTRAINT_OPTIONS, ...ConstraintFactory::CALLABLE_OPTIONS];

        if ($constraintFactory->isUntrusted()) {
            $excluded = [...$excluded, ...ConstraintFactory::UNTRUSTED_BLOCKED_CONSTRAINT_OPTIONS];
        }

        $options = [];

        foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            $types = $this->getParameterTypes($type);

            if (\in_array($parameter->getName(), $excluded, true) || array_intersect($types, self::CALLABLE_TYPES)) {
                continue;
            }

            $options[$parameter->getName()] = ['types' => $types ?: ['mixed']];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function getParameterTypes(?\ReflectionType $type): array
    {
        if (null === $type) {
            return [];
        }

        $types = $type instanceof \ReflectionNamedType ? [$type] : ($type instanceof \ReflectionUnionType ? $type->getTypes() : []);
        $names = [];

        foreach ($types as $namedType) {
            if ($namedType instanceof \ReflectionNamedType) {
                $names[] = $namedType->getName();
            }
        }

        if ($type->allowsNull() && !\in_array('null', $names, true) && !\in_array('mixed', $names, true)) {
            $names[] = 'null';
        }

        return $names;
    }
}
