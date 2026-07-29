<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\UseCase\Update;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * When calling the update use case, you must always pass all newsItem data,
 * including new changes; otherwise, the data will be overwritten with null.
 */
readonly class Command
{
    public function __construct(
        public Uuid $id,
        public string $title,
        public string $text,
        public ?string $imageUrl = null
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

        if (null !== $this->imageUrl && '' === trim($this->imageUrl)) {
            throw new InvalidArgumentException('news image url cannot be empty');
        }
    }
}
