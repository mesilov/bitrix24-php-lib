<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Command;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
        string  $memberId,
        Domain  $domain,
        string  $applicationToken,
        ApplicationStatus  $applicationStatus,
        ?string $expectedException,
    ): void
    {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        $command = new Command(
            $memberId,
            $domain,
            $applicationToken,
            $applicationStatus
        );

        if (null === $expectedException) {
            $this->assertInstanceOf(Command::class, $command);
        }
    }

    public static function dataForCommand(): \Generator
    {
        $applicationToken = Uuid::v7()->toRfc4122();
        $applicationStatus = new ApplicationStatus('T');

        (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withApplicationToken($applicationToken)
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->build();

        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withInstalled()
            ->withApplicationToken($applicationToken)
            ->withStatus(Bitrix24AccountStatus::active)
            ->build();

        // Valid case
        yield 'validCommand' => [
            $bitrix24AccountBuilder->getMemberId(),
            new Domain($bitrix24AccountBuilder->getDomainUrl()),
            $applicationToken,
            $applicationStatus,
            null,
        ];

        // Empty memberId
        yield 'emptyMemberId' => [
            '',
            new Domain($bitrix24AccountBuilder->getDomainUrl()),
            $applicationToken,
            $applicationStatus,
            InvalidArgumentException::class,
        ];

        // Empty applicationToken
        yield 'emptyApplicationToken' => [
            $bitrix24AccountBuilder->getMemberId(),
            new Domain($bitrix24AccountBuilder->getDomainUrl()),
            '',
            $applicationStatus,
            InvalidArgumentException::class,
        ];
    }
}
