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

namespace App\Ingestion\Infrastructure\Salt;

use App\Ingestion\Domain\Port\SaltProviderPort;
use InvalidArgumentException;

/**
 * Derives the daily salt deterministically from the per-deployment install
 * secret and the UTC date (ADR-0008 §3, identity-and-privacy.md §3.2):
 *
 *   daily_salt = HKDF-SHA256(install_secret, "statflow-daily-salt", utc_date)
 *
 * The salt is never persisted: it lives only in this object's in-memory cache
 * for the life of the worker and is recomputed (not stored) per date. Because
 * the date is an input, the previous day's salt becomes unreproducible without
 * re-supplying that date — it rotates and is gone at the UTC day boundary. All
 * horizontally-scaled workers derive the same salt with no shared store.
 */
final class HkdfSaltProvider implements SaltProviderPort
{
    private const INFO = 'statflow-daily-salt';

    private const SALT_BYTES = 32;

    /**
     * @var array<string, string> in-memory only — never persisted
     */
    private array $cache = [];

    public function __construct(
        private readonly string $installSecret,
    ) {
        if ($installSecret === '') {
            throw new InvalidArgumentException('The install secret must not be empty.');
        }
    }

    public function saltForDate(string $utcDate): string
    {
        if (!isset($this->cache[$utcDate])) {
            $this->cache[$utcDate] = hash_hkdf(
                'sha256',
                $this->installSecret,
                self::SALT_BYTES,
                self::INFO,
                $utcDate,
            );
        }

        return $this->cache[$utcDate];
    }
}
