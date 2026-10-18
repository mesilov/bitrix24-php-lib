<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Authorization;

final class InvalidAuthorizationException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('OAuth authorization could not be verified.');
    }
}
