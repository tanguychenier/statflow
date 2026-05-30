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

namespace App\Tests\Unit\Reporting\Fake;

use App\Reporting\Domain\Port\ExportJobDispatcher;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Records dispatched export ids so handler tests can assert a job was enqueued.
 */
final class RecordingExportJobDispatcher implements ExportJobDispatcher
{
    /**
     * @var list<string>
     */
    public array $dispatched = [];

    public function dispatch(Uuid $exportId): void
    {
        $this->dispatched[] = $exportId->getValue();
    }
}
