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

namespace App\Analytics\Domain\Port;

use App\Analytics\Domain\Model\Funnel;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Port (driven side) for reading persisted funnel definitions
 * (`postgres.funnels` / `funnel_steps`). The Analytics context reads funnels to
 * resolve step definitions for the funnel query; funnel CRUD lives elsewhere.
 */
interface FunnelRepositoryPort
{
    public function find(Uuid $siteId, Uuid $funnelId): ?Funnel;
}
