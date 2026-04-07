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
 * We use a dedicated enum here instead of a plain string:
 * - Only PSR-3 levels are allowed;
 * - Invalid values are impossible at the type level;
 * - Doctrine can map the value cleanly to the DB.
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
}
