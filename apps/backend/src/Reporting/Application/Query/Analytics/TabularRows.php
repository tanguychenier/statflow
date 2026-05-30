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

use App\Shared\Domain\Bus\Query\QueryResult;

/**
 * Result of a {@see FetchExportRowsQuery}: the export's rows as a list of
 * column => scalar maps, ready for tabular serialisation.
 */
final readonly class TabularRows implements QueryResult
{
    /**
     * @param list<array<string, scalar|null>> $rows
     */
    public function __construct(
        public array $rows,
    ) {
    }
}
