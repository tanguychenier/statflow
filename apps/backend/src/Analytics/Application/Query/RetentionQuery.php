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
use App\Analytics\Domain\ValueObject\RetentionInterval;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Runs a cohort retention analysis (OpenAPI `queryRetention`): visitors are
 * grouped by their first-seen period, retention measured by return activity in
 * later periods.
 */
final readonly class RetentionQuery
{
    public function __construct(
        public Uuid $siteId,
        public DateRange $dateRange,
        public RetentionInterval $interval,
        public string $returnEvent,
    ) {
    }
}
