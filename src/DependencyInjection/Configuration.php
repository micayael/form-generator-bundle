<?php

namespace Micayael\Bundle\FormGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('micayael_form_generator');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
//                ->scalarNode('dncp_admin_login')
//                    ->defaultValue('login')
//                    ->info('name of the form login route')
//                ->end()
//                ->scalarNode('dncp_admin_login_check')
//                    ->defaultValue('login_check')
//                    ->info('name of the form login_check route')
//                ->end()
//                ->scalarNode('dncp_admin_password_reset')
//                    ->defaultNull()
//                    ->info('name of the forgot-password form route')
//                ->end()
//                ->scalarNode('dncp_admin_profile')
//                    ->defaultValue('profile')
//                    ->info('name of the route to the users profile')
//                ->end()
//                ->scalarNode('dncp_admin_logout')
//                    ->defaultValue('logout')
//                    ->info('name of the form login route')
//                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
