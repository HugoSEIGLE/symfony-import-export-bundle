<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function strlen;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('import_export');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('date_format')->defaultValue('Y-m-d H:i:s')->end()
                ->scalarNode('bool_true')->defaultValue('true')->end()
                ->scalarNode('bool_false')->defaultValue('false')->end()
                ->booleanNode('validate_headers')->defaultTrue()->end()
                ->arrayNode('csv')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('delimiter')->defaultValue(',')->cannotBeEmpty()
                            ->validate()->ifTrue(static fn (string $value): bool => 1 !== strlen($value))->thenInvalid('CSV delimiter must be exactly one character.')->end()
                        ->end()
                        ->scalarNode('enclosure')->defaultValue('"')->cannotBeEmpty()
                            ->validate()->ifTrue(static fn (string $value): bool => 1 !== strlen($value))->thenInvalid('CSV enclosure must be exactly one character.')->end()
                        ->end()
                        ->scalarNode('escape')->defaultValue('\\')
                            ->validate()->ifTrue(static fn (string $value): bool => 1 < strlen($value))->thenInvalid('CSV escape must contain at most one character.')->end()
                        ->end()
                        ->booleanNode('bom')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('importers')
                    ->useAttributeAsKey('class')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('fields')
                                ->scalarPrototype()->end()
                            ->end()
                            ->booleanNode('allow_delete')->defaultFalse()->end()
                            ->booleanNode('validate_headers')->defaultNull()->end()
                            ->arrayNode('unique_fields')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
