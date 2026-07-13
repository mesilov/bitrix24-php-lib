<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\Import;

use Bitrix24\Lib\Bitrix24Partners\Import\PartnerSyncView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(PartnerSyncView::class)]
class PartnerSyncViewTest extends TestCase
{
    /**
     * getArrayResult() applies the Doctrine `uuid` type, so the `id` column
     * arrives as a Symfony Uuid object — not a string. fromArray() must handle it.
     */
    #[Test]
    public function fromArrayAcceptsUuidObjectFromGetArrayResult(): void
    {
        $id = Uuid::v7();

        $view = PartnerSyncView::fromArray([
            'id' => $id,
            'bitrix24PartnerNumber' => 16592200,
            'title' => 'Test Partner',
            'site' => 'https://example.com',
            'email' => 'test@example.com',
            'logoUrl' => 'https://example.com/logo.png',
        ]);

        self::assertSame($id, $view->id);
        self::assertSame(16592200, $view->bitrix24PartnerNumber);
        self::assertSame('Test Partner', $view->title);
        self::assertSame('https://example.com', $view->site);
        self::assertSame('test@example.com', $view->email);
        self::assertSame('https://example.com/logo.png', $view->logoUrl);
    }
}
