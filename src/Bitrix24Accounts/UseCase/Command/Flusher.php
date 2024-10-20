<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\Command;

use Doctrine\ORM\EntityManagerInterface;

final readonly class Flusher
{
    public function __construct(private EntityManagerInterface $em) {}

    public function flush(): void
    {
        $this->em->flush();
    }
}