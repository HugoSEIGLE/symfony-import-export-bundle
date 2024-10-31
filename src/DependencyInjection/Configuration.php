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
        $treeBuilder = new TreeBuilder('import_export');
        $rootNode = $treeBuilder->getRootNode();

        if (method_exists($rootNode, 'children')) {
            $rootNode
            ->children()
                ->scalarNode('date_format')->defaultValue('Y-m-d H:i:s')->end()
                ->scalarNode('bool_true')->defaultValue('true')->end()
                ->scalarNode('bool_false')->defaultValue('false')->end()
                ->arrayNode('importers')
                    ->useAttributeAsKey('class')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('fields')
                                ->scalarPrototype()->end()
                            ->end()
                            ->booleanNode('allow_delete')->defaultFalse()->end()
                            ->arrayNode('unique_fields')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        }

        return $treeBuilder;
    }
}
