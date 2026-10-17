<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests;

use Micayael\Bundle\FormGeneratorBundle\Exception\InvalidConfigurationException;
use Micayael\Bundle\FormGeneratorBundle\Exception\NotSupportedFormTypeException;
use Micayael\Bundle\FormGeneratorBundle\Service\ConstraintFactory;
use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
use Micayael\Bundle\FormGeneratorBundle\Service\FormGeneratorInterface;
use Micayael\Bundle\FormGeneratorBundle\Tests\Form\Type\DemoFormType;
use Micayael\Bundle\FormGeneratorBundle\Tests\Form\Type\FixedFieldsType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Yaml\Yaml;

final class FormGeneratorTest extends KernelTestCase
{
    private FormGenerator $formGenerator;

    /** @var array<string, mixed> */
    private array $originalData = [];

    /** @var array<string, mixed> */
    private array $expectedData = [];

    protected static function getKernelClass(): string
    {
        return AppTestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        $formGenerator = static::getContainer()->get('micayael.form_generator');
        \assert($formGenerator instanceof FormGenerator);

        $this->formGenerator = $formGenerator;

        $this->originalData = [
            'name' => 'Jhon Doe',
            'birthday' => (new \DateTime())->format('Y-m-d'),
        ];

        $this->expectedData = [
            'name' => 'Jhon Doe',
            'birthday' => (new \DateTime())->format('Y-m-d'),
            'status' => null,
            'custom_type' => null,
        ];
    }

    public function testServiceWiring(): void
    {
        $this->assertInstanceOf(FormGenerator::class, $this->formGenerator);
        $this->assertSame($this->formGenerator, static::getContainer()->get(FormGeneratorInterface::class));
    }

    public function testFormFromArray(): void
    {
        $form = $this->formGenerator->createForm($this->getFormConfigAsArray(), [], $this->originalData);

        $this->assertSubmittedData($form);
    }

    public function testFormFromJson(): void
    {
        $form = $this->formGenerator->createFormFromJson($this->getFormConfigAsJson(), [], $this->originalData);

        $this->assertSubmittedData($form);
    }

    public function testFormFromYaml(): void
    {
        $form = $this->formGenerator->createFormFromYaml($this->getFormConfigAsYaml(), [], $this->originalData);

        $this->assertSubmittedData($form);
    }

    public function testFormWithBaseFormClass(): void
    {
        $formConfigArray = $this->getFormConfigAsArray();

        $form = $this->formGenerator->createForm($formConfigArray, [], null, FixedFieldsType::class);

        foreach (array_keys($formConfigArray) as $fieldName) {
            $this->assertTrue($form->has($fieldName));
        }

        foreach (['fixed1', 'fixed2'] as $fieldName) {
            $this->assertTrue($form->has($fieldName));
        }
    }

    public function testFormWithBaseFormClassAndGroupName(): void
    {
        $formConfigArray = $this->getFormConfigAsArray();

        $form = $this->formGenerator->createForm($formConfigArray, [], null, FixedFieldsType::class, 'extra_fields');

        $this->assertTrue($form->has('extra_fields'));

        foreach (array_keys($formConfigArray) as $fieldName) {
            $this->assertTrue($form->get('extra_fields')->has($fieldName));
        }

        foreach (['fixed1', 'fixed2'] as $fieldName) {
            $this->assertTrue($form->has($fieldName));
        }

        $form->submit(['fixed1' => 'a', 'extra_fields' => $this->originalData]);

        $this->assertSame('Jhon Doe', $form->getData()['extra_fields']['name']);
    }

    public function testFormName(): void
    {
        $form = $this->formGenerator->createForm(['name' => null], formName: 'contact');

        $this->assertSame('contact', $form->getName());
    }

    public function testCreateFormBuilder(): void
    {
        $builder = $this->formGenerator->createFormBuilder(['name' => null]);

        $this->assertInstanceOf(FormBuilderInterface::class, $builder);
        $this->assertTrue($builder->has('name'));
    }

    public function testEmptyConfigReturnsEmptyForm(): void
    {
        $form = $this->formGenerator->createForm([]);

        $this->assertCount(0, $form);
        $this->assertCount(0, $this->formGenerator->createFormFromYaml(''));
    }

