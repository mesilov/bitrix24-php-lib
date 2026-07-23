<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\UseCase\Create;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

readonly class Command
{
    public function __construct(
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
