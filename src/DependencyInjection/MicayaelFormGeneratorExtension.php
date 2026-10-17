<?php

namespace Micayael\Bundle\FormGeneratorBundle\DependencyInjection;

use Micayael\Bundle\FormGeneratorBundle\Service\ConstraintFactory;
use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MicayaelFormGeneratorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        // Los comandos solo se registran si la app usa symfony/console
        if (class_exists(Application::class)) {
            $loader->load('commands.yaml');
        }

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('micayael_form_generator_bundle_config', $config);

        $container->getDefinition(FormGenerator::class)
            ->setArgument('$types', $config['types'])
            ->setArgument('$allowedClasses', $config['allowed_classes'])
            ->setArgument('$untrusted', $config['untrusted'])
            ->setArgument('$allowedOptions', $config['allowed_options'])
            // Las constraints permitidas van en el ConstraintFactory. Se define igual porque
            // Symfony DI < 5.4.x no acepta saltear argumentos intermedios
            ->setArgument('$allowedConstraints', [])
            ->setArgument('$allowedHtmlAttributes', $config['allowed_html_attributes'])
        ;

        $container->getDefinition(ConstraintFactory::class)
            ->setArgument('$untrusted', $config['untrusted'])
            ->setArgument('$allowedConstraints', $config['allowed_constraints'])
        ;
    }
}