    public function testDefaultOptionsAreApplied(): void
    {
        $form = $this->formGenerator->createForm([
            'email' => ['type' => 'email'],
            'birthday' => ['type' => 'date'],
        ]);

        $this->assertConstraintClasses([NotBlank::class, Email::class], $form->get('email')->getConfig()->getOption('constraints'));
        $this->assertSame('single_text', $form->get('birthday')->getConfig()->getOption('widget'));
        $this->assertSame('string', $form->get('birthday')->getConfig()->getOption('input'));
    }

    public function testFieldWithoutTypeIsText(): void
    {
        $form = $this->formGenerator->createForm(['name' => null]);

        $this->assertInstanceOf(TextType::class, $form->get('name')->getConfig()->getType()->getInnerType());
    }

    public function testNotRequiredSkipsNotBlank(): void
    {
        $form = $this->formGenerator->createForm(['name' => ['options' => ['required' => false]]]);

        $this->assertNull($form->get('name')->getConfig()->getOption('constraints') ?: null);
    }

    public function testDefaultConstraintCanBeDisabled(): void
    {
        $form = $this->formGenerator->createForm([
            'email' => ['type' => 'email', 'options' => ['constraints' => ['not_blank' => false]]],
        ]);

        $this->assertConstraintClasses([Email::class], $form->get('email')->getConfig()->getOption('constraints'));
    }

    public function testAnySymfonyConstraintByName(): void
    {
        $form = $this->formGenerator->createFormFromYaml(<<<'YAML'
            name:
              options:
                constraints:
                  length: { max: 3 }
            YAML);

        $this->assertConstraintClasses([NotBlank::class, Length::class], $form->get('name')->getConfig()->getOption('constraints'));

        $form->submit(['name' => 'abcd']);
        $this->assertFalse($form->isValid());
    }

    public function testConstraintsAsListAndObjects(): void
    {
        $form = $this->formGenerator->createForm([
            'name' => ['options' => ['required' => false, 'constraints' => ['email', new Length(max: 10)]]],
        ]);

        $this->assertConstraintClasses([Email::class, Length::class], $form->get('name')->getConfig()->getOption('constraints'));
    }

    public function testGroupedFieldsKeepDataInParent(): void
    {
        $form = $this->formGenerator->createForm([
            'name' => ['extras' => ['group' => 'Datos personales']],
            'city' => ['options' => ['required' => false]],
        ]);

        $this->assertTrue($form->has('datos_personales_default'));
        $this->assertTrue($form->get('datos_personales_default')->has('name'));
        $this->assertSame('Datos personales', $form->get('datos_personales_default')->getConfig()->getOption('label'));
        $this->assertTrue($form->has('city'));

        $form->submit(['datos_personales_default' => ['name' => 'Jhon Doe'], 'city' => 'Asunción']);

        $this->assertTrue($form->isValid());
        $this->assertSame(['name' => 'Jhon Doe', 'city' => 'Asunción'], $form->getData());
    }

    public function testCustomTypes(): void
    {
        $formGenerator = new FormGenerator($this->getFormFactory(), [
            'shipping' => ['class' => DemoFormType::class, 'default_options' => ['custom_option' => 'x']],
            'boolean_choice' => ['default_options' => ['choices' => ['Yes' => true, 'No' => false]]],
        ]);

        $form = $formGenerator->createForm([
            'shipping' => ['type' => 'shipping'],
            'accept' => ['type' => 'boolean_choice'],
        ]);

        $this->assertInstanceOf(DemoFormType::class, $form->get('shipping')->getConfig()->getType()->getInnerType());
        $this->assertSame(['Yes' => true, 'No' => false], $form->get('accept')->getConfig()->getOption('choices'));
    }

    public function testAllowedClasses(): void
    {
        $formGenerator = new FormGenerator($this->getFormFactory(), [], [DemoFormType::class]);

        $form = $formGenerator->createForm(['shipping' => ['type' => DemoFormType::class, 'options' => ['custom_option' => 'x']]]);
        $this->assertTrue($form->has('shipping'));

        $this->expectException(NotSupportedFormTypeException::class);
        $formGenerator->createForm(['fixed' => ['type' => FixedFieldsType::class]]);
    }

