<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\UseCase\Create;

use Bitrix24\Lib\News\Entity\News;
use Bitrix24\Lib\News\Infrastructure\Doctrine\NewsRepositoryInterface;
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

        $news = new News(
            Uuid::v7(),
            $command->title,
            $command->text
        );

        $this->newsRepository->save($news);

        $this->logger->debug('News.Create.created', [
            'newsId' => $news->getId()->toRfc4122(),
        ]);

        $this->flusher->flush($news);

        $this->logger->info('News.Create.finish', [
            'newsId' => $news->getId()->toRfc4122(),
        ]);
    }
}
