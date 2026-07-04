<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Helpers\Bitrix24Accounts;

use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Tests\Unit\Application\Contracts\Bitrix24Accounts\Repository\InMemoryBitrix24AccountRepositoryImplementation;

class RecordingBitrix24AccountInMemoryRepository extends InMemoryBitrix24AccountRepositoryImplementation
{
    private int $saveCalls = 0;

    #[\Override]
    public function save(Bitrix24AccountInterface $bitrix24Account): void
    {
        $this->saveCalls++;
        parent::save($bitrix24Account);
    }

    public function getSaveCalls(): int
    {
        return $this->saveCalls;
    }
}
