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

namespace App\Tests\Unit\Analytics\Support;

use App\Analytics\Domain\Port\RealtimeCounterPort;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class FakeRealtimeCounter implements RealtimeCounterPort
{
    public function __construct(
        private ?int $value,
    ) {
    }

    public function currentVisitors(Uuid $siteId): ?int
    {
        return $this->value;
    }
}
