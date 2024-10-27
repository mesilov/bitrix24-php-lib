<?php
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src');

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        '@DoctrineAnnotation' => true,
        '@PhpCsFixer' => true,
    ]);