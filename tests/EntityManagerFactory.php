<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests;

use Bitrix24\SDK\Core\Exceptions\WrongConfigurationException;
use Carbon\Doctrine\CarbonImmutableType;
use Darsyn\IP\Doctrine\MultiType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Misd\PhoneNumberBundle\Doctrine\DBAL\Types\PhoneNumberType;
use Symfony\Bridge\Doctrine\Types\UuidType;

class EntityManagerFactory
{
    /**
     * @throws OptimisticLockException
     * @throws WrongConfigurationException
     * @throws ORMException
     * @throws Exception
     */
    private static ?EntityManager $entityManager = null;

    public static function get(): EntityManagerInterface
    {
        if (!self::$entityManager instanceof EntityManager) {
            $paths = [
                dirname(__DIR__).'/config/xml',
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

            if (!Type::hasType('phone_number')) {
                Type::addType('phone_number', PhoneNumberType::class);
            }

            if (!Type::hasType('ip_address')) {
                Type::addType('ip_address', MultiType::class);
            }

            $configuration = ORMSetup::createXMLMetadataConfiguration($paths, $isDevMode);

            $connection = DriverManager::getConnection($connectionParams, $configuration);
            self::$entityManager = new EntityManager($connection, $configuration);
        }

        return self::$entityManager;
    }
}
