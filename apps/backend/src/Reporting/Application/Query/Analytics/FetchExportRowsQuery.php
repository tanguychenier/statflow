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

namespace App\Reporting\Application\Query\Analytics;

use App\Shared\Domain\Bus\Query\Query;

/**
 * Cross-context read dispatched on the `query.bus` to obtain an export's result
 * rows from the Analytics context. The message is owned by Reporting (so the
 * dependency points Reporting -> Shared, never Reporting -> Analytics) and is
 * answered by an Analytics-side handler the wiring agent registers.
 */
final readonly class FetchExportRowsQuery implements Query
{
    /**
     * @param array<string, mixed> $query serialised Analytics query definition
     */
    public function __construct(
        public string $siteId,
        public string $reportType,
        public array $query,
    ) {
    }
}
