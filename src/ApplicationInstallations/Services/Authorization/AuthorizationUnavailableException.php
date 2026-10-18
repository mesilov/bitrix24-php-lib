<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Authorization;

final class AuthorizationUnavailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('OAuth authorization verification is temporarily unavailable.');
    }
}
