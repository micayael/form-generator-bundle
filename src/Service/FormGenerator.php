<?php

namespace Micayael\Bundle\FormGeneratorBundle\Service;

use Micayael\Bundle\FormGeneratorBundle\Config\SupportedTypes;
use Micayael\Bundle\FormGeneratorBundle\Exception\InvalidConfigurationException;
use Micayael\Bundle\FormGeneratorBundle\Exception\NotSupportedFormTypeException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class FormGenerator implements FormGeneratorInterface
{
    private const FIELD_KEYS = ['type', 'options', 'extras'];

    // Opciones de form types que Symfony invoca si reciben un callable en forma de array ([clase, método])
    private const ARRAY_CALLABLE_OPTIONS = ['validation_groups', 'choice_label', 'choice_value', 'choice_name', 'choice_attr', 'choice_filter', 'choice_translation_parameters', 'group_by', 'preferred_choices'];

    // Opciones de form types que solo aceptan callables
    private const CALLABLE_OPTIONS = ['getter', 'setter'];

    // Opciones bloqueadas en modo untrusted: escriben en el modelo, instancian clases, cambian el tipo
    // de los hijos o renderizan HTML sin escapar
    public const UNTRUSTED_BLOCKED_OPTIONS = [
        'property_path', 'mapped', 'inherit_data', 'by_reference', 'data', 'data_class', 'empty_data',
        'getter', 'setter', 'entry_type', 'entry_options', 'prototype_options',
        'label_html', 'help_html', 'block_name', 'block_prefix',
    ];

    /** @var array<string, array{class: string, default_options: array<string, mixed>}> */
    private readonly array $types;

    /** @var list<string> */
    private readonly array $allowedClasses;

    /** @var list<string> */
    private readonly array $allowedOptions;

    private readonly ConstraintFactory $constraintFactory;

    private readonly AsciiSlugger $slugger;

    /**
     * @param array<string, array{class?: ?string, default_options?: array<string, mixed>}> $types              custom aliases or overrides of the built-in ones
     * @param list<string>                                                                  $allowedClasses     if not empty, the only FQCN form types allowed in the "type" key (in untrusted mode, always)
     * @param bool                                                                          $untrusted          the configuration comes from untrusted users: dangerous options and constraints are blocked
     * @param list<string>                                                                  $allowedOptions     options of UNTRUSTED_BLOCKED_OPTIONS allowed anyway in untrusted mode
     * @param list<string>                                                                  $allowedConstraints constraints allowed anyway in untrusted mode (only used if $constraintFactory is null)
     */
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        array $types = [],
        array $allowedClasses = [],
        ?ConstraintFactory $constraintFactory = null,
        private readonly bool $untrusted = false,
        array $allowedOptions = [],
        array $allowedConstraints = [],
    ) {
        $this->types = $this->mergeTypes(SupportedTypes::SUPPORTED_TYPES, $types);
        $this->allowedClasses = array_map(static fn (string $class): string => ltrim($class, '\\'), $allowedClasses);
        $this->allowedOptions = $allowedOptions;
        $this->constraintFactory = $constraintFactory ?? new ConstraintFactory($untrusted, $allowedConstraints);
        $this->slugger = new AsciiSlugger();

        if ($untrusted && !$this->constraintFactory->isUntrusted()) {
            throw new InvalidConfigurationException('In untrusted mode the ConstraintFactory must also be untrusted.');
        }
    }

    public function createFormFromYaml(string $formConfigYaml, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormInterface
    {
        try {
            $formConfig = Yaml::parse($formConfigYaml);
        } catch (ParseException $e) {
            throw new InvalidConfigurationException(sprintf('Invalid YAML form configuration: %s', $e->getMessage()), 0, $e);
        }

        return $this->createForm($this->ensureArray($formConfig, 'YAML'), $formOptions, $data, $baseFormTypeClass, $groupName, $formName);
    }

    public function createFormFromJson(string $formConfigJson, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormInterface
    {
        try {
            $formConfig = json_decode($formConfigJson, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidConfigurationException(sprintf('Invalid JSON form configuration: %s', $e->getMessage()), 0, $e);
        }

        return $this->createForm($this->ensureArray($formConfig, 'JSON'), $formOptions, $data, $baseFormTypeClass, $groupName, $formName);
    }

    public function createForm(array $formConfig, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormInterface
    {
        return $this->createFormBuilder($formConfig, $formOptions, $data, $baseFormTypeClass, $groupName, $formName)->getForm();
    }

    public function createFormBuilder(array $formConfig, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormBuilderInterface
    {
        $baseFormTypeClass ??= FormType::class;

        $builder = null === $formName
            ? $this->formFactory->createBuilder($baseFormTypeClass, $data, $formOptions)
            : $this->formFactory->createNamedBuilder($formName, $baseFormTypeClass, $data, $formOptions);

        // Los campos generados se agregan dentro de un formulario embebido
        $rootBuilder = $builder;

        if ($groupName) {
            if (!$builder->has($groupName)) {
                $builder->add($groupName, FormType::class);
            }

            $rootBuilder = $builder->get($groupName);
        }

        // En modo untrusted los nombres de los campos no pueden escribir propiedades de un objeto
        if ($this->untrusted && $formConfig && null !== $rootBuilder->getDataClass()) {
            throw new InvalidConfigurationException(sprintf('In untrusted mode the fields can\'t be added to a form with a "data_class" ("%s"): use the $groupName argument.', $rootBuilder->getDataClass()));
        }

        // Claves de los grupos creados por extras.group / extras.position
        $groupKeys = [];

        foreach ($formConfig as $inputName => $inputConfig) {
            $inputName = (string) $inputName;
            $inputConfig = $this->normalizeInputConfig($inputName, $inputConfig);
            $typeConfig = $this->resolveType($inputConfig['type']);

            $this->assertNoCallableOptions($inputName, $inputConfig['options']);
            $this->assertAllowedOptions($inputName, $inputConfig['options']);

            // Se compilan las opciones default con las definidas
            $inputOptions = array_replace($typeConfig['default_options'], $inputConfig['options']);

            $constraints = $this->buildConstraints(
                $inputName,
                $typeConfig['default_options']['constraints'] ?? [],
                $inputConfig['options']['constraints'] ?? [],
                false !== ($inputOptions['required'] ?? true)
            );

            // Sin constraints se respetan los defaults propios del form type
            unset($inputOptions['constraints']);

            if ($constraints) {
                $inputOptions['constraints'] = $constraints;
            }

            if (isset($groupKeys[$inputName]) && !isset($inputConfig['extras']['group']) && !isset($inputConfig['extras']['position'])) {
                throw new InvalidConfigurationException(sprintf('The field "%s" collides with the group of the same name.', $inputName));
            }

            $parentBuilder = $this->resolveParentBuilder($rootBuilder, $inputConfig['extras'], $groupKeys);

            if ($this->untrusted && $parentBuilder->has($inputName)) {
                throw new InvalidConfigurationException(sprintf('In untrusted mode the field "%s" can\'t replace a field of the base form type.', $inputName));
            }

            $parentBuilder->add($inputName, $typeConfig['class'], $inputOptions);
        }

        return $builder;
    }

    /**
     * @param array<string, array{class: string, default_options: array<string, mixed>}>    $builtInTypes
     * @param array<string, array{class?: ?string, default_options?: array<string, mixed>}> $customTypes
     *
     * @return array<string, array{class: string, default_options: array<string, mixed>}>
     */
    private function mergeTypes(array $builtInTypes, array $customTypes): array
    {
        foreach ($customTypes as $alias => $typeConfig) {
            $base = $builtInTypes[$alias] ?? ['class' => null, 'default_options' => []];
            $class = $typeConfig['class'] ?? $base['class'];

            if (!$class) {
                throw new InvalidConfigurationException(sprintf('The custom type "%s" must define a "class".', $alias));
            }

            $builtInTypes[$alias] = [
                'class' => $class,
                'default_options' => array_replace($base['default_options'], $typeConfig['default_options'] ?? []),
            ];
        }

        return $builtInTypes;
    }

    /**
     * @return array<string|int, mixed>
     */
    private function ensureArray(mixed $formConfig, string $format): array
    {
        if (null === $formConfig) {
            return [];
        }

        if (!\is_array($formConfig)) {
            throw new InvalidConfigurationException(sprintf('The %s form configuration must be a map of fields, "%s" given.', $format, get_debug_type($formConfig)));
        }

        return $formConfig;
    }

    /**
     * @return array{type: string, options: array<string, mixed>, extras: array<string, mixed>}
     */
    private function normalizeInputConfig(string $inputName, mixed $inputConfig): array
    {
        $inputConfig ??= [];

        if (!\is_array($inputConfig)) {
            throw new InvalidConfigurationException(sprintf('The configuration of the field "%s" must be an array or null, "%s" given.', $inputName, get_debug_type($inputConfig)));
        }

        $unknownKeys = array_diff(array_keys($inputConfig), self::FIELD_KEYS);

        if ($unknownKeys) {
            throw new InvalidConfigurationException(sprintf('Unknown key(s) "%s" in the configuration of the field "%s". Allowed keys: "%s".', implode('", "', $unknownKeys), $inputName, implode('", "', self::FIELD_KEYS)));
        }

        $type = $inputConfig['type'] ?? 'text';
        $options = $inputConfig['options'] ?? [];
        $extras = $inputConfig['extras'] ?? [];

        if (!\is_string($type)) {
            throw new InvalidConfigurationException(sprintf('The "type" of the field "%s" must be a string, "%s" given.', $inputName, get_debug_type($type)));
        }

        foreach (['options' => $options, 'extras' => $extras] as $key => $value) {
            if (!\is_array($value)) {
                throw new InvalidConfigurationException(sprintf('The "%s" of the field "%s" must be an array, "%s" given.', $key, $inputName, get_debug_type($value)));
            }
        }

        return ['type' => $type, 'options' => $options, 'extras' => $extras];
    }

    /**
     * @return array{class: string, default_options: array<string, mixed>}
     */
    private function resolveType(string $type): array
    {
        if (isset($this->types[$type])) {
            return $this->types[$type];
        }

        if (!class_exists($type) || !is_subclass_of($type, FormTypeInterface::class) || !(new \ReflectionClass($type))->isInstantiable()) {
            throw NotSupportedFormTypeException::unknown($type, array_keys($this->types));
        }

        if (($this->untrusted || $this->allowedClasses) && !\in_array(ltrim($type, '\\'), $this->allowedClasses, true)) {
            throw NotSupportedFormTypeException::notAllowed($type);
        }

        return ['class' => $type, 'default_options' => []];
    }

    /**
     * Las constraints pueden definirse como "nombre: opciones", como una lista de nombres
     * o como objetos Constraint. Con "nombre: false" se desactiva una constraint por defecto.
     * Las definidas por nombre se identifican por su clase, así no se duplican.
     *
     * @return list<Constraint>
     */
    private function buildConstraints(string $inputName, mixed $defaultConstraints, mixed $inputConstraints, bool $required): array
    {
        $definitions = [];
        $objects = [];

        foreach ([$defaultConstraints, $inputConstraints] as $constraints) {
            if ($constraints instanceof Constraint) {
                $constraints = [$constraints];
            }

            $constraints ??= [];

            if (!\is_array($constraints)) {
                throw new InvalidConfigurationException(sprintf('The "constraints" option of the field "%s" must be an array, "%s" given.', $inputName, get_debug_type($constraints)));
            }

            foreach ($constraints as $name => $options) {
                if ($options instanceof Constraint) {
                    $objects[] = $options;

                    continue;
                }

                if (\is_int($name)) {
                    if (!\is_string($options)) {
                        throw new InvalidConfigurationException(sprintf('Invalid constraint definition in the field "%s": expected a constraint name, "%s" given.', $inputName, get_debug_type($options)));
                    }

                    $name = $options;
                    $options = [];
                }

                $definitions[$this->constraintFactory->resolveClass($name)] = $options;
            }
        }

        $result = [];

        foreach ($definitions as $class => $options) {
            if (false === $options || (NotBlank::class === $class && !$required)) {
                continue;
            }

            $result[] = $this->constraintFactory->create($class, $options);
        }

        return array_merge($result, $objects);
    }

    /**
     * Por seguridad, los callables solo se aceptan como Closure (definidos en PHP, no en YAML/JSON).
     *
     * @param array<string, mixed> $options
     */
    private function assertNoCallableOptions(string $inputName, array $options): void
    {
        foreach ($options as $option => $value) {
            $isArrayCallable = \in_array($option, self::ARRAY_CALLABLE_OPTIONS, true) && \is_array($value) && \is_callable($value);
            $isCallable = \in_array($option, self::CALLABLE_OPTIONS, true) && null !== $value && !$value instanceof \Closure;

            if ($isArrayCallable || $isCallable) {
                throw new InvalidConfigurationException(sprintf('The option "%s" of the field "%s" only accepts a callable as a \Closure.', $option, $inputName));
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function assertAllowedOptions(string $inputName, array $options): void
    {
        if (!$this->untrusted) {
            return;
        }

        $blockedOptions = array_diff(array_intersect(array_keys($options), self::UNTRUSTED_BLOCKED_OPTIONS), $this->allowedOptions);

        if ($blockedOptions) {
            throw new InvalidConfigurationException(sprintf('The option(s) "%s" of the field "%s" are not allowed in untrusted mode.', implode('", "', $blockedOptions), $inputName));
        }
    }

    /**
     * Agrupa el campo según "extras.group" y "extras.position". Los grupos usan inherit_data,
     * por lo que los datos de sus campos quedan en el formulario padre.
     *
     * @param array<string, mixed> $extras
     * @param array<string, true>  $groupKeys claves de los grupos ya creados
     */
    private function resolveParentBuilder(FormBuilderInterface $builder, array $extras, array &$groupKeys): FormBuilderInterface
    {
        if (!isset($extras['group']) && !isset($extras['position'])) {
            return $builder;
        }

        $group = (string) ($extras['group'] ?? 'default');
        $position = (string) ($extras['position'] ?? 'default');

        $groupKey = strtolower((string) $this->slugger->slug($group.'_'.$position, '_'));

        if (!isset($groupKeys[$groupKey])) {
            if ($builder->has($groupKey)) {
                throw new InvalidConfigurationException(sprintf('The group "%s" collides with the field of the same name.', $groupKey));
            }

            $groupKeys[$groupKey] = true;

            $builder->add($groupKey, FormType::class, [
                'label' => 'default' === $group ? false : $group,
                'inherit_data' => true,
            ]);
        }

        return $builder->get($groupKey);
    }
}
