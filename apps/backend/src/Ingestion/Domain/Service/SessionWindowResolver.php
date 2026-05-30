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

namespace App\Ingestion\Domain\Service;

use DateTimeImmutable;

/**
 * Resolves the `session_window` value fed into the session HMAC, exactly as
 * ADR-0008 §2 specifies:
 *
 *   session_window = floor(unix_seconds / 60)
 *
 * i.e. the one-minute bucket the event falls in. Stateless ingestion has no
 * per-visitor session store, so the literal minute bucket is used directly; the
 * 30-minute inactivity boundary (event-contract.md §7) is reconstructed
 * downstream by the ClickHouse sessions_mv from event timestamps, not here.
 */
final class SessionWindowResolver
{
    private const SECONDS_PER_MINUTE = 60;

    public function resolve(DateTimeImmutable $eventTimestamp): int
    {
        return intdiv($eventTimestamp->getTimestamp(), self::SECONDS_PER_MINUTE);
    }
}
