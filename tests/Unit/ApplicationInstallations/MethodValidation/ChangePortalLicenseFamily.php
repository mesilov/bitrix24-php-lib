<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\MethodValidation;

use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use PHPUnit\Framework\TestCase;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
class ChangePortalLicenseFamily extends TestCase
{
   public function testEqualsChangePortalLicenseFamily(): void
   {
       $applicationInstallation = (new ApplicationInstallationBuilder)
           ->withPortalLicenseFamily(PortalLicenseFamily::free)
           ->withApplicationStatus(new ApplicationStatus('F'))
           ->build();
       $applicationInstallation->changePortalLicenseFamily(PortalLicenseFamily::basic);
       $this->assertEquals(PortalLicenseFamily::basic, $applicationInstallation->getPortalLicenseFamily());
   }

    public function testNotEqualsChangePortalLicenseFamily(): void
    {
        $applicationInstallation = (new ApplicationInstallationBuilder)
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->build();
        $initialUpdatedAt = $applicationInstallation->getUpdatedAt();
        $applicationInstallation->changePortalLicenseFamily(PortalLicenseFamily::free);
        $this->assertEquals($initialUpdatedAt, $applicationInstallation->getUpdatedAt());
    }
}
