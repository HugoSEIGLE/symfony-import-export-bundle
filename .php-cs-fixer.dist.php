<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in(__DIR__ . '/src')
    ->exclude([
        'config/secrets',
        'node_modules',
        'var',
        'docker-data',
    ])
;

return (new Config())
    ->setRules([
        '@Symfony' => true,
        'blank_line_before_statement' => [
            'statements' => [
                // 'break',
                // 'continue',
                'declare',
                'return',
                'throw',
                'try',
            ],
        ],
        'class_definition' => [
            'multi_line_extends_each_single_line' => true,
            'single_item_single_line' => true,
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'native_constant_invocation' => [
            'include' => [
                '@all',
            ],
        ],
        'native_function_invocation' => [
            'exclude' => [
                'time',
                'sleep',
            ],
            'include' => [
                '@all',
            ],
        ],
        'operator_linebreak' => [
            'only_booleans' => true,
            'position' => 'end',
        ],
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'phpdoc_align' => [
            'align' => 'left',
        ],
        'phpdoc_to_comment' => [
            'ignored_tags' => ['var'],
        ],
        'single_line_throw' => false,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
;
