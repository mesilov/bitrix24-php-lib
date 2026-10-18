<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\Uninstall;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Entity\Uninstallation;
use Bitrix24\Lib\ApplicationInstallations\Events\ApplicationUninstallationCompletedEvent;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\UninstallationStore;
use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\UninstallationContext;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall\Handler;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingStatus;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\Lib\Tests\Functional\ContactPersons\Builders\ContactPersonBuilder;
use Bitrix24\Lib\Tests\Helpers\ApplicationInstallations\RecordingApplicationDataCleaner;
use Bitrix24\Lib\Tests\Helpers\CollectingLogger;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

/** @internal */
#[CoversClass(Handler::class)]
#[CoversClass(UninstallationStore::class)]
class HandlerTest extends TestCase
{
    private EntityManagerInterface $em;

    private RecordingApplicationDataCleaner $cleaner;

    private CollectingLogger $logger;

    private EventDispatcher $dispatcher;

    private Handler $handler;

    protected function setUp(): void
    {
        $this->em = EntityManagerFactory::get();
        $this->em->clear();

        $this->cleaner = new RecordingApplicationDataCleaner();
        $this->logger = new CollectingLogger();
        $this->dispatcher = new EventDispatcher();
        $this->handler = new Handler(new UninstallationStore($this->em), $this->cleaner, $this->logger, $this->dispatcher);
    }

