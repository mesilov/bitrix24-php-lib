<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\ValueObjects;

enum Bitrix24Zone: string
{
    case RU = 'ru';
    case KZ = 'kz';

    public function getBaseDomain(): string
    {
        return match ($this) {
            self::RU => 'https://www.bitrix24.ru/country__19',
            self::KZ => 'https://www.bitrix24.kz/country_22',
        };
    }
}
