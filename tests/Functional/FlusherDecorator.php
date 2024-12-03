<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Tests\Application\Contracts\TestRepositoryFlusherInterface;

readonly class FlusherDecorator implements TestRepositoryFlusherInterface
{
    public function __construct(
        private Flusher $flusher
    ) {}

    #[\Override]
    public function flush(): void
    {
        $this->flusher->flush();
    }
}
