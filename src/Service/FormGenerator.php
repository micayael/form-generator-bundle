<?php

namespace Micayael\Bundle\FormGeneratorBundle\Service;

use Micayael\Bundle\FormGeneratorBundle\Config\SupportedTypes;
use Micayael\Bundle\FormGeneratorBundle\Exception\ExceptionInterface;
use Micayael\Bundle\FormGeneratorBundle\Exception\InvalidConfigurationException;
use Micayael\Bundle\FormGeneratorBundle\Exception\NotSupportedFormTypeException;
use Symfony\Component\Form\Exception\ExceptionInterface as FormExceptionInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\Debug\OptionsResolverIntrospector;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsResolverExceptionInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class FormGenerator implements FormGeneratorInterface
{
    private const FIELD_KEYS = ['type', 'options', 'extras'];

    // Opciones que Symfony invoca si reciben un callable en forma de array, sin declararlo en sus tipos
    private const UNTYPED_ARRAY_CALLABLE_OPTIONS = ['validation_groups'];

    // Opciones con atributos HTML: en modo untrusted solo se aceptan los nombres de SAFE_HTML_ATTRIBUTES
    private const HTML_ATTRIBUTES_OPTIONS = ['attr', 'row_attr', 'label_attr', 'help_attr', 'placeholder_attr'];

    // Atributos HTML permitidos en modo untrusted. Los terminados en "-" son prefijos
    public const SAFE_HTML_ATTRIBUTES = [
        'class', 'placeholder', 'title', 'rows', 'cols', 'size', 'maxlength', 'minlength', 'min', 'max', 'step',
        'pattern', 'autocomplete', 'autofocus', 'inputmode', 'spellcheck', 'readonly', 'dir', 'lang', 'aria-',
    ];

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

    /** @var list<string> */
    private readonly array $allowedHtmlAttributes;

    private readonly ConstraintFactory $constraintFactory;

    private readonly AsciiSlugger $slugger;

    /**
     * @param array<string, array{class?: ?string, default_options?: array<string, mixed>}> $types                 custom aliases or overrides of the built-in ones
     * @param list<string>                                                                  $allowedClasses        if not empty, the only FQCN form types allowed in the "type" key (in untrusted mode, always)
     * @param bool                                                                          $untrusted             the configuration comes from untrusted users: dangerous options and constraints are blocked
     * @param list<string>                                                                  $allowedOptions        options of UNTRUSTED_BLOCKED_OPTIONS allowed anyway in untrusted mode
     * @param list<string>                                                                  $allowedConstraints    constraints allowed anyway in untrusted mode (only if $constraintFactory is null)
     * @param list<string>                                                                  $allowedHtmlAttributes HTML attributes (or prefixes ending with "-") allowed anyway in untrusted mode
     */
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        array $types = [],
        array $allowedClasses = [],
        ?ConstraintFactory $constraintFactory = null,
        private readonly bool $untrusted = false,
        array $allowedOptions = [],
        array $allowedConstraints = [],
        array $allowedHtmlAttributes = [],
    ) {
        $this->types = $this->mergeTypes(SupportedTypes::SUPPORTED_TYPES, $types);
        $this->allowedClasses = array_map(static fn (string $class): string => ltrim($class, '\\'), $allowedClasses);
        $this->allowedOptions = $allowedOptions;
        $this->allowedHtmlAttributes = array_map('strtolower', array_merge(self::SAFE_HTML_ATTRIBUTES, $allowedHtmlAttributes));

        if ($constraintFactory && $allowedConstraints) {
            throw new InvalidConfigurationException('The $allowedConstraints argument is ignored when a ConstraintFactory is given: pass them to the ConstraintFactory.');
        }

        $this->constraintFactory = $constraintFactory ?? new ConstraintFactory($untrusted, $allowedConstraints);
        $this->slugger = new AsciiSlugger();

        if ($untrusted && !$this->constraintFactory->isUntrusted()) {
            throw new InvalidConfigurationException('In untrusted mode the ConstraintFactory must also be untrusted.');
        }
    }

    public function createFormFromYaml(string $formConfigYaml, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormInterface
    {
        return $this->createForm($this->parseConfig($formConfigYaml, 'yaml'), $formOptions, $data, $baseFormTypeClass, $groupName, $formName);
    }

    public function createFormFromJson(string $formConfigJson, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormInterface
    {
        return $this->createForm($this->parseConfig($formConfigJson, 'json'), $formOptions, $data, $baseFormTypeClass, $groupName, $formName);
    }

    /**
     * Type aliases (built-in and custom) with their form type and default options.
     *
     * @return array<string, array{class: string, default_options: array<string, mixed>}>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function validateConfig(array|string $formConfig, string $format = 'yaml', array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null): array
    {
        try {
            $formConfig = \is_string($formConfig) ? $this->parseConfig($formConfig, $format) : $formConfig;
        } catch (ExceptionInterface $e) {
            return ['' => $e->getMessage()];
        }

        $errors = [];

        // Cada campo por separado, para informar todos los errores y no solo el primero
        foreach ($formConfig as $inputName => $inputConfig) {
            $error = $this->tryToCreateForm([$inputName => $inputConfig], $formOptions, $data, $baseFormTypeClass, $groupName);

            if (null !== $error) {
                $errors[(string) $inputName] = $error;
            }
        }

        // Errores entre campos, como colisiones con grupos
        if (!$errors) {
            $error = $this->tryToCreateForm($formConfig, $formOptions, $data, $baseFormTypeClass, $groupName);

            if (null !== $error) {
                $errors[''] = $error;
            }
        }

        return $errors;
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

            $this->assertNoCallableStrings($inputName, $parentBuilder->get($inputName), $inputConfig['options']);
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
    private function parseConfig(string $formConfig, string $format): array
    {
        if ('yaml' === $format) {
            try {
                return $this->ensureArray(Yaml::parse($formConfig), 'YAML');
            } catch (ParseException $e) {
                throw new InvalidConfigurationException(sprintf('Invalid YAML form configuration: %s', $e->getMessage()), 0, $e);
            }
        }

        if ('json' === $format) {
            try {
                return $this->ensureArray(json_decode($formConfig, true, 512, \JSON_THROW_ON_ERROR), 'JSON');
            } catch (\JsonException $e) {
                throw new InvalidConfigurationException(sprintf('Invalid JSON form configuration: %s', $e->getMessage()), 0, $e);
            }
        }

        throw new InvalidConfigurationException(sprintf('Unknown form configuration format "%s". Use "yaml" or "json".', $format));
    }

    /**
     * @param array<string|int, mixed> $formConfig
     * @param array<string, mixed>     $formOptions
     */
    private function tryToCreateForm(array $formConfig, array $formOptions, mixed $data, ?string $baseFormTypeClass, ?string $groupName): ?string
    {
        try {
            $this->createForm($formConfig, $formOptions, $data, $baseFormTypeClass, $groupName);
        } catch (ExceptionInterface|FormExceptionInterface|OptionsResolverExceptionInterface $e) {
            return $e->getMessage();
        }

        return null;
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
     * Se rechazan los strings y arrays en las opciones que Symfony acepta como callables, salvo que
     * la opción también acepte un string (p. ej. choice_label, que lo usa como property path).
     *
     * @param array<string, mixed> $options
     */
    private function assertNoCallableStrings(string $inputName, FormBuilderInterface $builder, array $options): void
    {
        $introspector = new OptionsResolverIntrospector($builder->getType()->getOptionsResolver());

        foreach ($options as $option => $value) {
            if (!\is_string($value) && !\is_array($value)) {
                continue;
            }

            if (\in_array($option, self::UNTYPED_ARRAY_CALLABLE_OPTIONS, true) && \is_array($value) && \is_callable($value)) {
                throw new InvalidConfigurationException(sprintf('The option "%s" of the field "%s" only accepts a callable as a \\Closure.', $option, $inputName));
            }

            try {
                $allowedTypes = $introspector->getAllowedTypes((string) $option);
            } catch (OptionsResolverExceptionInterface) {
                // Opción sin tipos declarados
                continue;
            }

            if (!array_intersect($allowedTypes, ['callable', 'Closure', '\\Closure'])) {
                continue;
            }

            $isData = \is_string($value) ? \in_array('string', $allowedTypes, true) : !\is_callable($value);

            if (!$isData) {
                throw new InvalidConfigurationException(sprintf('The option "%s" of the field "%s" only accepts a callable as a \\Closure.', $option, $inputName));
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

        foreach (self::HTML_ATTRIBUTES_OPTIONS as $option) {
            if (isset($options[$option])) {
                $this->assertSafeHtmlAttributes($inputName, $option, $options[$option]);
            }
        }

        // choice_attr: atributos por opción del choice
        if (isset($options['choice_attr'])) {
            if (!\is_array($options['choice_attr'])) {
                throw new InvalidConfigurationException(sprintf('In untrusted mode the option "choice_attr" of the field "%s" must be an array of attributes by choice.', $inputName));
            }

            foreach ($options['choice_attr'] as $attributes) {
                $this->assertSafeHtmlAttributes($inputName, 'choice_attr', $attributes);
            }
        }
    }

    /**
     * El escape de Twig protege los valores de los atributos, no sus nombres: en modo untrusted
     * solo se aceptan nombres conocidos (sin manejadores de eventos como "onclick" ni "style").
     */
    private function assertSafeHtmlAttributes(string $inputName, string $option, mixed $attributes): void
    {
        if (!\is_array($attributes)) {
            throw new InvalidConfigurationException(sprintf('The option "%s" of the field "%s" must be an array of HTML attributes.', $option, $inputName));
        }

        foreach ($attributes as $name => $value) {
            if (!$this->isSafeHtmlAttribute((string) $name) || (null !== $value && !\is_scalar($value))) {
                throw new InvalidConfigurationException(sprintf('The HTML attribute "%s" in the option "%s" of the field "%s" is not allowed in untrusted mode.', $name, $option, $inputName));
            }
        }
    }

    private function isSafeHtmlAttribute(string $name): bool
    {
        $name = strtolower($name);

        if (!preg_match('/^[a-z][a-z0-9_.:-]*$/', $name)) {
            return false;
        }

        foreach ($this->allowedHtmlAttributes as $allowed) {
            if ($name === $allowed || (str_ends_with($allowed, '-') && str_starts_with($name, $allowed))) {
                return true;
            }
        }

        return false;
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
