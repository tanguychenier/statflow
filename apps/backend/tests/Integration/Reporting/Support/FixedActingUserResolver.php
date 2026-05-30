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

namespace App\Tests\Integration\Reporting\Support;

use App\Reporting\Infrastructure\Http\ActingUserResolver;

/**
 * Test double for the security-backed acting-user resolver. Lets controller
 * integration tests assert behaviour without booting the auth firewall: the
 * returned id is mutated between requests to simulate different callers.
 */
final class FixedActingUserResolver implements ActingUserResolver
{
    public static string $userId = '11111111-1111-4111-8111-111111111111';

    public function userId(): string
    {
        return self::$userId;
    }
}
