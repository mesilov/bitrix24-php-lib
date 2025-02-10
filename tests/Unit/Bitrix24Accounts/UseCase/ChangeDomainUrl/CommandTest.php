<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl\Command;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;


/**
 * @internal
 */
#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    #[DataProvider('dataForValidateValidDomain')]
    public function testValidateDomain(
        string $oldDomain,
        string $newDomain,
    ): void {
        new Domain($oldDomain);
        new Domain($newDomain);
        $this->assertTrue(true);
    }

    #[Test]
    #[DataProvider('dataForValidateInvalidDomain')]
    public function testValidateInvalidDomain(
        string $oldDomain,
        string $newDomain,
        ?string $expectedException,
        ?string $expectedExceptionMessage
    ): void {

        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }

        if ($expectedExceptionMessage !== null) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        new Domain($oldDomain);
        new Domain($newDomain);

    }

    public static function dataForValidateValidDomain(): \Generator
    {

        // Примеры допустимых доменов
        /*$arrValidDomains = [
            ['oldDomain' => 'example.com', 'newDomain' => 'example.org'],
            ['oldDomain' => 'пример.рф', 'newDomain' => 'пример.рус'],
            ['oldDomain' => 'test-site.org', 'newDomain' => 'test-site.ru'],
            ['oldDomain' => 'valid-domain.co.uk', 'newDomain' => 'valid-domain.net'],
            ['oldDomain' => 'subdomain.example.com', 'newDomain' => 'subdomain2.example.com'],
            ['oldDomain' => 'тест.рус', 'newDomain' => 'тест2.рус'], // Пример с кириллицей
        ];*/

        yield 'validDomain1' => [
            'example.com',
            'example.org',
        ];

        yield 'validDomain2' => [
            'пример.рф',
            'пример.рус',
        ];

        yield 'validDomain3' => [
            'test-site.org',
            'test-site.ru',
        ];

        yield 'validDomain4' => [
            'valid-domain.co.uk',
            'valid-domain.net',
        ];

        yield 'validDomain5' => [
            'subdomain.example.com',
            'subdomain2.example.com',
        ];

        yield 'validDomain6' => [
            'тест.рус',
            'тест2.рус',
        ];
    }

    public static function dataForValidateInvalidDomain(): \Generator
    {
        yield 'invalidDomain1' => [
            'invalid_domain.com', // Неправильный формат (подчеркивание)
            'valid.com',
            \InvalidArgumentException::class,
            sprintf('Invalid domain: %s', 'invalid_domain.com')
        ];

        yield 'invalidDomain2' => [
            '-invalid.com', // Домен не может начинаться с дефиса
            'valid.com',
            \InvalidArgumentException::class,
            sprintf('Invalid domain: %s', '-invalid.com')
        ];

        yield 'invalidDomain3' => [
            'invalid-.com', // Домен не может заканчиваться на дефис
            'valid.com',
            \InvalidArgumentException::class,
            sprintf('Invalid domain: %s', 'invalid-.com')
        ];

        yield 'invalidDomain4' => [
            '123.456.789.0', // Неправильный формат (IP-адрес)
            'valid.com',
            \InvalidArgumentException::class,
              sprintf('Invalid domain: %s', '123.456.789.0')
        ];

        yield 'invalidDomain5' => [
            'example..com', // Два подряд идущих точки
            'valid.com',
            \InvalidArgumentException::class,
            sprintf('Invalid domain: %s', 'example..com')
        ];

        yield 'invalidDomain6' => [
            'example.c', // Слишком короткая доменная зона
            'valid.com',
            \InvalidArgumentException::class,
            sprintf('Invalid domain: %s', 'example.c')
        ];
    }
}
