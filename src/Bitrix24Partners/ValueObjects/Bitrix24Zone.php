<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\ValueObjects;

enum Bitrix24Zone: string
{
    case RU = 'ru';
    case KZ = 'kz';

    public function getDomain(): string
    {
        return match ($this) {
            self::RU => 'https://www.bitrix24.ru',
            self::KZ => 'https://www.bitrix24.kz',
        };
    }

    public function getPartnerListUrl(): string
    {
        return match ($this) {
            self::RU => 'https://www.bitrix24.ru/partners/country__19/',
            self::KZ => 'https://www.bitrix24.kz/partners/country__22/',
        };
    }
}
