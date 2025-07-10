<?php


declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl\Command;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Install;
use Symfony\Component\Uid\Uuid;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;

/**
 * @internal
 */
#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
        ApplicationStatus   $applicationStatus,
        PortalLicenseFamily $portalLicenseFamily,
        ?int                $portalUsersCount,
        ?Uuid               $contactPersonId,
        ?Uuid               $bitrix24PartnerContactPersonId,
        ?Uuid               $bitrix24PartnerId,
        ?string             $externalId,
        ?string             $comment,
        int                 $bitrix24UserId,
        bool                $isBitrix24UserAdmin,
        string              $memberId,
        Domain              $domain,
        AuthToken           $authToken,
        int                 $applicationVersion,
        Scope               $applicationScope,
        ?string             $applicationToken,
        ?string             $expectedException,

    ): void
    {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        new Install\Command(
            $applicationStatus,
            $portalLicenseFamily,
            $portalUsersCount,
            $contactPersonId,
            $bitrix24PartnerContactPersonId,
            $bitrix24PartnerId,
            $externalId,
            $comment,
            $bitrix24UserId,
            $isBitrix24UserAdmin,
            $memberId,
            $domain,
            $authToken,
            $applicationVersion,
            $applicationScope,
            $applicationToken,
        );
    }

    public static function dataForCommand(): \Generator
    {
        $applicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->build();

        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->build();


        yield '' => [
            $applicationInstallationBuilder->getApplicationStatus(),
            $applicationInstallationBuilder->getPortalLicenseFamily(),
            $applicationInstallationBuilder->getPortalUsersCount(),
            $applicationInstallationBuilder->getContactPersonId(),
            $applicationInstallationBuilder->getBitrix24PartnerContactPersonId(),
            $applicationInstallationBuilder->getBitrix24PartnerId(),
            $applicationInstallationBuilder->getExternalId(),
            $applicationInstallationBuilder->getComment(),
            $applicationInstallationBuilder->getBitrix24AccountId(),
            $bitrix24AccountBuilder->getBitrix24UserId(),
            $bitrix24AccountBuilder->isBitrix24UserAdmin(),
            $bitrix24AccountBuilder->getMemberId(),
            $bitrix24AccountBuilder->getDomainUrl(),
            $bitrix24AccountBuilder->getAuthToken(),
            $bitrix24AccountBuilder->getApplicationVersion(),
            $bitrix24AccountBuilder->getApplicationScope(),
        ];

    }
}