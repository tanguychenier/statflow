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
 * The request-inherent signals the ingestion pipeline reads off the HTTP
 * request: the client IP (resolved by the controller from X-Forwarded-For when
 * behind the first-party proxy), the User-Agent, the Accept-Language, and the
 * Origin.
 *
 * These three identity inputs (ip, userAgent, acceptLanguage) feed the HMAC in
 * ADR-0008 and are never persisted (identity-and-privacy.md §6).
 */
final readonly class RequestContext
{
    public function __construct(
        public string $ipAddress,
        public string $userAgent,
        public string $acceptLanguage,
        public ?string $origin,
    ) {
    }
}
