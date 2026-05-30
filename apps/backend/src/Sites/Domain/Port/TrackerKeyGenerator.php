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

namespace App\Sites\Domain\Port;

use App\Sites\Domain\ValueObject\TrackerKey;

/**
 * Driven port that mints fresh public tracker keys (`stk_…`).
 *
 * The generation strategy (CSPRNG, encoding, suffix length) lives in
 * Infrastructure; the domain only requires a syntactically valid, unguessable
 * key. Uniqueness across sites is guaranteed by the repository, not here.
 */
interface TrackerKeyGenerator
{
    public function generate(): TrackerKey;
}
