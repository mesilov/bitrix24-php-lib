<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\News\Builders;

use Bitrix24\Lib\News\Entity\News;
use Bitrix24\Lib\News\Entity\NewsStatus;
use Faker\Factory;
use Symfony\Component\Uid\Uuid;

class NewsBuilder
{
    private readonly Uuid $id;

    private string $title;

    private string $text;

    private NewsStatus $status = NewsStatus::draft;

    private bool $isEmitNewsCreatedEvent = false;

    private ?string $imageUrl = null;

    public function __construct()
    {
        $faker = Factory::create();
        $this->id = Uuid::v7();
        $this->title = $faker->sentence(3);
        $this->text = $faker->paragraph(3);
    }

    public function withTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function withText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function withStatus(NewsStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function withEmitCreatedEvent(bool $emit = true): self
    {
        $this->isEmitNewsCreatedEvent = $emit;

        return $this;
    }

    public function withImageUrl(?string $url): self
    {
        $this->imageUrl = $url;

        return $this;
    }

    public function build(): News
    {
        $news = new News(
            $this->id,
            $this->title,
            $this->text,
            $this->isEmitNewsCreatedEvent,
        );

        if ($this->imageUrl !== null) {
            $news->attachImage($this->imageUrl);
        }

        match ($this->status) {
            NewsStatus::draft => null,
            NewsStatus::published => $news->publish(),
            NewsStatus::deleted => $news->markAsDeleted(),
        };

        return $news;
    }
}
