<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Services;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\InactiveInstallationException;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Doctrine\ORM\EntityManagerInterface;

/** Synchronize ORM writes with uninstall before they reach the database. */
final class LifecycleWriteGuard
{
    public static function lock(EntityManagerInterface $em): void
    {
        $unitOfWork = $em->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        $writes = [...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()];
        $members = [];
        $installations = [];
        $dataOwners = [];
        $contacts = [];
        $newInstallations = [];
        $existingInstallations = [];
        $newContacts = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof ApplicationInstallation && ApplicationInstallationStatus::deleted !== $entity->getStatus()) {
                $newInstallations[$entity->getId()->toRfc4122()] = true;
            }

            if ($entity instanceof ContactPerson) {
                $newContacts[$entity->getId()->toRfc4122()] = true;
            }
        }

        foreach ($writes as $write) {
            if ($write instanceof Bitrix24Account) {
                $members[$write->getMemberId()] = true;
            }

            if ($write instanceof ApplicationInstallation) {
                $installationId = $write->getId()->toRfc4122();
                $installations[$installationId] = true;
                if (!isset($newInstallations[$installationId])) {
                    $existingInstallations[$installationId] = true;
                }

                foreach ([$write->getContactPersonId(), $write->getBitrix24PartnerContactPersonId()] as $id) {
                    if (null !== $id) {
                        $contacts[$id->toRfc4122()] = true;
                    }
                }
            }

            if ($write instanceof ApplicationSettingsItem || $write instanceof JournalItem) {
                $owner = $write->getApplicationInstallationId()->toRfc4122();
                $installations[$owner] = true;
                $dataOwners[$owner] = true;
            }
        }

        $connection = $em->getConnection();
        ksort($members);
        foreach ($members as $member => $_) {
            LifecycleLock::acquire($connection, 'member:'.$member);
        }

        ksort($installations);
        foreach ($installations as $id => $_) {
            LifecycleLock::acquire($connection, 'installation:'.$id);
            if (isset($newInstallations[$id]) && false !== $connection->fetchOne('SELECT installation_id FROM b24lib_uninstallations WHERE installation_id = ?', [$id])) {
                throw new InactiveInstallationException();
            }

            if (isset($existingInstallations[$id]) || (isset($dataOwners[$id]) && !isset($newInstallations[$id]))) {
                $status = $connection->fetchOne('SELECT status FROM b24lib_application_installations WHERE id = ?', [$id]);
                if (false === $status || $status === ApplicationInstallationStatus::deleted->value) {
                    throw new InactiveInstallationException();
                }
            }
        }

        ksort($contacts);
        foreach ($contacts as $id => $_) {
            LifecycleLock::acquire($connection, 'contact:'.$id);
            if (!isset($newContacts[$id]) && false === $connection->fetchOne('SELECT id FROM b24lib_contact_persons WHERE id = ?', [$id])) {
                throw new \LogicException('Referenced contact is no longer available.');
            }
        }
    }
}
