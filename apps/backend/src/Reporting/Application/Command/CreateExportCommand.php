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

namespace App\Reporting\Application\Command;

/**
 * Request an asynchronous data export for a site. The handler persists the job
 * as `pending` and dispatches it for background processing; the response is the
 * job view, polled later for completion.
 */
final readonly class CreateExportCommand
{
    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public string $format,
        public array $query,
        public ?string $notifyEmail,
        public string $reportType = 'breakdown',
    ) {
    }
}
