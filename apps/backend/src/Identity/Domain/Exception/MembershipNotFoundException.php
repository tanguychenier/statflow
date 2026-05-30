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
use App\Shared\Domain\ValueObject\Uuid;

final class MembershipNotFoundException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::NotFound;
    }

    public static function forUserInTeam(Uuid $userId, Uuid $teamId): self
    {
        return new self(sprintf(
            'User "%s" is not a member of team "%s".',
            $userId->getValue(),
            $teamId->getValue(),
        ));
    }
}
