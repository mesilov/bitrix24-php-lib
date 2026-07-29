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

        $newsItem = new News(
            Uuid::v7(),
            $command->title,
            $command->text,
            isEmitNewsCreatedEvent: true,
        );

        if (null !== $command->imageUrl) {
            $newsItem->attachImage($command->imageUrl);
        }

        $this->newsRepository->save($newsItem);

        $this->logger->debug('News.Create.created', [
            'newsId' => $newsItem->getId()->toRfc4122(),
        ]);

        $this->flusher->flush($newsItem);

        $this->logger->info('News.Create.finish', [
            'newsId' => $newsItem->getId()->toRfc4122(),
        ]);
    }
}
