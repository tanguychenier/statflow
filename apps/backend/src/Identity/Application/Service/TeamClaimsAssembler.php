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

namespace App\Identity\Application\Service;

use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\ValueObject\TeamMembershipClaim;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Builds the JWT `teams` claim from a user's accepted memberships so voters can
 * authorise without a database round-trip (api/README.md §2.1). Pending
 * invitations are excluded — they grant no access until accepted.
 */
final readonly class TeamClaimsAssembler
{
    public function __construct(
        private TeamMembershipRepository $memberships,
    ) {
    }

    /**
     * @return list<TeamMembershipClaim>
     */
    public function forUser(Uuid $userId): array
    {
        $claims = [];

        foreach ($this->memberships->findByUser($userId) as $membership) {
            if ($membership->isPending()) {
                continue;
            }

            $claims[] = $this->toClaim($membership);
        }

        return $claims;
    }

    private function toClaim(TeamMembership $membership): TeamMembershipClaim
    {
        return new TeamMembershipClaim($membership->teamId(), $membership->role());
    }
}
