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

namespace App\Tests\Ingestion\Support;

use App\Ingestion\Domain\Port\SaltProviderPort;

/**
 * Deterministic salt provider: a distinct fixed salt per date so identity
 * assertions are reproducible and day-rotation can be exercised.
 */
final class FixedSaltProvider implements SaltProviderPort
{
    public function saltForDate(string $utcDate): string
    {
        return hash('sha256', 'test-salt:' . $utcDate, true);
    }
}
