<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\OnAppInstall;


use Bitrix24\Lib\Bitrix24Accounts;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\ApplicationInstallations;
use Bitrix24\Lib\Tests\EntityManagerFactory;

use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Handler;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ApplicationInstallations\UseCase\OnAppInstall\Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $handler;

    private Flusher $flusher;

    private ApplicationInstallationRepository $applicationInstallationRepository;

    private Bitrix24AccountRepository $bitrix24accountRepository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->applicationInstallationRepository = new ApplicationInstallationRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->bitrix24accountRepository = new Bitrix24AccountRepository($entityManager);
        $this->handler = new Handler(
            $this->bitrix24accountRepository,
            $this->applicationInstallationRepository,
            $this->flusher,
            new NullLogger()
        );

    }

    /**
     * @throws InvalidArgumentException|Bitrix24AccountNotFoundException|ApplicationInstallationNotFoundException
     */
    #[Test]
    public function testEventOnAppInstall(): void
    {
        $memberId = Uuid::v4()->toRfc4122();
        $domainUrl = Uuid::v4()->toRfc4122().'-example.com';
        $applicationToken = Uuid::v7()->toRfc4122();
        $applicationStatus = new ApplicationStatus('T');

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withMemberId($memberId)
            ->withDomainUrl($domainUrl)
            ->withInstalled()
            ->withMaster(true)
            ->build();

        $applicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($bitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->build();

        $this->bitrix24accountRepository->save($bitrix24Account);
        $this->applicationInstallationRepository->save($applicationInstallationBuilder);
        $this->flusher->flush();

        $this->handler->handle(
            new ApplicationInstallations\UseCase\OnAppInstall\Command(
                $memberId,
                new Domain($domainUrl),
                $applicationToken,
                $applicationStatus
            )
        );

        $updated = $this->bitrix24accountRepository->getById($bitrix24Account->getId());

        $this->assertTrue(
            $updated->isApplicationTokenValid($applicationToken),
            sprintf(
                'failed application token «%s» validation for bitrix24 account with id «%s»',
                $applicationToken,
                $bitrix24Account->getId()->toString()
            )
        );
    }
}
