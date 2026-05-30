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

namespace App\Analytics\Domain\Query;

/**
 * Which physical table a filter/breakdown compiles against. Event-grained
 * filters read `events`; session-grained dimensions (entry/exit page) read
 * `sessions`.
 */
enum Grain
{
    case Events;
    case Sessions;
}
