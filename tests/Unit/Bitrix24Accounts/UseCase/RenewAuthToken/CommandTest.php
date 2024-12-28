<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\RenewAuthToken;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\RenewAuthToken\Command;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Response\DTO\RenewedAuthToken;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    public function testValidBitrix24UserId(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $newAuthToken = new AuthToken('new_1', 'new_2', 3600);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 User ID must be a positive integer.');

        new Command(
            new RenewedAuthToken(
                $newAuthToken,
                $bitrix24Account->getMemberId(),
                'https://client-endpoint.com',
                'https://server-endpoint.com',
                ApplicationStatus::subscription(),
                $bitrix24Account->getDomainUrl()
            ),
        );
    }
}