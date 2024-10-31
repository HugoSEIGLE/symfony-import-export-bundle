<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function method_exists;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('symfony_import_export');
        $rootNode = $treeBuilder->getRootNode();

        if (method_exists($rootNode, 'children')) {
            $rootNode
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
                    ->arrayNode('importers')
                        ->useAttributeAsKey('class')
                        ->arrayPrototype()
                            ->children()
                                ->arrayNode('fields')
                                    ->scalarPrototype()
                                        ->info('List of fields to import for the given class.')
                                    ->end()
                                ->end()
                                ->booleanNode('allow_delete')
                                    ->defaultFalse()
                                    ->info('Allows deletion of entities if set to true.')
                                ->end()
                                ->arrayNode('unique_fields')
                                    ->scalarPrototype()
                                        ->info('List of fields that uniquely identify an entity for updates.')
                                    ->end()
                                    ->defaultValue([])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();
        }

        return $treeBuilder;
    }
}
