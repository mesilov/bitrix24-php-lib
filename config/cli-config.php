<?php

require 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;

$config = new PhpFile('migrations.php');

$conn = DriverManager::getConnection(
    [
        'driver' => 'pdo_pgsql',
        'memory' => true,
        'dbname' => 'b24AccountTest',
    ]);

return DependencyFactory::fromConnection($config, new ExistingConnection($conn));