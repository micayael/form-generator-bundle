<?php

namespace Micayael\Bundle\FormGeneratorBundle\DependencyInjection;

use Micayael\Bundle\FormGeneratorBundle\Service\ConstraintFactory;
use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MicayaelFormGeneratorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('micayael_form_generator_bundle_config', $config);

        $container->getDefinition(FormGenerator::class)
            ->setArgument('$types', $config['types'])
            ->setArgument('$allowedClasses', $config['allowed_classes'])
            ->setArgument('$untrusted', $config['untrusted'])
            ->setArgument('$allowedOptions', $config['allowed_options'])
        ;

        $container->getDefinition(ConstraintFactory::class)
            ->setArgument('$untrusted', $config['untrusted'])
            ->setArgument('$allowedConstraints', $config['allowed_constraints'])
        ;
    }
}
