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

namespace App\Reporting\Domain\Port;

use DateTimeImmutable;

/**
 * Driven port abstracting the current wall-clock time so report timestamps,
 * cron computations and export lifecycle transitions stay deterministic under
 * test. The infrastructure adapter returns the real UTC time.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
