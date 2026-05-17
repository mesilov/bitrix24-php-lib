<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\PartnerData;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PartnerData::class)]
class PartnerDataTest extends TestCase
{
    #[Test]
    public function testCreatesPartnerDataWithAllFields(): void
    {
        $scrapedAt = CarbonImmutable::now();

        $dto = new PartnerData(
            bitrix24PartnerNumber: 12345,
            title: 'Test Partner',
            site: 'https://example.com',
            phone: '+7 (495) 123-45-67',
            email: 'test@example.com',
            logoUrl: 'https://example.com/logo.png',
            detailPageUrl: '/partners/partner/12345/',
            baseDomain: 'https://www.bitrix24.ru',
            scrapedAt: $scrapedAt,
        );

        $this->assertSame(12345, $dto->bitrix24PartnerNumber);
        $this->assertSame('Test Partner', $dto->title);
        $this->assertSame('https://example.com', $dto->site);
        $this->assertSame('+7 (495) 123-45-67', $dto->phone);
        $this->assertSame('test@example.com', $dto->email);
        $this->assertSame('https://example.com/logo.png', $dto->logoUrl);
        $this->assertSame('/partners/partner/12345/', $dto->detailPageUrl);
        $this->assertSame('https://www.bitrix24.ru', $dto->baseDomain);
        $this->assertSame($scrapedAt, $dto->scrapedAt);
    }

    #[Test]
    public function testCreatesPartnerDataWithNullableFieldsAsNull(): void
    {
        $scrapedAt = CarbonImmutable::now();

        $dto = new PartnerData(
            bitrix24PartnerNumber: 67890,
            title: 'Minimal Partner',
            site: null,
            phone: null,
            email: null,
            logoUrl: null,
            detailPageUrl: '/partners/partner/67890/',
            baseDomain: 'https://www.bitrix24.ru',
            scrapedAt: $scrapedAt,
        );

        $this->assertSame(67890, $dto->bitrix24PartnerNumber);
        $this->assertNull($dto->site);
        $this->assertNull($dto->phone);
        $this->assertNull($dto->email);
        $this->assertNull($dto->logoUrl);
    }
}
