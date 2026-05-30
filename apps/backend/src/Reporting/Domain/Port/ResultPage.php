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

/**
 * A single page of aggregates plus the cursor for the following page.
 * `nextCursor` is null when the page is the last one.
 *
 * @template T of object
 */
final readonly class ResultPage
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
