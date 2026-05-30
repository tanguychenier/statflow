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

namespace App\Sites\Domain\Exception;

use App\Shared\Domain\Exception\ErrorType;
use App\Sites\Domain\ValueObject\Hostname;

/**
 * Raised when registering or renaming a site to a domain already used by another
 * active site in the same team. Maps to HTTP 409 (error-catalog `conflict`):
 * the unique partial index `sites_domain_team_unique` enforces the same rule.
 */
final class DuplicateSiteDomainException extends SitesDomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::Conflict;
    }

    public static function inTeam(Hostname $domain): self
    {
        return new self(sprintf('A site with domain "%s" already exists in this team.', $domain->value()));
    }
}
