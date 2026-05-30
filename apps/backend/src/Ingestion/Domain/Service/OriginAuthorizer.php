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

use App\Ingestion\Domain\Exception\OriginNotAllowed;
use App\Ingestion\Domain\Model\Site;

/**
 * Enforces the per-site domain allowlist (ADR-0009 §1, openapi.yaml
 * `allowed_domains`). The allowlist is matched against the request Origin's
 * host. Wildcards of the form `*.example.com` match any single-or-multi-label
 * subdomain but not the bare apex.
 *
 * An empty allowlist is the permissive **bootstrap** default: a freshly created
 * site (whose `site_settings.allowed_domains` defaults to `{}`,
 * postgres-schema.sql §5) accepts any origin so the tracker works the moment the
 * snippet is installed, before the operator has configured the allowlist. The
 * spec documents this as "not recommended for production"; this class therefore
 * reports the permissive path back to the caller as
 * {@see OriginDecision::BootstrapAllowAll} so the application layer can log it
 * rather than letting it pass silently. Once any entry is configured, every
 * request must match — and a request with no Origin header (server-side
 * integrations, sendBeacon in some browsers) can no longer be verified and is
 * rejected.
 */
final class OriginAuthorizer
{
    public function assertAllowed(Site $site, ?string $origin): OriginDecision
    {
        if ($site->allowsAllOrigins()) {
            return OriginDecision::BootstrapAllowAll;
        }

        $host = $this->hostFromOrigin($origin);
        if ($host === null) {
            throw OriginNotAllowed::forOrigin($origin ?? '');
        }

        foreach ($site->allowedDomains as $pattern) {
            if ($this->matches($host, strtolower(trim($pattern)))) {
                return OriginDecision::Matched;
            }
        }

        throw OriginNotAllowed::forOrigin($origin ?? '');
    }

    private function hostFromOrigin(?string $origin): ?string
    {
        if ($origin === null || trim($origin) === '') {
            return null;
        }

        // The Origin header is scheme://host[:port]; a bare host is also tolerated.
        $host = parse_url($origin, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = preg_replace('/:\d+$/', '', $origin);
        }

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private function matches(string $host, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        if (str_starts_with($pattern, '*.')) {
            $suffix = substr($pattern, 1); // ".example.com"

            return str_ends_with($host, $suffix) && $host !== substr($suffix, 1);
        }

        return $host === $pattern;
    }
}
