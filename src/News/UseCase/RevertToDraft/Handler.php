<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\UseCase\RevertToDraft;

use Bitrix24\Lib\News\Entity\News;
use Bitrix24\Lib\News\Exceptions\NewsNotFoundException;
use Bitrix24\Lib\News\Infrastructure\NewsRepositoryInterface;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private NewsRepositoryInterface $newsRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('News.RevertToDraft.start', [
            'newsId' => $command->id->toRfc4122(),
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface&News $newsItem */
            $newsItem = $this->newsRepository->getById($command->id);

            $newsItem->revertToDraft();

            $this->newsRepository->save($newsItem);

            $this->flusher->flush($newsItem);

            $this->logger->info('News.RevertToDraft.success', [
                'newsId' => $command->id->toRfc4122(),
            ]);
        } catch (NewsNotFoundException $newsNotFoundException) {
            $this->logger->warning('News.RevertToDraft.notFound', [
                'newsId' => $command->id->toRfc4122(),
                'message' => $newsNotFoundException->getMessage(),
            ]);

            throw $newsNotFoundException;
        } finally {
            $this->logger->info('News.RevertToDraft.finish', [
                'newsId' => $command->id->toRfc4122(),
            ]);
        }
    }
}
