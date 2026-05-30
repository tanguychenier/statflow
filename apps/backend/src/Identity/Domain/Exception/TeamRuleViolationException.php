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

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\ErrorType;

/**
 * Invariant violations on team membership: there must always be an owner, the
 * sole owner cannot be demoted or removed, a user cannot be invited twice, and a
 * personal team cannot be deleted. All map to a 409 conflict.
 */
final class TeamRuleViolationException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::Conflict;
    }

    public static function alreadyMember(): self
    {
        return new self('This user is already a member of the team.');
    }

    public static function ownerNotAssignable(): self
    {
        return new self('The owner role cannot be assigned through an invitation; transfer ownership instead.');
    }

    public static function cannotDemoteSoleOwner(): self
    {
        return new self('The team must always have an owner; assign another owner first.');
    }

    public static function cannotRemoveSoleOwner(): self
    {
        return new self('The team owner cannot be removed; transfer ownership first.');
    }

    public static function cannotDeletePersonalTeam(): self
    {
        return new self('A personal team cannot be deleted.');
    }
}
