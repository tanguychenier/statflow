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

namespace App\Analytics\Application\Query;

use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Runs a saved funnel over a date range (OpenAPI `queryFunnel`). The funnel's
 * steps are resolved from persistence by `funnelId`; the query never carries
 * them inline.
 */
final readonly class FunnelQuery
{
    public function __construct(
        public Uuid $siteId,
        public Uuid $funnelId,
        public DateRange $dateRange,
        public int $conversionWindowDays,
        public FilterSet $inlineFilters,
        public ?Uuid $segmentId,
    ) {
    }
}
