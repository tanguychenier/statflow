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

namespace App\Ingestion\Domain\Service;

/**
 * Outcome of an origin-allowlist check (@see OriginAuthorizer).
 *
 * The distinction matters for observability: {@see self::BootstrapAllowAll} is
 * the deliberately permissive path taken while a site has no allowlist yet, and
 * the application layer logs it so operators can see that a site is still in its
 * insecure first-run state (postgres-schema.sql §5: empty allowlist = allow all,
 * "not recommended for production").
 */
enum OriginDecision
{
    /**
     * The request Origin matched a configured allowlist entry.
     */
    case Matched;

    /**
     * No allowlist configured: permissive bootstrap default (gated and logged).
     */
    case BootstrapAllowAll;
}
