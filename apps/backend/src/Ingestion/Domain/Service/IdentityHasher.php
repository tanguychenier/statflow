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

use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Model\VisitorIdentity;
use App\Ingestion\Domain\Port\SaltProviderPort;

/**
 * Computes the cookieless visitor and session identifiers exactly as ADR-0008
 * specifies. This is the single implementation of the identity algorithm in the
 * codebase; no other class re-derives it.
 *
 *   visitor_id = HMAC-SHA256(daily_salt || site_id, ip "\n" ua "\n" lang)
 *   session_id = HMAC-SHA256(daily_salt || site_id || "session",
 *                            ip "\n" ua "\n" lang "\n" session_window)
 *
 * The newline delimiter is an unambiguous separator that cannot occur inside the
 * inputs. Cross-site isolation comes solely from mixing site_id into the key.
 */
final readonly class IdentityHasher
{
    private const SESSION_DOMAIN_TAG = 'session';

    private const DELIMITER = "\n";

    public function __construct(
        private SaltProviderPort $saltProvider,
    ) {
    }

    /**
     * @param int $sessionWindow floor(unix_seconds / 60) of the session's first
     *                           event — the minute bucket the session started in
     *                           (ADR-0008 §2)
     */
    public function derive(
        string $siteId,
        RequestContext $context,
        string $utcDate,
        int $sessionWindow,
    ): VisitorIdentity {
        $salt = $this->saltProvider->saltForDate($utcDate);

        $data = $context->ipAddress
            . self::DELIMITER . $context->userAgent
            . self::DELIMITER . $context->acceptLanguage;

        $visitorId = hash_hmac('sha256', $data, $salt . $siteId);

        $sessionId = hash_hmac(
            'sha256',
            $data . self::DELIMITER . $sessionWindow,
            $salt . $siteId . self::SESSION_DOMAIN_TAG,
        );

        return new VisitorIdentity($visitorId, $sessionId);
    }
}
