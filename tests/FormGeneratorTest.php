<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests;

use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
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

        $this->expectedData = [
            'name' => 'Jhon Doe',
            'birthday' => (new \DateTime())->format('Y-m-d'),
            'status' => null,
            'custom_type' => null,
        ];
    }

    public function testServiceWiring()
    {
        $this->assertInstanceOf(FormGenerator::class, $this->formGeneratorService);
    }

    public function testFormFromArray()
    {
        $formConfigArray = $this->getFormConfigAsArray();

        $form = $this->formGeneratorService->createForm($formConfigArray, [], $this->originalData);

        $form->submit($this->originalData);

        $this->assertInstanceOf(FormInterface::class, $form);

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($this->expectedData, $form->getData());
    }

    public function testFormFromJson()
    {
        $formConfigAsJson = $this->getFormConfigAsJson();

        $form = $this->formGeneratorService->createFormFromJson($formConfigAsJson, [], $this->originalData);

        $form->submit($this->originalData);

        $this->assertInstanceOf(FormInterface::class, $form);

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($this->expectedData, $form->getData());
    }

    public function testFormFromYaml()
    {
        $formConfigAsYaml = $this->getFormConfigAsYaml();

        $form = $this->formGeneratorService->createFormFromYaml($formConfigAsYaml, [], $this->originalData);

        $form->submit($this->originalData);

        $this->assertInstanceOf(FormInterface::class, $form);

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($this->expectedData, $form->getData());
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
  type: Micayael\Bundle\FormGeneratorBundle\Tests\DemoFormType
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
