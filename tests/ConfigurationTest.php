<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests;

use Micayael\Bundle\FormGeneratorBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        $this->assertEquals(['types' => [], 'allowed_classes' => []], $config);
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
}
