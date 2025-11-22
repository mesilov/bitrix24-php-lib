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

namespace Bitrix24\Lib\Tests\Unit\Journal\Entity;

use Bitrix24\Lib\Journal\Entity\LogLevel;
use PHPUnit\Framework\TestCase;

class LogLevelTest extends TestCase
{
    public function testFromPsr3LevelEmergency(): void
    {
        $level = LogLevel::fromPsr3Level('emergency');
        $this->assertSame(LogLevel::emergency, $level);
    }

    public function testFromPsr3LevelAlert(): void
    {
        $level = LogLevel::fromPsr3Level('alert');
        $this->assertSame(LogLevel::alert, $level);
    }

    public function testFromPsr3LevelCritical(): void
    {
        $level = LogLevel::fromPsr3Level('critical');
        $this->assertSame(LogLevel::critical, $level);
    }

    public function testFromPsr3LevelError(): void
    {
        $level = LogLevel::fromPsr3Level('error');
        $this->assertSame(LogLevel::error, $level);
    }

    public function testFromPsr3LevelWarning(): void
    {
        $level = LogLevel::fromPsr3Level('warning');
        $this->assertSame(LogLevel::warning, $level);
    }

    public function testFromPsr3LevelNotice(): void
    {
        $level = LogLevel::fromPsr3Level('notice');
        $this->assertSame(LogLevel::notice, $level);
    }

    public function testFromPsr3LevelInfo(): void
    {
        $level = LogLevel::fromPsr3Level('info');
        $this->assertSame(LogLevel::info, $level);
    }

    public function testFromPsr3LevelDebug(): void
    {
        $level = LogLevel::fromPsr3Level('debug');
        $this->assertSame(LogLevel::debug, $level);
    }

    public function testFromPsr3LevelCaseInsensitive(): void
    {
        $this->assertSame(LogLevel::info, LogLevel::fromPsr3Level('INFO'));
        $this->assertSame(LogLevel::error, LogLevel::fromPsr3Level('ERROR'));
        $this->assertSame(LogLevel::debug, LogLevel::fromPsr3Level('DeBuG'));
    }

    public function testFromPsr3LevelInvalidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PSR-3 log level: invalid');

        LogLevel::fromPsr3Level('invalid');
    }

    public function testEnumValues(): void
    {
        $this->assertSame('emergency', LogLevel::emergency->value);
        $this->assertSame('alert', LogLevel::alert->value);
        $this->assertSame('critical', LogLevel::critical->value);
        $this->assertSame('error', LogLevel::error->value);
        $this->assertSame('warning', LogLevel::warning->value);
        $this->assertSame('notice', LogLevel::notice->value);
        $this->assertSame('info', LogLevel::info->value);
        $this->assertSame('debug', LogLevel::debug->value);
    }

    public function testAllLogLevelsExist(): void
    {
        $cases = LogLevel::cases();
        $this->assertCount(8, $cases);

        $values = array_map(static fn (LogLevel $level): string => $level->value, $cases);

        $this->assertContains('emergency', $values);
        $this->assertContains('alert', $values);
        $this->assertContains('critical', $values);
        $this->assertContains('error', $values);
        $this->assertContains('warning', $values);
        $this->assertContains('notice', $values);
        $this->assertContains('info', $values);
        $this->assertContains('debug', $values);
    }
}
