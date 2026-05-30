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

use App\Analytics\Domain\Model\Funnel;
use App\Analytics\Domain\Port\FunnelRepositoryPort;
use App\Shared\Domain\ValueObject\Uuid;

final class InMemoryFunnelRepository implements FunnelRepositoryPort
{
    /**
     * @var array<string, Funnel> keyed by funnel id
     */
    private array $funnels = [];

    public function add(Funnel $funnel): void
    {
        $this->funnels[(string) $funnel->id] = $funnel;
    }

    public function find(Uuid $siteId, Uuid $funnelId): ?Funnel
    {
        $funnel = $this->funnels[(string) $funnelId] ?? null;

        return $funnel !== null && $funnel->siteId->equals($siteId) ? $funnel : null;
    }
}
