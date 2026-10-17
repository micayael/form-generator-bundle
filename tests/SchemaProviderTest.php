<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests;

use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
use Micayael\Bundle\FormGeneratorBundle\Service\SchemaProvider;
use Micayael\Bundle\FormGeneratorBundle\Tests\Form\Type\DemoFormType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRegistryInterface;

final class SchemaProviderTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AppTestKernel::class;
    }

    public function testTrustedSchema(): void
    {
        $schema = $this->provider()->getSchema(new FormGenerator($this->formFactory()));

        $this->assertFalse($schema['untrusted']);
        $this->assertSame(TextType::class, $schema['types']['text']['class']);
        $this->assertContains('bool', $schema['types']['text']['options']['required']['types']);
        $this->assertTrue($schema['types']['text']['options']['required']['default']);
        $this->assertArrayHasKey('property_path', $schema['types']['text']['options']);
        $this->assertArrayNotHasKey('is_empty_callback', $schema['types']['text']['options']);
        $this->assertArrayNotHasKey('constraints', $schema['types']['text']['options']);
        $this->assertArrayHasKey('max', $schema['constraints']['length']['options']);
        $this->assertArrayNotHasKey('normalizer', $schema['constraints']['length']['options']);
        $this->assertArrayNotHasKey('groups', $schema['constraints']['length']['options']);
    }

    public function testUntrustedSchema(): void
    {
        $generator = new FormGenerator($this->formFactory(), allowedClasses: [DemoFormType::class], untrusted: true, allowedOptions: ['label_html']);
        $schema = $this->provider()->getSchema($generator);

        $this->assertTrue($schema['untrusted']);
        $this->assertArrayNotHasKey('property_path', $schema['types']['text']['options']);
        $this->assertArrayHasKey('label_html', $schema['types']['text']['options']);
        $this->assertArrayHasKey(DemoFormType::class, $schema['types']);
        $this->assertArrayNotHasKey('expression', $schema['constraints']);
        $this->assertArrayNotHasKey('propertyPath', $schema['constraints']['equal_to']['options']);
    }

    public function testSchemaIsJsonSerializable(): void
    {
        $this->assertJson(json_encode($this->provider()->getSchema(new FormGenerator($this->formFactory())), \JSON_THROW_ON_ERROR));
    }

    public function testServiceIsRegistered(): void
    {
        self::bootKernel();

        $this->assertInstanceOf(SchemaProvider::class, static::getContainer()->get(SchemaProvider::class));
    }

    private function provider(): SchemaProvider
    {
        self::bootKernel();

        /** @var FormRegistryInterface $registry */
        $registry = static::getContainer()->get('form.registry');

        return new SchemaProvider($registry);
    }

    private function formFactory(): FormFactoryInterface
    {
        /** @var FormFactoryInterface $factory */
        $factory = static::getContainer()->get('form.factory');

        return $factory;
    }
}
