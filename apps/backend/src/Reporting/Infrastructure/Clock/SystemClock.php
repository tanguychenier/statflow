<?php

declare(strict_types=1);

/*
 * This file is part of Statflow.
 *
 * (c) Tanguy Chénier <tanguychenier@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Reporting\Infrastructure\Clock;

use App\Reporting\Domain\Port\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Real-time {@see Clock} adapter. Always returns UTC so persisted reporting
 * timestamps and cron computations are timezone-stable across hosts.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
