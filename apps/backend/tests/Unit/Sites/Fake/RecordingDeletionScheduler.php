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

namespace App\Tests\Unit\Sites\Fake;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\Port\SiteDataDeletionScheduler;

final class RecordingDeletionScheduler implements SiteDataDeletionScheduler
{
    /**
     * @var list<string>
     */
    public array $scheduled = [];

    public function scheduleDeletion(Uuid $siteId): void
    {
        $this->scheduled[] = $siteId->getValue();
    }
}
