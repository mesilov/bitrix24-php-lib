<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\UseCase\Create;

use Bitrix24\Lib\News\Entity\News;
use Bitrix24\Lib\News\Infrastructure\NewsRepositoryInterface;
use Bitrix24\Lib\Services\Flusher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private NewsRepositoryInterface $newsRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('News.Create.start', [
            'title' => $command->title,
        ]);

        $newsId = Uuid::v7();

        try {
            $newsItem = new News(
                $newsId,
                $command->title,
                $command->text,
                isEmitNewsCreatedEvent: true,
            );

            if (null !== $command->imageUrl) {
                $newsItem->attachImage($command->imageUrl);
            }

            $this->newsRepository->save($newsItem);

            $this->flusher->flush($newsItem);

            $this->logger->info('News.Create.success', [
                'newsId' => $newsId->toRfc4122(),
            ]);
        } finally {
            $this->logger->info('News.Create.finish', [
                'newsId' => $newsId->toRfc4122(),
            ]);
        }
    }
}
