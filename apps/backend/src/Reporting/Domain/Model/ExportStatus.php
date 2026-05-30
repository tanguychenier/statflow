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

namespace App\Reporting\Domain\Model;

/**
 * Lifecycle state of an async export job, per the OpenAPI `Export.status` enum.
 *
 * The state machine is linear: Pending -> Processing -> (Completed | Failed).
 * Terminal states never transition again.
 */
enum ExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => $next === self::Processing || $next === self::Failed,
            self::Processing => $next === self::Completed || $next === self::Failed,
            self::Completed, self::Failed => false,
        };
    }
}
