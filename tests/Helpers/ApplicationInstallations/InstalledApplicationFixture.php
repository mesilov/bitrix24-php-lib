<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Helpers\ApplicationInstallations;

use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Symfony\Component\Uid\Uuid;

final class InstalledApplicationFixture
{
    public static function createId(): Uuid
    {
        $em = EntityManagerFactory::get();
        $account = (new Bitrix24AccountBuilder())->withMaster(true)->build();
        $installation = (new ApplicationInstallationBuilder())->withBitrix24AccountId($account->getId())
            ->withApplicationStatus(new ApplicationStatus('F'))->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)->build();
        $em->persist($account);
        $em->persist($installation);
        $em->flush();
        return $installation->getId();
    }
}
