<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish;

readonly class Command
{
    public function __construct(
        public string $applicationToken,
        public string $memberId,
        public string $domainUrl,
        public int $bitrix24UserId,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === $this->applicationToken || '0' === $this->applicationToken) {
            throw new \InvalidArgumentException('Application token cannot be empty.');
        }

        if ('' === $this->memberId || '0' === $this->memberId) {
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
        $patternValidChars = '/^((?!-)[A-Za-zА-Яа-яЁё0-9-]{1,63}(?<!-)\\.)+[A-Za-zА-Яа-яЁё]{2,6}$/u';

        // Проверка общей длины (1-253 символа)
        $patternLengthCheck = '/^.{1,253}$/';

        // Проверка длины каждой метки (1-63 символа, включая кириллицу)
        $patternLengthEachLabel = '/^[A-Za-zА-Яа-яЁё0-9-]{1,63}(\\.[A-Za-zА-Яа-яЁё0-9-]{1,63})*$/u';
        if (
            in_array(preg_match($patternValidChars, $domain), [0, false], true)
            || in_array(preg_match($patternLengthCheck, $domain), [0, false], true)
            || in_array(preg_match($patternLengthEachLabel, $domain), [0, false], true)) {
            throw new \InvalidArgumentException('Domain URL is not valid.');
        }
    }
}
