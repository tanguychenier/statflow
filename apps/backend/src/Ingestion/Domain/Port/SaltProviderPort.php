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

namespace App\Ingestion\Domain\Port;

/**
 * Driven port: supplies the daily salt for identity hashing (ADR-0008 §3).
 *
 * The salt is derived deterministically from the install secret and a UTC date
 * via HKDF, lives only in process memory, and rotates at the UTC day boundary.
 * The date is an explicit argument so late-arriving events are bucketed by
 * their own event-day salt (ADR-0008 §3, "Lifetime").
 */
interface SaltProviderPort
{
    /**
     * @param string $utcDate the event-day in `Y-m-d` form (UTC)
     *
     * @return string raw 32-byte (256-bit) salt for that day
     */
    public function saltForDate(string $utcDate): string;
}
