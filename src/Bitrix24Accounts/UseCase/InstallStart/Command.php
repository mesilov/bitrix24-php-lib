<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $uuid,
        public int $bitrix24UserId,
        public bool $isBitrix24UserAdmin,
        public string $memberId,
        public string $domainUrl,
        public AuthToken $authToken,
        public int $applicationVersion,
        public Scope $applicationScope
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->uuid)) {
            throw new \InvalidArgumentException('Empty UUID provided.');
        }

        if ($this->bitrix24UserId <= 0) {
            throw new \InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }

        if (!is_string($this->memberId) || ($this->memberId === '' || $this->memberId === '0')) {
            throw new \InvalidArgumentException('Member ID must be a non-empty string.');
        }

        $this->validateDomain($this->domainUrl);

        if ($this->applicationVersion <= 0) {
            throw new \InvalidArgumentException('Application version must be a positive integer.');
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
            in_array(preg_match($patternValidChars, $domain), [0, false], true) ||
            in_array(preg_match($patternLengthCheck, $domain), [0, false], true) ||
            in_array(preg_match($patternLengthEachLabel, $domain), [0, false], true)) {

            throw new \InvalidArgumentException('Domain URL is not valid.');
        }
    }
}
