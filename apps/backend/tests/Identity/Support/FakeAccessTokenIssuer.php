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

namespace App\Tests\Identity\Support;

use App\Identity\Domain\Port\AccessTokenIssuer;
use App\Identity\Domain\ValueObject\AccessToken;
use App\Identity\Domain\ValueObject\TeamMembershipClaim;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Fake issuer producing a predictable token string and recording the claims it
 * was asked to embed, so handler tests can assert the team claims without parsing
 * a real JWT.
 */
final class FakeAccessTokenIssuer implements AccessTokenIssuer
{
    public ?Uuid $lastUserId = null;

    /**
     * @var list<TeamMembershipClaim>
     */
    public array $lastTeams = [];

    public function issue(Uuid $userId, array $teams): AccessToken
    {
        $this->lastUserId = $userId;
        $this->lastTeams = $teams;

        return new AccessToken('jwt-for-' . $userId->getValue(), 900);
    }
}
