<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Tests;

use Bitrix24\SDK\Core\Exceptions\WrongConfigurationException;
use Carbon\Doctrine\CarbonImmutableType;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\Types\Type;

class EntityManagerFactory
{
    /**
     * @throws OptimisticLockException
     * @throws WrongConfigurationException
     * @throws ORMException
     * @throws Exception
     */
    public static function get(): EntityManagerInterface
    {
        $paths = [
            dirname(__DIR__) . '/src/Bitrix24Accounts/Entity'
        ];
        $isDevMode = true;

        if (!array_key_exists('DATABASE_HOST', $_ENV)) {
            throw new WrongConfigurationException('DATABASE_HOST not defined in $_ENV');
        }

        if (!array_key_exists('DATABASE_USER', $_ENV)) {
            throw new WrongConfigurationException('DATABASE_USER not defined in $_ENV');
        }

        if (!array_key_exists('DATABASE_PASSWORD', $_ENV)) {
            throw new WrongConfigurationException('DATABASE_PASSWORD not defined in $_ENV');
        }

        if (!array_key_exists('DATABASE_NAME', $_ENV)) {
            throw new WrongConfigurationException('DATABASE_NAME not defined in $_ENV');
        }

        $connectionParams = [
            'driver' => 'pdo_pgsql',
            'host' => $_ENV['DATABASE_HOST'],
            'user' => $_ENV['DATABASE_USER'],
            'password' => $_ENV['DATABASE_PASSWORD'],
            'dbname' => $_ENV['DATABASE_NAME'],
        ];
        if (!Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }

        if (!Type::hasType('carbon_immutable')) {
            Type::addType('carbon_immutable', CarbonImmutableType::class);
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);
        $connection = DriverManager::getConnection($connectionParams, $configuration);
        $entityManager = new EntityManager($connection, $configuration);
        // todo разобраться, почему так, без этого объекты оставались в кеше и при find мы получали старые значения
        $entityManager->clear();
        $entityManager->flush();
        return $entityManager;
    }
}