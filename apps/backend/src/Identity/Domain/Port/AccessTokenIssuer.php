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

namespace App\Identity\Domain\Port;

use App\Identity\Domain\ValueObject\AccessToken;
use App\Identity\Domain\ValueObject\TeamMembershipClaim;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Issues short-lived signed access tokens (ES256, 15-minute TTL — api/README.md
 * §2.1). The signing algorithm and key handling are infrastructure concerns.
 */
interface AccessTokenIssuer
{
    /**
     * @param list<TeamMembershipClaim> $teams
     */
    public function issue(Uuid $userId, array $teams): AccessToken;
}
