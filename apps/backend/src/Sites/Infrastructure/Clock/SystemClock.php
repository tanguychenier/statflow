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

namespace App\Sites\Infrastructure\Clock;

use App\Sites\Domain\Port\Clock;
use DateTimeImmutable;

/**
 * Wall-clock adapter. Always produces a UTC instant so persisted timestamps
 * match the TIMESTAMPTZ-stored-as-UTC convention of the PostgreSQL schema.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
