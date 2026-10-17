<?php

namespace Micayael\Bundle\FormGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('micayael_form_generator');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('types')
                    ->info('Custom type aliases, or overrides of the built-in ones (text, date, boolean_choice, ...)')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')
                                ->info('FQCN of the form type. Optional when overriding a built-in alias')
                                ->defaultNull()
                            ->end()
                            ->variableNode('default_options')
                                ->info('Default options, merged over the built-in ones')
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('allowed_classes')
                    ->info('If not empty, only these form type classes (FQCN) can be used in the "type" key, besides the aliases')
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
