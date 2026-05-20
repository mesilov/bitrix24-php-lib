<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

use Psr\Log\LoggerInterface;

class BanDetector
{
    private const int CONSECUTIVE_EMPTY_THRESHOLD = 10;

    private const float EMPTY_RATIO_THRESHOLD = 0.5;

    private int $consecutiveEmptyPages = 0;

    private int $totalEmptyPages = 0;

    private int $totalPagesProcessed = 0;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function onEmptyPage(): bool
    {
        ++$this->consecutiveEmptyPages;
        ++$this->totalEmptyPages;
        ++$this->totalPagesProcessed;

        if ($this->consecutiveEmptyPages >= self::CONSECUTIVE_EMPTY_THRESHOLD) {
            $this->logger->error(sprintf(
                'Обнаружена блокировка: %d страниц подряд без данных. Рекомендуется увеличить задержки.',
                $this->consecutiveEmptyPages,
            ));

            return true;
        }

        return false;
    }

    public function onSuccessfulPage(): void
    {
        $this->consecutiveEmptyPages = 0;
        ++$this->totalPagesProcessed;
    }

    public function isSuspicious(): bool
    {
        if ($this->totalPagesProcessed > 0 && $this->totalEmptyPages / $this->totalPagesProcessed > self::EMPTY_RATIO_THRESHOLD) {
            $this->logger->error(sprintf(
                'Подозрение на блокировку: %d из %d страниц пустые (%.0f%%).',
                $this->totalEmptyPages,
                $this->totalPagesProcessed,
                $this->totalEmptyPages / $this->totalPagesProcessed * 100,
            ));

            return true;
        }

        return false;
    }

    public function getTotalEmptyPages(): int
    {
        return $this->totalEmptyPages;
    }

    public function getTotalPagesProcessed(): int
    {
        return $this->totalPagesProcessed;
    }

    public function reset(): void
    {
        $this->consecutiveEmptyPages = 0;
        $this->totalEmptyPages = 0;
        $this->totalPagesProcessed = 0;
    }
}
