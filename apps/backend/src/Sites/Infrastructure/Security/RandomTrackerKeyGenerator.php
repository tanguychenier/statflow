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

namespace App\Sites\Infrastructure\Security;

use App\Sites\Domain\Port\TrackerKeyGenerator;
use App\Sites\Domain\ValueObject\TrackerKey;

/**
 * Mints `stk_` keys from 24 CSPRNG bytes encoded as URL-safe base64 without
 * padding (32 characters of suffix, ~144 bits of entropy). URL-safe so the key
 * is copy-paste clean into a snippet and never needs escaping.
 */
final class RandomTrackerKeyGenerator implements TrackerKeyGenerator
{
    private const ENTROPY_BYTES = 24;

    public function generate(): TrackerKey
    {
        $suffix = rtrim(strtr(base64_encode(random_bytes(self::ENTROPY_BYTES)), '+/', '-_'), '=');

        return TrackerKey::fromString(TrackerKey::PREFIX . $suffix);
    }
}
