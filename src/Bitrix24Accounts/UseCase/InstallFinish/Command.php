<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish;

readonly class Command
{
    public function __construct(
        public string $applicationToken,
        public string $memberId,
        public string $domainUrl,
        public int    $bitrix24UserId,
    )
    {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->applicationToken)) {
            throw new \InvalidArgumentException('Application token cannot be empty.');
        }
        if (empty($this->memberId)) {
            throw new \InvalidArgumentException('Member ID cannot be empty.');
        }

        $this->validateDomain($this->domainUrl);

        if ($this->bitrix24UserId <= 0) {
            throw new \InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }
    }

    private function validateDomain(string $domain): void
    {
        // Регулярное выражение для проверки допустимых символов (латиница и кириллица)
        $patternValidChars = "/^((?!-)[A-Za-zА-Яа-яЁё0-9-]{1,63}(?<!-)\\.)+[A-Za-zА-Яа-яЁё]{2,6}$/u";

        // Проверка общей длины (1-253 символа)
        $patternLengthCheck = "/^.{1,253}$/";

        // Проверка длины каждой метки (1-63 символа, включая кириллицу)
        $patternLengthEachLabel = "/^[A-Za-zА-Яа-яЁё0-9-]{1,63}(\.[A-Za-zА-Яа-яЁё0-9-]{1,63})*$/u";
        if (
            !preg_match($patternValidChars, $domain) ||
            !preg_match($patternLengthCheck, $domain) ||
            !preg_match($patternLengthEachLabel, $domain)) {

            throw new \InvalidArgumentException('Domain URL is not valid.');
        }
    }
}
