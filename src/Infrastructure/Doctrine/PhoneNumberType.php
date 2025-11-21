<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberType extends Type
{
    public const NAME = 'phone_number';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * @param mixed $value
     *
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof PhoneNumber) {
            throw new \InvalidArgumentException('Expected \libphonenumber\PhoneNumber, got '.get_debug_type($value));
        }

        $phoneUtil = PhoneNumberUtil::getInstance();

        return $phoneUtil->format($value, PhoneNumberFormat::E164);
    }

    /**
     * @param mixed $value
     *
     * @throws NumberParseException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?PhoneNumber
    {
        if (null === $value || $value instanceof PhoneNumber) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('Expected string, got '.get_debug_type($value));
        }

        $phoneUtil = PhoneNumberUtil::getInstance();

        return $phoneUtil->parse($value, 'ZZ');
    }

    #[\Override]
    public function getName(): string
    {
        return self::NAME;
    }

    #[\Override]
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
