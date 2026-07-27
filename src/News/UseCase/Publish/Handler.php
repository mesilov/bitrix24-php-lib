<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\UseCase\Publish;

use Bitrix24\Lib\News\Entity\News;
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
        $this->logger->info('News.Publish.start', [
            'newsId' => $command->id->toRfc4122(),
        ]);

        /** @var AggregateRootEventsEmitterInterface&News $news */
        $news = $this->newsRepository->getById($command->id);

        $news->publish();

        $this->newsRepository->save($news);

        $this->flusher->flush($news);

        $this->logger->info('News.Publish.finish', [
            'newsId' => $command->id->toRfc4122(),
        ]);
    }
}
