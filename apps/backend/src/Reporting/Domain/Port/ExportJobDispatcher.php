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

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Driven port that hands a freshly-created export off for asynchronous
 * processing. The Messenger adapter emits a job message consumed by the export
 * worker; the synchronous request returns 202 without blocking on generation.
 */
interface ExportJobDispatcher
{
    public function dispatch(Uuid $exportId): void;
}
