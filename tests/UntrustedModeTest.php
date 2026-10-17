<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests;

use Micayael\Bundle\FormGeneratorBundle\Exception\InvalidConfigurationException;
use Micayael\Bundle\FormGeneratorBundle\Exception\NotSupportedFormTypeException;
use Micayael\Bundle\FormGeneratorBundle\Service\ConstraintFactory;
use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
use Micayael\Bundle\FormGeneratorBundle\Tests\Form\Type\DemoFormType;
use Micayael\Bundle\FormGeneratorBundle\Tests\Form\Type\FixedFieldsType;
use Micayael\Bundle\FormGeneratorBundle\Tests\Validator\AppConstraint;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Constraints\Length;

final class UntrustedModeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AppTestKernel::class;
    }

    public function testSafeConfigurationWorks(): void
    {
        $form = $this->createGenerator()->createFormFromYaml(<<<'YAML'
            name:
              extras: { group: Personal data }
              options:
                label: Name
                attr: { placeholder: 'Your name' }
                constraints:
                  length: { max: 10 }
            status:
              type: choice
              options:
                choices: { Active: A }
            YAML);

        $form->submit(['personal_data_default' => ['name' => 'Jhon'], 'status' => 'A']);

        $this->assertTrue($form->isValid());
        $this->assertSame(['name' => 'Jhon', 'status' => 'A'], $form->getData());
    }

    public function testBlockedOptions(): void
    {
        $formGenerator = $this->createGenerator();

        foreach (FormGenerator::UNTRUSTED_BLOCKED_OPTIONS as $option) {
            try {
                $formGenerator->createForm(['a' => ['options' => [$option => 'x']]]);
                $this->fail(sprintf('The option "%s" should be blocked.', $option));
            } catch (InvalidConfigurationException $e) {
                // getter/setter ya los rechaza el control de callables
                $this->assertMatchesRegularExpression('/not allowed in untrusted mode|only accepts a callable/', $e->getMessage());
            }
        }
    }

    public function testAllowedOptions(): void
    {
        $form = $this->createGenerator(allowedOptions: ['label_html'])->createForm([
            'a' => ['options' => ['label' => '<b>Name</b>', 'label_html' => true]],
        ]);

        $this->assertTrue($form->get('a')->getConfig()->getOption('label_html'));
    }

    public function testBlockedConstraints(): void
    {
        $formGenerator = $this->createGenerator();

        $blocked = [
            'expression' => ['expression' => ['expression' => 'true']],
            'when' => ['when' => ['expression' => 'true', 'constraints' => ['not_blank']]],
            'callback' => ['callback' => []],
            'nested' => ['all' => ['constraints' => ['expression' => ['expression' => 'true']]]],
            'non Symfony' => [AppConstraint::class => []],
        ];

        foreach ($blocked as $case => $constraints) {
            try {
                $formGenerator->createForm(['a' => ['options' => ['constraints' => $constraints]]]);
                $this->fail(sprintf('The case "%s" should be blocked.', $case));
            } catch (InvalidConfigurationException $e) {
                // When no existe en Symfony < 6.2
                $this->assertMatchesRegularExpression('/not allowed in untrusted mode|Unknown constraint "when"/', $e->getMessage());
            }
        }
    }

    public function testAllowedConstraints(): void
    {
        $form = $this->createGenerator(allowedConstraints: [AppConstraint::class])->createForm([
            'a' => ['options' => ['constraints' => [AppConstraint::class => [], 'length' => ['max' => 3]]]],
        ]);

        $constraints = $form->get('a')->getConfig()->getOption('constraints');

        $this->assertInstanceOf(AppConstraint::class, $constraints[1]);
        $this->assertInstanceOf(Length::class, $constraints[2]);
    }

    public function testFqcnTypesNeedAllowedClasses(): void
    {
        try {
            $this->createGenerator()->createForm(['a' => ['type' => CollectionType::class]]);
            $this->fail('FQCN types should be blocked.');
        } catch (NotSupportedFormTypeException) {
            $this->addToAssertionCount(1);
        }

        $form = $this->createGenerator(allowedClasses: [DemoFormType::class])->createForm([
            'a' => ['type' => DemoFormType::class, 'options' => ['custom_option' => 'x']],
        ]);

        $this->assertTrue($form->has('a'));
    }

    public function testFieldsCantBeMappedToAnObject(): void
    {
        try {
            $this->createGenerator()->createForm(['roles' => null], ['data_class' => \ArrayObject::class]);
            $this->fail('Fields mapped to an object should be blocked.');
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString('data_class', $e->getMessage());
        }

        $form = $this->createGenerator()->createForm(['color' => null], ['data_class' => \ArrayObject::class], groupName: 'attributes');

        $this->assertTrue($form->get('attributes')->has('color'));
    }

    public function testFieldsCantReplaceBaseFields(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('can\'t replace a field');

        $this->createGenerator()->createForm(['fixed1' => null], baseFormTypeClass: FixedFieldsType::class);
    }

    public function testConstraintFactoryMustBeUntrusted(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new FormGenerator($this->getFormFactory(), constraintFactory: new ConstraintFactory(), untrusted: true);
    }

    public function testTrustedModeIsUnchanged(): void
    {
        $form = (new FormGenerator($this->getFormFactory()))->createForm([
            'a' => ['type' => FormType::class, 'options' => ['label_html' => true, 'property_path' => '[b]']],
        ]);

        $this->assertTrue($form->get('a')->getConfig()->getOption('label_html'));
    }

    /**
     * @param list<string> $allowedClasses
     * @param list<string> $allowedOptions
     * @param list<string> $allowedConstraints
     */
    private function createGenerator(array $allowedClasses = [], array $allowedOptions = [], array $allowedConstraints = []): FormGenerator
    {
        return new FormGenerator(
            $this->getFormFactory(),
            allowedClasses: $allowedClasses,
            untrusted: true,
            allowedOptions: $allowedOptions,
            allowedConstraints: $allowedConstraints,
        );
    }

    private function getFormFactory(): FormFactoryInterface
    {
        self::bootKernel();

        $formFactory = static::getContainer()->get('form.factory');
        \assert($formFactory instanceof FormFactoryInterface);

        return $formFactory;
    }
}