    public function testInvalidConfigurations(): void
    {
        $invalidConfigs = [
            'unknown type' => fn () => $this->formGenerator->createForm(['a' => ['type' => 'foo']]),
            'not a form type' => fn () => $this->formGenerator->createForm(['a' => ['type' => \stdClass::class]]),
            'unknown key' => fn () => $this->formGenerator->createForm(['a' => ['option' => []]]),
            'scalar field' => fn () => $this->formGenerator->createForm(['a' => 'text']),
            'unknown constraint' => fn () => $this->formGenerator->createForm(['a' => ['options' => ['constraints' => ['foo_bar' => []]]]]),
            'unknown constraint option' => fn () => $this->formGenerator->createForm(['a' => ['options' => ['constraints' => ['length' => ['foo' => 1]]]]]),
            'invalid json' => fn () => $this->formGenerator->createFormFromJson('{'),
            'invalid yaml' => fn () => $this->formGenerator->createFormFromYaml('a: [b'),
            'scalar yaml' => fn () => $this->formGenerator->createFormFromYaml('text'),
            'abstract form type' => fn () => $this->formGenerator->createForm(['a' => ['type' => AbstractType::class]]),
            'abstract constraint' => fn () => $this->formGenerator->createForm(['a' => ['options' => ['constraints' => [Composite::class => []]]]]),
            'callable normalizer' => fn () => $this->formGenerator->createForm(['a' => ['options' => ['constraints' => ['not_blank' => ['normalizer' => 'trim']]]]]),
            'callable callback' => fn () => $this->formGenerator->createForm(['a' => ['options' => ['constraints' => ['callback' => ['callback' => [self::class, 'probe']]]]]]),
            'nested callable' => fn () => $this->formGenerator->createForm(['a' => ['options' => ['constraints' => ['all' => ['constraints' => ['not_blank' => ['normalizer' => 'trim']]]]]]]),
            'callable validation_groups' => fn () => $this->formGenerator->createForm(['a' => ['options' => ['validation_groups' => [self::class, 'probe']]]]),
            'callable is_empty_callback' => fn () => $this->formGenerator->createForm(['a' => ['options' => ['is_empty_callback' => 'trim']]]),
            'callable delete_empty' => fn () => $this->formGenerator->createForm(['a' => ['type' => CollectionType::class, 'options' => ['delete_empty' => 'trim']]]),
            'callable getter' => fn () => $this->formGenerator->createForm(['a' => ['options' => ['getter' => 'trim']]]),
            'callable choice_label' => fn () => $this->formGenerator->createForm(['a' => ['type' => 'choice', 'options' => ['choice_label' => [self::class, 'probe']]]]),
            'group after field' => fn () => $this->formGenerator->createForm(['g_default' => null, 'a' => ['extras' => ['group' => 'g']]]),
            'field after group' => fn () => $this->formGenerator->createForm(['a' => ['extras' => ['group' => 'g']], 'g_default' => null]),
        ];

        foreach ($invalidConfigs as $case => $createForm) {
            try {
                $createForm();
                $this->fail(sprintf('Case "%s" should throw an exception.', $case));
            } catch (InvalidConfigurationException|NotSupportedFormTypeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNotBlankByClassRespectsRequired(): void
    {
        $form = $this->formGenerator->createForm([
            'name' => ['options' => ['required' => false, 'constraints' => [NotBlank::class => [], 'NotBlank' => []]]],
        ]);

        $this->assertNull($form->get('name')->getConfig()->getOption('constraints') ?: null);
    }

    public function testConstraintsAreNotDuplicated(): void
    {
        $form = $this->formGenerator->createForm([
            'email' => ['type' => 'email', 'options' => ['constraints' => ['not_blank', 'email']]],
        ]);

        $this->assertConstraintClasses([NotBlank::class, Email::class], $form->get('email')->getConfig()->getOption('constraints'));
    }

    public function testNestedConstraints(): void
    {
        $form = $this->formGenerator->createFormFromYaml(<<<'YAML'
            tags:
              type: Symfony\Component\Form\Extension\Core\Type\CollectionType
              options:
                allow_add: true
                constraints:
                  all:
                    constraints:
                      length: { max: 3 }
            YAML);

        $constraints = $form->get('tags')->getConfig()->getOption('constraints');
        $this->assertConstraintClasses([All::class], $constraints);
        $this->assertInstanceOf(Length::class, $constraints[0]->constraints[0]);

        $form->submit(['tags' => ['abc', 'abcd']]);
        $this->assertFalse($form->isValid());

        $collection = (new ConstraintFactory())->create('collection', [
            'fields' => [
                'email' => ['email'],
                'name' => ['optional' => ['constraints' => ['length' => ['max' => 3]]]],
            ],
        ]);
        \assert($collection instanceof Collection);

        $this->assertInstanceOf(Required::class, $collection->fields['email']);
        $this->assertInstanceOf(Optional::class, $collection->fields['name']);
    }

    public function testStringChoiceLabelIsAllowed(): void
    {
        $form = $this->formGenerator->createForm([
            'status' => ['type' => 'choice', 'options' => ['choices' => ['A' => 'a'], 'choice_label' => 'key']],
        ]);

        $this->assertSame('key', $form->get('status')->getConfig()->getOption('choice_label'));
    }

    public function testValidateConfig(): void
    {
        $this->assertSame([], $this->formGenerator->validateConfig($this->getFormConfigAsYaml()));
        $this->assertSame([], $this->formGenerator->validateConfig($this->getFormConfigAsJson(), 'json'));
        $this->assertSame([], $this->formGenerator->validateConfig($this->getFormConfigAsArray()));

        $errors = $this->formGenerator->validateConfig(<<<'YAML'
            name:
            age:
              type: integr
            email:
              type: email
              options:
                foo: bar
            city:
              option: {}
            YAML);

        $this->assertSame(['age', 'email', 'city'], array_keys($errors));
        $this->assertStringContainsString('"integr" is not supported', $errors['age']);
        $this->assertStringContainsString('"foo"', $errors['email']);
        $this->assertStringContainsString('Unknown key(s) "option"', $errors['city']);
    }

    public function testValidateConfigGlobalErrors(): void
    {
        $this->assertArrayHasKey('', $this->formGenerator->validateConfig('{', 'json'));
        $this->assertArrayHasKey('', $this->formGenerator->validateConfig('a:', 'xml'));

        $errors = $this->formGenerator->validateConfig(['g_default' => null, 'a' => ['extras' => ['group' => 'g']]]);
        $this->assertSame([''], array_keys($errors));
        $this->assertStringContainsString('collides', $errors['']);
    }

    public function testAllowedConstraintsNeedTheDefaultConstraintFactory(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new FormGenerator($this->getFormFactory(), constraintFactory: new ConstraintFactory(), allowedConstraints: ['expression']);
    }

    public function testGetTypes(): void
    {
        $types = $this->formGenerator->getTypes();

        $this->assertSame(TextType::class, $types['text']['class']);
        $this->assertSame(['Yes' => true, 'No' => false], $types['boolean_choice']['default_options']['choices']);
    }

    public static function probe(): void
    {
        throw new \LogicException('Callables from the configuration must never be called.');
    }

    private function assertSubmittedData(FormInterface $form): void
    {
        $form->submit($this->originalData);

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($this->expectedData, $form->getData());
    }

    /**
     * @param list<class-string<Constraint>> $expected
     */
    private function assertConstraintClasses(array $expected, mixed $constraints): void
    {
        $this->assertIsArray($constraints);
        $this->assertSame($expected, array_map(static fn (Constraint $constraint): string => $constraint::class, $constraints));
    }

    public function testAccessors(): void
    {
        $generator = new FormGenerator($this->getFormFactory(), allowedClasses: ['\\'.DemoFormType::class], allowedOptions: ['label_html']);
        $this->assertFalse($generator->isUntrusted());
        $this->assertSame([DemoFormType::class], $generator->getAllowedClasses());
        $this->assertSame(['label_html'], $generator->getAllowedOptions());
        $this->assertInstanceOf(ConstraintFactory::class, $generator->getConstraintFactory());
    }

    public function testAvailableConstraints(): void
    {
        $constraints = (new ConstraintFactory())->getAvailableConstraints();
        $this->assertSame(Length::class, $constraints['length']);
        $this->assertSame(NotBlank::class, $constraints['not_blank']);
        $this->assertArrayHasKey('expression', $constraints);
        $this->assertArrayNotHasKey('length_validator', $constraints);
        $this->assertArrayNotHasKey('composite', $constraints);
    }

    private function getFormFactory(): FormFactoryInterface
    {
        $formFactory = static::getContainer()->get('form.factory');
        \assert($formFactory instanceof FormFactoryInterface);

        return $formFactory;
    }

    private function getFormConfigAsYaml(): string
    {
        return 'name:
birthday:
  type: date
  options:
    label: Your Birthday
status:
  type: choice
  options:
    required: false
    choices:
      Active: A
      Inactive: I
custom_type:
  type: Micayael\Bundle\FormGeneratorBundle\Tests\Form\Type\DemoFormType
  options:
    custom_option: value';
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormConfigAsArray(): array
    {
        return Yaml::parse($this->getFormConfigAsYaml());
    }

    private function getFormConfigAsJson(): string
    {
        return json_encode($this->getFormConfigAsArray(), \JSON_THROW_ON_ERROR);
    }
}
