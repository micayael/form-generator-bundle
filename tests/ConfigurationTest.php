<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests;

use Micayael\Bundle\FormGeneratorBundle\DependencyInjection\Configuration;
use Micayael\Bundle\FormGeneratorBundle\DependencyInjection\MicayaelFormGeneratorExtension;
use Micayael\Bundle\FormGeneratorBundle\Service\ConstraintFactory;
use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        $this->assertEquals([
            'types' => [],
            'allowed_classes' => [],
            'untrusted' => false,
            'allowed_options' => [],
            'allowed_constraints' => [],
        ], $config);
    }

    public function testCustomTypes(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'types' => [
                'boolean_choice' => ['default_options' => ['choices' => ['Yes' => true, 'No' => false]]],
                'my_type' => ['class' => 'App\Form\MyType'],
            ],
            'allowed_classes' => ['App\Form\MyType'],
        ]]);

        $this->assertEquals([
            'boolean_choice' => ['default_options' => ['choices' => ['Yes' => true, 'No' => false]], 'class' => null],
            'my_type' => ['class' => 'App\Form\MyType', 'default_options' => []],
        ], $config['types']);
        $this->assertEquals(['App\Form\MyType'], $config['allowed_classes']);
    }

    public function testUntrustedModeIsWired(): void
    {
        $container = new ContainerBuilder();
        $container->register('form.factory', \stdClass::class);

        (new MicayaelFormGeneratorExtension())->load([[
            'untrusted' => true,
            'allowed_options' => ['label_html'],
            'allowed_constraints' => ['expression'],
        ]], $container);

        $formGenerator = $container->getDefinition(FormGenerator::class);
        $constraintFactory = $container->getDefinition(ConstraintFactory::class);

        $this->assertTrue($formGenerator->getArgument('$untrusted'));
        $this->assertSame(['label_html'], $formGenerator->getArgument('$allowedOptions'));
        $this->assertTrue($constraintFactory->getArgument('$untrusted'));
        $this->assertSame(['expression'], $constraintFactory->getArgument('$allowedConstraints'));
    }
}
