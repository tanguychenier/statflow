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

namespace App\Analytics\Domain\Model;

/**
 * How a {@see FunnelStep} matches an event (`postgres.goal_trigger_type`).
 */
enum FunnelTriggerType: string
{
    case Pageview = 'pageview';
    case Event = 'event';
}
