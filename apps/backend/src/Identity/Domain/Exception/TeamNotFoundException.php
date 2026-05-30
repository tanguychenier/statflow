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

/**
 * Raised when a team cannot be resolved or is not visible to the caller. The
 * same 404 is returned for teams the caller is not a member of, so the API never
 * discloses the existence of another team's resources (error catalog: not-found).
 */
final class TeamNotFoundException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::NotFound;
    }

    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Team "%s" was not found.', $id->getValue()));
    }
}
