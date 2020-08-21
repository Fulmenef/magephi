<?php

return (new PhpCsFixer\Config())
    ->setRules(
        [
            '@Symfony'                               => true,
            '@PhpCsFixer'                            => true,
            '@PhpCsFixer:risky'                      => true,
            'yoda_style'                             => false,
            'no_superfluous_phpdoc_tags'             => false,
            'array_syntax'                           => ['syntax' => 'short'],
            'braces'                                 => ['allow_single_line_closure' => true],
            'binary_operator_spaces'                 => ['operators' => ['=>' => 'align_single_space']],
            'class_attributes_separation'            => true,
            'concat_space'                           => ['spacing' => 'one'],
            'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        ]
    )
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__ . '/src'])
            ->append([__FILE__])
    )
    ->setCacheFile(__DIR__ . '/var/.php_cs.cache');
