<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\UseCase\Update;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $id,
        public string $title,
        public string $text
    ) {
        $this->validate();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if ('' === trim($this->title)) {
            throw new InvalidArgumentException('news title cannot be empty');
        }

        if ('' === trim($this->text)) {
            throw new InvalidArgumentException('news text cannot be empty');
        }
    }
}
