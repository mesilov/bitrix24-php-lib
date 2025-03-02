<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\ValueObjects;

readonly class Domain
{
    public string $value;

    public function __construct(string $domain)
    {
        $this->validate($domain);
        $this->value = $domain;
    }

    private function validate(string $domain): void
    {
        // Регулярное выражение для проверки допустимых символов (латиница и кириллица)
        $patternValidChars = '/^((?!-)[A-Za-zА-Яа-яЁё0-9-]{1,63}(?<!-)\.)+[A-Za-zА-Яа-яЁё]{2,6}$/u';

        // Проверка общей длины (1-253 символа)
        $patternLengthCheck = '/^.{1,253}$/';

        // Проверка длины каждой метки (1-63 символа, включая кириллицу)
        $patternLengthEachLabel = '/^[A-Za-zА-Яа-яЁё0-9-]{1,63}(\.[A-Za-zА-Яа-яЁё0-9-]{1,63}){0,2}$/u';
        if (
            in_array(preg_match($patternValidChars, $domain), [0, false], true)
            || in_array(preg_match($patternLengthCheck, $domain), [0, false], true)
            || in_array(preg_match($patternLengthEachLabel, $domain), [0, false], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid domain: %s', $domain));
        }
    }
}
