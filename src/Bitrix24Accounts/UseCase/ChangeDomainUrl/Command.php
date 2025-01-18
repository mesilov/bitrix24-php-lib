<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl;

readonly class Command
{
    public function __construct(
        /**
         * @var non-empty-string $oldDomain
         */
        public string $oldDomain,
        /**
         * @var non-empty-string $newDomain
         */
        public string $newDomain
    ) {
        $this->validateDomain($oldDomain, 'oldDomainUrlHost');
        $this->validateDomain($newDomain, 'newDomainUrlHost');
    }

    private function validateDomain(string $domain, string $parameterName): void
    {
        // Регулярное выражение для проверки допустимых символов (латиница и кириллица)
        $patternValidChars = "/^((?!-)[A-Za-zА-Яа-яЁё0-9-]{1,63}(?<!-)\\.)+[A-Za-zА-Яа-яЁё]{2,6}$/u";

        // Проверка общей длины (1-253 символа)
        $patternLengthCheck = "/^.{1,253}$/";

        // Проверка длины каждой метки (1-63 символа, включая кириллицу)
        $patternLengthEachLabel = "/^[A-Za-zА-Яа-яЁё0-9-]{1,63}(\.[A-Za-zА-Яа-яЁё0-9-]{1,63})*$/u";
        if (
            in_array(preg_match($patternValidChars, $domain), [0, false], true) ||
            in_array(preg_match($patternLengthCheck, $domain), [0, false], true) ||
            in_array(preg_match($patternLengthEachLabel, $domain), [0, false], true)) {

            throw new \InvalidArgumentException(sprintf('Invalid value for %s: %s', $parameterName, $domain));
        }
    }
}
