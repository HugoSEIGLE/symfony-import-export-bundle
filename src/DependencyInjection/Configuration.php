<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('symfony_import_export');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('date_format')
                ->defaultValue('Y-m-d H:i:s')
                ->info('Date format used for export.')
            ->end()
            ->scalarNode('bool_true')
                ->defaultValue('true')
                ->info('Value used for true.')
            ->end()
            ->scalarNode('bool_false')
                ->defaultValue('false')
                ->info('Value used for false.')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
