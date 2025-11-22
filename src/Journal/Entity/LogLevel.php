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

namespace Bitrix24\Lib\Journal\Entity;

/**
 * PSR-3 compatible log level enum
 */
enum LogLevel: string
{
    case emergency = 'emergency';
    case alert = 'alert';
    case critical = 'critical';
    case error = 'error';
    case warning = 'warning';
    case notice = 'notice';
    case info = 'info';
    case debug = 'debug';

    /**
     * Creates LogLevel from PSR-3 log level string
     */
    public static function fromPsr3Level(string $level): self
    {
        return match (strtolower($level)) {
            'emergency' => self::emergency,
            'alert' => self::alert,
            'critical' => self::critical,
            'error' => self::error,
            'warning' => self::warning,
            'notice' => self::notice,
            'info' => self::info,
            'debug' => self::debug,
            default => throw new \InvalidArgumentException(
                sprintf('Invalid PSR-3 log level: %s', $level)
            ),
        };
    }
}
