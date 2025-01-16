<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl\Command;
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
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
        array $arr,
        ?string $expectedException
    ) {

        $exceptionCount = 0;

        foreach ($arr as $arrDomains) {
            foreach ($arrDomains as $domainPair) {
                try {
                    new Command($domainPair['oldDomain'], $domainPair['newDomain']);
                } catch (\InvalidArgumentException $e) {
                    // Увеличиваем счетчик при каждом выбросе исключения
                    $exceptionCount++;
                }
            }
        }

        // Проверяем, сколько исключений было выброшено
        if ($expectedException !== null) {
            $this->assertEquals(6, $exceptionCount, "Expected 6 invalid exception and received {$exceptionCount}");
        } else {
            // Если ожидается отсутствие исключений, проверяем что их не было
            $this->assertEquals(0, $exceptionCount, "No exceptions were expected but {$exceptionCount} were thrown.");
        }

    }

    public static function dataForCommand(): \Generator
    {

        // Примеры недопустимых доменов
        $arrInvalidDomains = [
            ['oldDomain' => 'invalid_domain.com', 'newDomain' => 'valid.com'], // Неправильный формат (подчеркивание)
            ['oldDomain' => '-invalid.com', 'newDomain' => 'valid.com'], // Домен не может начинаться с дефиса
            ['oldDomain' => 'invalid-.com', 'newDomain' => 'valid.com'], // Домен не может заканчиваться на дефис
            ['oldDomain' => '123.456.789.0', 'newDomain' => 'valid.com'], // Неправильный формат (IP-адрес)
            ['oldDomain' => 'example..com', 'newDomain' => 'valid.com'], // Два подряд идущих точки
            ['oldDomain' => 'example.c', 'newDomain' => 'valid.com'] // Слишком короткая доменная зона
        ];

        // Примеры допустимых доменов
        $arrValidDomains = [
            ['oldDomain' => 'example.com', 'newDomain' => 'example.org'],
            ['oldDomain' => 'пример.рф', 'newDomain' => 'пример.рус'],
            ['oldDomain' => 'test-site.org', 'newDomain' => 'test-site.ru'],
            ['oldDomain' => 'valid-domain.co.uk', 'newDomain' => 'valid-domain.net'],
            ['oldDomain' => 'subdomain.example.com', 'newDomain' => 'subdomain2.example.com'],
            ['oldDomain' => 'тест.рус', 'newDomain' => 'тест2.рус'], // Пример с кириллицей
        ];

            yield 'invalidDomain' => [
                [$arrInvalidDomains], // Оборачиваем в массив для передачи в testValidCommand
                \InvalidArgumentException::class
            ];

            yield 'validDomain' => [
                [$arrValidDomains], // Оборачиваем в массив для передачи в testValidCommand
                null // Здесь исключение не ожидается
            ];
    }
}
