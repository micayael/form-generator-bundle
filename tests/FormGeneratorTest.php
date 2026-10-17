<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests;

use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
use Micayael\Bundle\FormGeneratorBundle\Tests\Form\Type\FixedFieldsType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Yaml\Yaml;

class FormGeneratorTest extends TestCase
{
    /** @var FormGenerator */
    private $formGeneratorService;

    private $originalData = [];

    private $expectedData = [];

    protected function setUp(): void
    {
        $kernel = new AppTestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        $this->formGeneratorService = $container->get('micayael.form_generator');

        $this->originalData = [
            'name' => 'Jhon Doe',
            'birthday' => (new \DateTime())->format('Y-m-d'),
        ];

        // Los campos vacíos no se comparan: según la versión de Symfony se agregan como null o no se agregan
        $this->expectedData = [
            'name' => 'Jhon Doe',
            'birthday' => (new \DateTime())->format('Y-m-d'),
        ];
    }

    public function testServiceWiring()
    {
        $this->assertInstanceOf(FormGenerator::class, $this->formGeneratorService);
    }

    public function testFormFromArray()
    {
        $formConfigArray = $this->getFormConfigAsArray();

        $form = $this->formGeneratorService->createForm($formConfigArray);

        $form->submit(['default_default' => $this->originalData]);

        $this->assertInstanceOf(FormInterface::class, $form);

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($this->expectedData, array_filter($form->getData(), static function ($value) { return null !== $value; }));
    }

    public function testFormFromJson()
    {
        $formConfigAsJson = $this->getFormConfigAsJson();

        $form = $this->formGeneratorService->createFormFromJson($formConfigAsJson);

        $form->submit(['default_default' => $this->originalData]);

        $this->assertInstanceOf(FormInterface::class, $form);

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($this->expectedData, array_filter($form->getData(), static function ($value) { return null !== $value; }));
    }

    public function testFormFromYaml()
    {
        $formConfigAsYaml = $this->getFormConfigAsYaml();

        $form = $this->formGeneratorService->createFormFromYaml($formConfigAsYaml);

        $form->submit(['default_default' => $this->originalData]);

        $this->assertInstanceOf(FormInterface::class, $form);

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($this->expectedData, array_filter($form->getData(), static function ($value) { return null !== $value; }));
    }

    public function testSubmittedDataOfGroupedFields()
    {
        $form = $this->formGeneratorService->createForm(['name' => null, 'city' => ['extras' => ['group' => 'Address']]]);

        $form->submit(['default_default' => ['name' => 'Jhon'], 'address_default' => ['city' => 'Asuncion']]);

        $this->assertTrue($form->isValid());
        $this->assertEquals(['name' => 'Jhon', 'city' => 'Asuncion'], $form->getData());
    }

    public function testFormWithBaseFormClass()
    {
        $formConfigArray = $this->getFormConfigAsArray();

        $fixedFields = [
            'fixed1',
            'fixed2',
        ];

        $form = $this->formGeneratorService->createForm($formConfigArray, [], null, FixedFieldsType::class);

        $form->submit($this->originalData);

        $this->assertInstanceOf(FormInterface::class, $form);

        foreach ($formConfigArray as $typeName => $type) {
            $this->assertTrue($form->get('default_default')->has($typeName));
        }

        foreach ($fixedFields as $fieldName) {
            $this->assertTrue($form->has($fieldName));
        }
    }

    public function testFormWithBaseFormClassAndGroupName()
    {
        $formConfigArray = $this->getFormConfigAsArray();

        $fixedFields = [
            'fixed1',
            'fixed2',
        ];

        $form = $this->formGeneratorService->createForm($formConfigArray, [], null, FixedFieldsType::class, 'extra_fields');

        $form->submit($this->originalData);

        $this->assertInstanceOf(FormInterface::class, $form);

        $this->assertTrue($form->has('extra_fields'));

        foreach ($formConfigArray as $typeName => $type) {
            $this->assertTrue($form->get('extra_fields')->has($typeName));
        }

        foreach ($fixedFields as $fieldName) {
            $this->assertTrue($form->has($fieldName));
        }
    }

    private function getFormConfigAsYaml(): string
    {
        $ret = 'name:
birthday:
  type: date
  options:
    label: Your Birthday
status:
  type: choice
  options:
    choices:
      Active: A
      Inactive: I
custom_type:
  type: Micayael\Bundle\FormGeneratorBundle\Tests\Form\Type\DemoFormType
  options:
    custom_option: value';

        return $ret;
    }

    private function getFormConfigAsArray(): array
    {
        $ret = Yaml::parse($this->getFormConfigAsYaml());

        return $ret;
    }

    private function getFormConfigAsJson(): string
    {
        $ret = json_encode($this->getFormConfigAsArray());

        return $ret;
    }
}
