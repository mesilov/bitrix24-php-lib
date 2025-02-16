<?php

declare(strict_types=1);
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in(__DIR__.'/src');

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        '@DoctrineAnnotation' => true,
        '@PhpCsFixer' => true,
        'phpdoc_to_comment' => false,
    ]);
