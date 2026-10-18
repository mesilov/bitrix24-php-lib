<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall;

use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

readonly class Command
{
    public function __construct(
        public Domain $domainUrl,
        public string $memberId,
        #[\SensitiveParameter]
        public string $applicationToken,
        public bool $clean,
    ) {
        if ('' === trim($applicationToken) || '' === trim($memberId)) {
            throw new InvalidArgumentException('Uninstall requires a non-empty application token and member ID.');
        }
    }

    /** Parse the POST event without coercing missing/ambiguous CLEAN values. */
    public static function fromEvent(#[\SensitiveParameter] array $event): self
    {
        $auth = $event['auth'] ?? null;
        $data = $event['data'] ?? null;
        if ('ONAPPUNINSTALL' !== ($event['event'] ?? null) || !is_array($auth) || !is_array($data)) {
            throw new InvalidArgumentException('Invalid uninstall event.');
        }

        $clean = $data['CLEAN'] ?? null;
        if (!in_array($clean, [0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('CLEAN must be explicitly 0 or 1.');
        }

        foreach (['domain', 'member_id', 'application_token'] as $key) {
            if (!is_string($auth[$key] ?? null) || '' === trim($auth[$key])) {
                throw new InvalidArgumentException('Invalid uninstall auth fields.');
            }
        }

        return new self(new Domain($auth['domain']), $auth['member_id'], $auth['application_token'], in_array($clean, [1, '1'], true));
    }
}
