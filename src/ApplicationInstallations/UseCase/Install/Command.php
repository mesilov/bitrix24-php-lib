<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Установка может происходить по 2 сценриям.
 * Если в эту команду передается $applicationToken значит это установка без UI.
 * Иначе это установка с UI и $applicationToken передаваться не должен.
 *
 * 1) UI - Пользователь запускает установку через интерфейс.
 * Bitrix24 отправляет первичный запрос на /install.php с параметрами:
 * AUTH_ID, REFRESH_ID, member_id в теле запроса. Без application_token
 * Система создаёт Bitrix24Account в статусе new.
 * Bitrix24 отправляет событие ONAPPINSTALL на /event-handler.php и передает application_token
 * Вызывается applicationInstalled($applicationToken) с полученным токеном.
 * 2) Без UI - Установка инициируется прямым POST-запросом с полным набором credentials.
 * Сразу создается Bitrix24Account. У установщика и у аккаунта вызывается метод с переданыым токеном:
 * $bitrix24Account->applicationInstalled($applicationToken);
 * $applicationInstallation->applicationInstalled($applicationToken);.
 */
readonly class Command
{
    public function __construct(
        public ApplicationStatus $applicationStatus,
        public PortalLicenseFamily $portalLicenseFamily,
        public ?int $portalUsersCount,
        public ?Uuid $contactPersonId,
        public ?Uuid $bitrix24PartnerContactPersonId,
        public ?Uuid $bitrix24PartnerId,
        public ?string $externalId,
        public ?string $comment,
        public int $bitrix24UserId,
        public bool $isBitrix24UserAdmin,
        public string $memberId,
        public Domain $domain,
        public AuthToken $authToken,
        public int $applicationVersion,
        public Scope $applicationScope,
        public ?string $applicationToken = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->portalUsersCount <= 0) {
            throw new InvalidArgumentException('Portal Users count must be a positive integer.');
        }

        if ('' === $this->externalId) {
            throw new InvalidArgumentException('External ID must be a non-empty string.');
        }

        if ('' === $this->comment) {
            throw new InvalidArgumentException('Comment must be a non-empty string.');
        }

        if ($this->bitrix24UserId <= 0) {
            throw new InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }

        if ('' === $this->memberId) {
            throw new InvalidArgumentException('Member ID must be a non-empty string.');
        }

        if ($this->applicationVersion <= 0) {
            throw new InvalidArgumentException('Application version must be a positive integer.');
        }

        if (null !== $this->applicationToken && '' === trim($this->applicationToken)) {
            throw new InvalidArgumentException('Application token must be a non-empty string.');
        }
    }
}
