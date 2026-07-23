<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\UseCase\Archive;

use Bitrix24\Lib\News\Entity\NewsInterface;
use Bitrix24\Lib\News\Infrastructure\Doctrine\NewsRepositoryInterface;
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
        $this->logger->info('News.Archive.start', [
            'newsId' => $command->id->toRfc4122(),
        ]);

        /** @var AggregateRootEventsEmitterInterface&NewsInterface $news */
        $news = $this->newsRepository->getById($command->id);

        $news->archive();

        $this->newsRepository->save($news);

        $this->flusher->flush($news);

        $this->logger->info('News.Archive.finish', [
            'newsId' => $command->id->toRfc4122(),
        ]);
    }
}
