<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Uninstall;

final class InactiveInstallationException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The requested installation is no longer active.');
    }
}
