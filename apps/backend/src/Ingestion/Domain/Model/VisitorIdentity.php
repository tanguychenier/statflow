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

namespace App\Ingestion\Domain\Model;

/**
 * The pair of salted, server-derived identifiers from ADR-0008. Both are
 * 64-character lowercase hex HMAC-SHA256 digests; neither is reversible without
 * the daily salt, which is never persisted.
 */
final readonly class VisitorIdentity
{
    public function __construct(
        public string $visitorId,
        public string $sessionId,
    ) {
    }
}