    public function testUninstallPhysicallyRemovesOAuthCredentialsAndPreservesCleanZeroSettings(): void
    {
        [$installation, $account, $command] = $this->installation(false);
        $setting = $this->setting($installation);
        $secondary = (new Bitrix24AccountBuilder())->withMemberId($account->getMemberId())->build();
        $this->em->persist($secondary);
        $this->em->flush();

        $this->handler->handle($command);
        $this->em->clear();
        self::assertNull($this->em->find(Bitrix24Account::class, $account->getId()));
        self::assertNull($this->em->find(Bitrix24Account::class, $secondary->getId()));
        self::assertNull($this->em->find(ApplicationInstallation::class, $installation->getId()));
        self::assertNotNull($this->em->find(ApplicationSettingsItem::class, $setting->getId()));
        self::assertCount(1, $this->cleaner->contexts);
        self::assertFalse($this->cleaner->contexts[0]->clean);
        self::assertSame($account->getMemberId(), $this->cleaner->contexts[0]->memberId);
        $row = $this->em->getConnection()->fetchAssociative('SELECT * FROM b24lib_uninstallations WHERE installation_id = ?', [$installation->getId()->toRfc4122()]);
        self::assertIsArray($row);
        self::assertNotNull($row['completed_at_utc']);
        self::assertStringNotContainsString($command->applicationToken, json_encode($row, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($account->getMemberId(), json_encode($row, JSON_THROW_ON_ERROR));
    }

    public function testCleanOneRemovesActiveAndDeletedSettingsJournalAndExclusiveContact(): void
    {
        $contact = (new ContactPersonBuilder())->withEmail('uninstall@example.com')->build();
        $this->em->persist($contact);
        [$installation, , $command] = $this->installation(true, null, $contact->getId());
        $active = $this->setting($installation);
        $deleted = $this->setting($installation, ApplicationSettingStatus::Deleted);
        $journalItem = new JournalItem($command->memberId, $installation->getId(), LogLevel::info, 'user details', 'test', new Context(\Darsyn\IP\Version\Multi::factory('127.0.0.1')));
        $this->em->persist($journalItem);
        [$other, $otherAccount] = $this->installation(true);
        $otherSetting = $this->setting($other);
        $this->em->flush();
        $this->handler->handle($command);
        $this->em->clear();
        foreach ([$active, $deleted] as $setting) {
            self::assertNull($this->em->find(ApplicationSettingsItem::class, $setting->getId()));
        }

        self::assertNull($this->em->find(JournalItem::class, $journalItem->getId()));
        self::assertNull($this->em->find(ContactPerson::class, $contact->getId()));
        self::assertNotNull($this->em->find(ApplicationSettingsItem::class, $otherSetting->getId()));
        self::assertNotNull($this->em->find(Bitrix24Account::class, $otherAccount->getId()));
        self::assertTrue($this->cleaner->contexts[0]->clean);
    }

    public function testUninstallCannotUseAnotherPortalsMemberId(): void
    {
        [$installation, $owner, $command] = $this->installation(true);
        [$victimInstallation, $victim] = $this->installation(true);
        $this->handler->handle(new Command($command->domainUrl, $victim->getMemberId(), $command->applicationToken, true));
        $this->em->clear();
        self::assertSame(ApplicationInstallationStatus::active, $this->em->find(ApplicationInstallation::class, $installation->getId())->getStatus());
        self::assertSame(ApplicationInstallationStatus::active, $this->em->find(ApplicationInstallation::class, $victimInstallation->getId())->getStatus());
        self::assertNotNull($this->em->find(Bitrix24Account::class, $owner->getId()));
        self::assertNotNull($this->em->find(Bitrix24Account::class, $victim->getId()));
        self::assertSame([], $this->cleaner->contexts);
        self::assertSame(0, $this->em->getRepository(Uninstallation::class)->count(['installationId' => $installation->getId()]));
    }

    public function testUnknownTokenDoesNotDeleteByMemberIdOrLogToken(): void
    {
        [$installation, $account, $command] = $this->installation(true);
        $this->handler->handle(new Command($command->domainUrl, $command->memberId, 'unknown-secret-token', true));
        $this->em->clear();
        self::assertNotNull($this->em->find(ApplicationInstallation::class, $installation->getId()));
        self::assertNotNull($this->em->find(Bitrix24Account::class, $account->getId()));
        self::assertSame([], $this->cleaner->contexts);
        self::assertStringNotContainsString('unknown-secret-token', json_encode($this->logger->recordsByLevel('info'), JSON_THROW_ON_ERROR));
    }

    public function testFailureKeepsAuthorizationRevokedAndAllowsRetryWithoutEscalatingClean(): void
    {
        [$installation, $account, $command] = $this->installation(false);
        $setting = $this->setting($installation);
        $this->em->flush();
        $this->cleaner->onClean = function () use ($setting): void {
            $this->em->remove($this->em->find(ApplicationSettingsItem::class, $setting->getId()));
            $this->em->flush();
            throw new \RuntimeException('application cleanup failed');
        };
        try {
            $this->handler->handle($command);
            self::fail('Cleanup failure swallowed');
        } catch (\RuntimeException $runtimeException) {
            self::assertSame('Application data cleanup failed; retry the uninstall event.', $runtimeException->getMessage());
            self::assertNull($runtimeException->getPrevious());
        }

        $this->reopenAfterFailure();
        $this->em->clear();
        self::assertNull($this->em->find(Bitrix24Account::class, $account->getId()));
        self::assertSame(ApplicationInstallationStatus::deleted, $this->em->find(ApplicationInstallation::class, $installation->getId())->getStatus());
        self::assertNotNull($this->em->find(ApplicationSettingsItem::class, $setting->getId()));
        self::assertNull($this->em->getRepository(Uninstallation::class)->findOneBy(['installationId' => $installation->getId()])->getCompletedAt());
        $this->cleaner->onClean = null;
        $this->handler->handle(new Command($command->domainUrl, $command->memberId, $command->applicationToken, true));
        $this->em->clear();
        self::assertFalse($this->cleaner->contexts[1]->clean);
        self::assertNotNull($this->em->find(ApplicationSettingsItem::class, $setting->getId()));
        self::assertNotNull($this->em->getRepository(Uninstallation::class)->findOneBy(['installationId' => $installation->getId()])->getCompletedAt());
    }

    public function testDuplicateAndDelayedOldEventDoNotAffectReinstallation(): void
    {
        [$old, , $command] = $this->installation(true);
        $this->handler->handle($command);
        [$new, $newAccount] = $this->installation(true, $command->memberId);
        $newSetting = $this->setting($new);
        $this->em->flush();
        $this->handler->handle($command);
        $this->em->clear();
        self::assertCount(1, $this->cleaner->contexts);
        self::assertNotNull($this->em->find(ApplicationInstallation::class, $new->getId()));
        self::assertNotNull($this->em->find(Bitrix24Account::class, $newAccount->getId()));
        self::assertNotNull($this->em->find(ApplicationSettingsItem::class, $newSetting->getId()));
        self::assertNull($this->em->find(ApplicationInstallation::class, $old->getId()));
    }

    public function testRetryOfPendingCleanupDoesNotDeleteNewAccounts(): void
    {
        [$old, , $command] = $this->installation(true);
        $oldSetting = $this->setting($old);
        $this->em->flush();
        $this->cleaner->onClean = static fn () => throw new \RuntimeException('failure');
        try { $this->handler->handle($command); } catch (\RuntimeException) {}

        $this->reopenAfterFailure();
        [$new, $newAccount] = $this->installation(true, $command->memberId);
        $newSetting = $this->setting($new);
        $this->em->flush();
        $this->cleaner->onClean = null;
        $this->handler->handle($command);
        $this->em->clear();
        self::assertNull($this->em->find(ApplicationSettingsItem::class, $oldSetting->getId()));
        self::assertNotNull($this->em->find(ApplicationSettingsItem::class, $newSetting->getId()));
        self::assertNotNull($this->em->find(Bitrix24Account::class, $newAccount->getId()));
        self::assertTrue($this->cleaner->contexts[1]->installationId->equals($old->getId()));
    }

    public function testStaleDeletedInstallationTokenDoesNotDeleteCurrentGeneration(): void
    {
        [$old, $oldAccount, $command] = $this->installation(true);
        $old->applicationUninstalled($command->applicationToken);
        $oldAccount->applicationUninstalled(null);
        $this->em->flush();
        [$new, $newAccount] = $this->installation(true, $command->memberId);
        $this->handler->handle($command);
        $this->em->clear();
        self::assertNotNull($this->em->find(ApplicationInstallation::class, $new->getId()));
        self::assertNotNull($this->em->find(Bitrix24Account::class, $newAccount->getId()));
        self::assertSame([], $this->cleaner->contexts);
    }

    public function testSharedContactIsPreserved(): void
    {
        $contact = (new ContactPersonBuilder())->build();
        $this->em->persist($contact);
        [, , $command] = $this->installation(true, null, $contact->getId());
        $this->installation(true, null, $contact->getId());
        $this->handler->handle($command);
        $this->em->clear();
        self::assertNotNull($this->em->find(ContactPerson::class, $contact->getId()));
    }

    public function testMismatchedMemberCannotResumePendingCleanup(): void
    {
        [$installation, , $command] = $this->installation(true);
        $this->cleaner->onClean = static fn () => throw new \RuntimeException('failure');
        try { $this->handler->handle($command); } catch (\RuntimeException) {}

        $this->reopenAfterFailure();
        $this->handler->handle(new Command($command->domainUrl, 'wrong-member', $command->applicationToken, true));
        self::assertCount(1, $this->cleaner->contexts);
        self::assertNull($this->em->getRepository(Uninstallation::class)->findOneBy(['installationId' => $installation->getId()])->getCompletedAt());
    }

    public function testUninstallLogsDoNotContainCredentials(): void
    {
        [, $account, $command] = $this->installation(true);
        $this->handler->handle($command);
        $logs = json_encode($this->logger->recordsByLevel('info'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($command->applicationToken, $logs);
        self::assertStringNotContainsString($account->getAuthToken()->accessToken, $logs);
        self::assertStringNotContainsString($account->getAuthToken()->refreshToken, $logs);
    }

    public function testDeletedInstallationRejectsLateAccountCreation(): void
    {
        [$installation, $account, $command] = $this->installation(true);
        $this->handler->handle($command);
        $accountHandler = new \Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart\Handler(
            new \Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository($this->em),
            new \Bitrix24\Lib\Services\Flusher($this->em, $this->dispatcher), $this->logger
        );
        $late = new \Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart\Command(
            42, false, $command->memberId, $command->domainUrl, $account->getAuthToken(),
            1, $account->getApplicationScope(), $installation->getId()
        );
        try {
            $accountHandler->handle($late);
            self::fail('Late authorization was persisted after uninstall');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\InactiveInstallationException) {}

        $this->reopenAfterFailure();
        self::assertSame(0, $this->em->getRepository(Bitrix24Account::class)->count(['memberId' => $command->memberId]));
    }

    public function testConcurrentPortalWriterBlocksUninstallBeforeAnyDeletion(): void
    {
        [$installation, $account, $command] = $this->installation(true);
        $peer = \Doctrine\DBAL\DriverManager::getConnection($this->em->getConnection()->getParams());
        $peer->beginTransaction();
        \Bitrix24\Lib\Services\LifecycleLock::acquire($peer, 'member:'.$command->memberId);
        $this->em->getConnection()->executeStatement("SET lock_timeout = '100ms'");
        try {
            $this->handler->handle($command);
            self::fail('Uninstall bypassed a concurrent lifecycle writer');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\UninstallationFailedException) {
        } finally {
            $peer->rollBack();
            $peer->close();
        }

        $this->reopenAfterFailure();
        self::assertNotNull($this->em->find(ApplicationInstallation::class, $installation->getId()));
        self::assertNotNull($this->em->find(Bitrix24Account::class, $account->getId()));
        self::assertSame([], $this->cleaner->contexts);
        $this->handler->handle($command);
        self::assertCount(1, $this->cleaner->contexts);
    }

    public function testConcurrentContactAttachmentBlocksPhysicalContactDeletion(): void
    {
        $contact = (new ContactPersonBuilder())->build();
        $this->em->persist($contact);
        [$installation, $account, $command] = $this->installation(true, null, $contact->getId());
        $peer = \Doctrine\DBAL\DriverManager::getConnection($this->em->getConnection()->getParams());
        $peer->beginTransaction();
        \Bitrix24\Lib\Services\LifecycleLock::acquire($peer, 'contact:'.$contact->getId()->toRfc4122());
        $this->em->getConnection()->executeStatement("SET lock_timeout = '100ms'");
        try {
            $this->handler->handle($command);
            self::fail('Cleanup bypassed a concurrent contact writer');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\UninstallationFailedException) {
        } finally {
            $peer->rollBack();
            $peer->close();
        }

        $this->reopenAfterFailure();
        self::assertNotNull($this->em->find(ContactPerson::class, $contact->getId()));
        self::assertNull($this->em->find(Bitrix24Account::class, $account->getId()));
        self::assertNull($this->em->getRepository(Uninstallation::class)->findOneBy(['installationId' => $installation->getId()])->getCompletedAt());
        $this->handler->handle($command);
        $this->em->clear();
        self::assertNull($this->em->find(ContactPerson::class, $contact->getId()));
    }

    public function testFlusherCannotAttachContactDeletedByUninstall(): void
    {
        $contact = (new ContactPersonBuilder())->build();
        $this->em->persist($contact);
        [, , $command] = $this->installation(true, null, $contact->getId());
        $this->handler->handle($command);
        [$new] = $this->installation(true);
        $this->em->find(ApplicationInstallation::class, $new->getId())->linkContactPerson($contact->getId());
        $flusher = new \Bitrix24\Lib\Services\Flusher($this->em, $this->dispatcher);
        try {
            $flusher->flush();
            self::fail('Deleted contact was attached to another installation');
        } catch (\LogicException $logicException) {
            self::assertSame('Referenced contact is no longer available.', $logicException->getMessage());
        }

        $this->reopenAfterFailure();
        self::assertNull($this->em->find(ApplicationInstallation::class, $new->getId())->getContactPersonId());
    }

    public function testNotificationFailureDoesNotUndoCompletedCleanupOrRepeatCleaner(): void
    {
        [$installation, , $command] = $this->installation(true);
        $this->dispatcher->addListener(ApplicationUninstallationCompletedEvent::class, static fn () => throw new \RuntimeException('observer failed'));
        $this->handler->handle($command);
        $this->handler->handle($command);

        $this->em->clear();
        self::assertCount(1, $this->cleaner->contexts);
        self::assertNotNull($this->em->getRepository(Uninstallation::class)->findOneBy(['installationId' => $installation->getId()])->getCompletedAt());
        self::assertSame(1, $this->logger->countByLevel('warning'));
    }

    public function testOuterTransactionCannotUndoAuthorizationRevocation(): void
    {
        [, $account, $command] = $this->installation(true);
        $this->em->getConnection()->beginTransaction();
        try {
            $this->handler->handle($command);
            self::fail('Uninstall accepted an outer transaction');
        } catch (\LogicException) {
        } finally {
            $this->em->getConnection()->rollBack();
        }

        $this->em->clear();
        self::assertNotNull($this->em->find(Bitrix24Account::class, $account->getId()));
        self::assertSame([], $this->cleaner->contexts);
    }

    public function testUnrelatedPendingChangesAreNotCommittedByUninstall(): void
    {
        [$installation, $account, $command] = $this->installation(true);
        $unrelated = $this->setting($installation);
        try {
            $this->handler->handle($command);
            self::fail('Uninstall committed unrelated changes');
        } catch (\LogicException) {}

        $this->em->clear();
        self::assertNull($this->em->find(ApplicationSettingsItem::class, $unrelated->getId()));
        self::assertNotNull($this->em->find(Bitrix24Account::class, $account->getId()));
        self::assertSame([], $this->cleaner->contexts);
    }

    public function testDocumentedMigrationSupportsTheReceiptMapping(): void
    {
        $connection = $this->em->getConnection();
        $schema = 'uninstall_test_'.str_replace('-', '', Uuid::v7()->toRfc4122());
        $connection->beginTransaction();
        try {
            $connection->executeStatement('CREATE SCHEMA '.$schema);
            $connection->executeStatement('SET LOCAL search_path TO '.$schema);
            $sql = file_get_contents(dirname(__DIR__, 5).'/src/ApplicationInstallations/Docs/uninstall-schema.sql');
            self::assertIsString($sql);
            $connection->executeStatement($sql);
            $uninstallation = new Uninstallation(Uuid::v7(), 'migration-test-token', 'migration-member', true);
            $this->em->persist($uninstallation);
            $this->em->flush();
            $this->em->clear();
            $loaded = $this->em->find(Uninstallation::class, hash('sha256', 'migration-test-token'));
            self::assertNotNull($loaded);
            self::assertTrue($loaded->contextFor('migration-member')->clean);
            self::assertNull($loaded->getCompletedAt());
            $loaded->complete();
            $this->em->flush();
            $this->em->clear();
            self::assertNotNull($this->em->find(Uninstallation::class, hash('sha256', 'migration-test-token'))->getCompletedAt());
        } finally {
            $connection->rollBack();
            $this->em->clear();
        }
    }

    public function testRetiredApplicationTokenCannotBeInstalledAgain(): void
    {
        [, $account, $command] = $this->installation(true);
        $this->handler->handle($command);
        $recordingAuthorizationVerifier = new \Bitrix24\Lib\Tests\Helpers\ApplicationInstallations\RecordingAuthorizationVerifier();
        $recordingAuthorizationVerifier->userId = $account->getBitrix24UserId();

        $handler = new \Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Handler(
            new \Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository($this->em),
            new \Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository($this->em),
            new \Bitrix24\Lib\Services\Flusher($this->em, $this->dispatcher), $this->logger,
            $recordingAuthorizationVerifier
        );
        $replay = new \Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Command(
            memberId: $command->memberId, domain: $command->domainUrl, authToken: $account->getAuthToken(),
            applicationVersion: 1, applicationScope: $account->getApplicationScope(),
            bitrix24UserId: $account->getBitrix24UserId(), isBitrix24UserAdmin: true,
            applicationStatus: new ApplicationStatus('F'), portalLicenseFamily: PortalLicenseFamily::free,
            applicationToken: $command->applicationToken, portalUsersCount: 1
        );
        try {
            $handler->handle($replay);
            self::fail('Retired application token was installed again');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\InactiveInstallationException) {}

        $this->reopenAfterFailure();
        self::assertSame(0, $this->em->getRepository(Bitrix24Account::class)->count(['memberId' => $command->memberId]));
    }

    public function testSettingCannotBeWrittenAfterCompletedCleanup(): void
    {
        [$installation, , $command] = $this->installation(true);
        $this->handler->handle($command);
        $applicationSettingsItem = new ApplicationSettingsItem($installation->getId(), 'late.setting', 'personal data');
        $this->em->persist($applicationSettingsItem);
        try {
            (new \Bitrix24\Lib\Services\Flusher($this->em, $this->dispatcher))->flush();
            self::fail('A late setting survived completed cleanup');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\InactiveInstallationException) {}

        $this->reopenAfterFailure();
        self::assertNull($this->em->find(ApplicationSettingsItem::class, $applicationSettingsItem->getId()));
    }

    public function testJournalCannotBeWrittenAfterCompletedCleanup(): void
    {
        [$installation, , $command] = $this->installation(true);
        $this->handler->handle($command);
        $journalItem = new JournalItem($command->memberId, $installation->getId(), LogLevel::info, 'late personal details', 'test', new Context(\Darsyn\IP\Version\Multi::factory('127.0.0.1')));
        $this->em->persist($journalItem);
        try {
            (new \Bitrix24\Lib\Services\Flusher($this->em, $this->dispatcher))->flush();
            self::fail('A late journal entry survived completed cleanup');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\InactiveInstallationException) {}

        $this->reopenAfterFailure();
        self::assertNull($this->em->find(JournalItem::class, $journalItem->getId()));
    }

    public function testSettingWriterParticipatesInInstallationCleanupLock(): void
    {
        [$installation] = $this->installation(true);
        $peer = \Doctrine\DBAL\DriverManager::getConnection($this->em->getConnection()->getParams());
        $peer->beginTransaction();
        \Bitrix24\Lib\Services\LifecycleLock::acquire($peer, 'installation:'.$installation->getId()->toRfc4122());
        $late = $this->setting($installation);
        $this->em->getConnection()->executeStatement("SET lock_timeout = '100ms'");
        try {
            (new \Bitrix24\Lib\Services\Flusher($this->em, $this->dispatcher))->flush();
            self::fail('Setting write bypassed the cleanup generation lock');
        } catch (\Doctrine\DBAL\Exception\DriverException $driverException) {
            self::assertSame('55P03', $driverException->getSQLState());
        } finally {
            $peer->rollBack();
            $peer->close();
        }

        $this->reopenAfterFailure();
        self::assertNull($this->em->find(ApplicationSettingsItem::class, $late->getId()));
    }

    public function testRetiredTokenCannotFinishANewPendingInstallation(): void
    {
        [, , $command] = $this->installation(true);
        $this->handler->handle($command);
        $account = (new Bitrix24AccountBuilder())->withMaster(true)->withMemberId($command->memberId)->build();
        $applicationInstallation = new ApplicationInstallation(Uuid::v7(), $account->getId(), new ApplicationStatus('F'), PortalLicenseFamily::free, 1, null, null, null, null);
        $this->em->persist($account);
        $this->em->persist($applicationInstallation);
        $this->em->flush();

        $recordingAuthorizationVerifier = new \Bitrix24\Lib\Tests\Helpers\ApplicationInstallations\RecordingAuthorizationVerifier();
        $recordingAuthorizationVerifier->userId = $account->getBitrix24UserId();

        $handler = new \Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Handler(
            new \Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository($this->em),
            new \Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository($this->em),
            new \Bitrix24\Lib\Services\Flusher($this->em, $this->dispatcher), $this->logger, $recordingAuthorizationVerifier
        );
        try {
            $handler->handle(new \Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Command(
                $command->memberId, $command->domainUrl, $command->applicationToken, new ApplicationStatus('F'), $account->getAuthToken()
            ));
            self::fail('Retired token finished a new generation');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\InactiveInstallationException) {}

        $this->reopenAfterFailure();
        self::assertSame(ApplicationInstallationStatus::new, $this->em->find(ApplicationInstallation::class, $applicationInstallation->getId())->getStatus());
        self::assertNotNull($this->em->find(Bitrix24Account::class, $account->getId()));
    }

    public function testStaleInstallationCannotPersistANewContactAfterCleanup(): void
    {
        [$installation, , $command] = $this->installation(true);
        $entityManager = new \Doctrine\ORM\EntityManager(
            \Doctrine\DBAL\DriverManager::getConnection($this->em->getConnection()->getParams()),
            $this->em->getConfiguration()
        );
        $stale = $entityManager->find(ApplicationInstallation::class, $installation->getId());
        $this->handler->handle($command);
        $contact = (new ContactPersonBuilder())->withEmail('late@example.com')->build();
        $entityManager->persist($contact);
        $stale->linkContactPerson($contact->getId());
        try {
            (new \Bitrix24\Lib\Services\Flusher($entityManager, $this->dispatcher))->flush($stale, $contact);
            self::fail('Stale installation inserted personal data after cleanup');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\InactiveInstallationException) {}

        $this->em->clear();
        self::assertNull($this->em->find(ContactPerson::class, $contact->getId()));
    }

    private function reopenAfterFailure(): void
    {
        $this->em = EntityManagerFactory::get();
        $this->handler = new Handler(new UninstallationStore($this->em), $this->cleaner, $this->logger, $this->dispatcher);
    }

    /** @return array{ApplicationInstallation, Bitrix24Account, Command} */
    private function installation(bool $clean, ?string $memberId = null, ?Uuid $contactId = null): array
    {
        $token = Uuid::v7()->toRfc4122();
        $builder = (new Bitrix24AccountBuilder())->withStatus(Bitrix24AccountStatus::new)
            ->withInstalled()->withMaster(true)->withApplicationToken($token)->withSetToken();
        if ($memberId !== null) { $builder->withMemberId($memberId); }

        $account = $builder->build();
        $installation = (new ApplicationInstallationBuilder())->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)->withBitrix24AccountId($account->getId())
            ->withApplicationToken($token)->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withContactPersonId($contactId)->withBitrix24PartnerContactPersonId(null)->build();
        $this->em->persist($account);
        $this->em->persist($installation);
        $this->em->flush();
        return [$installation, $account, new Command(new Domain($account->getDomainUrl()), $account->getMemberId(), $token, $clean)];
    }

    private function setting(ApplicationInstallation $installation, ApplicationSettingStatus $status = ApplicationSettingStatus::Active): ApplicationSettingsItem
    {
        $applicationSettingsItem = new ApplicationSettingsItem($installation->getId(), 'setting.test', 'private data', status: $status);
        $this->em->persist($applicationSettingsItem);
        return $applicationSettingsItem;
    }
}
