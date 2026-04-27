<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Partners\UseCase\Create;

use Bitrix24\Lib\Bitrix24Partners;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Partners\Builders\Bitrix24PartnerBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @internal
 */
#[CoversClass(Bitrix24Partners\UseCase\Create\Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24Partners\UseCase\Create\Handler $handler;

    private Flusher $flusher;

    private Bitrix24PartnerRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new Bitrix24PartnerRepository($this->entityManager);
        $this->flusher = new Flusher($this->entityManager, $this->eventDispatcher);
        $this->handler = new Bitrix24Partners\UseCase\Create\Handler(
            $this->repository,
            $this->flusher,
            PhoneNumberUtil::getInstance(),
            new NullLogger()
        );
    }

    #[Test]
    public function testCreatePartner(): void
    {
        $expectedPartner = (new Bitrix24PartnerBuilder())
            ->withTitle('Test Partner')
            ->withBitrix24PartnerNumber(rand(1000, 9999))
            ->withSite('https://example.com')
            ->withPhone(null)
            ->withEmail('test@example.com')
            ->withOpenLineId('line-123')
            ->withExternalId('ext-123')
            ->build();

        $command = new Bitrix24Partners\UseCase\Create\Command(
            $expectedPartner->getTitle(),
            $expectedPartner->getBitrix24PartnerNumber(),
            $expectedPartner->getSite(),
            $expectedPartner->getPhone(),
            $expectedPartner->getEmail(),
            $expectedPartner->getOpenLineId(),
            $expectedPartner->getExternalId()
        );

        $this->handler->handle($command);

        $this->entityManager->clear();

        $this->assertContains(
            Bitrix24PartnerCreatedEvent::class,
            $this->eventDispatcher->getOrphanedEvents(),
        );

        $createdPartner = $this->repository->findByBitrix24PartnerNumber($expectedPartner->getBitrix24PartnerNumber());
        $this->assertNotNull($createdPartner, 'Created partner not found in repository');
    }

    #[Test]
    public function testCreatePartnerWithInvalidMobilePhoneNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mobile phone number.');

        $command = new Bitrix24Partners\UseCase\Create\Command(
            'Test Partner',
            rand(1000, 9999),
            'https://example.com',
            PhoneNumberUtil::getInstance()->parse('+70000000000', 'RU'),
            'test@example.com',
            'line-123',
            'ext-123'
        );

        $this->handler->handle($command);
    }

    #[Test]
    public function testCreatePartnerWithNonMobilePhoneNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Phone number must be mobile.');

        // Example of a fixed line number in RU (Moscow)
        $command = new Bitrix24Partners\UseCase\Create\Command(
            'Test Partner',
            rand(1000, 9999),
            'https://example.com',
            PhoneNumberUtil::getInstance()->parse('+74957777777', 'RU'),
            'test@example.com',
            'line-123',
            'ext-123'
        );

        $this->handler->handle($command);
    }
}
