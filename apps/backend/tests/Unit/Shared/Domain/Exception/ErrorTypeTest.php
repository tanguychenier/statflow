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

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\ErrorType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorType::class)]
final class ErrorTypeTest extends TestCase
{
    #[Test]
    #[DataProvider('catalogProvider')]
    public function itMatchesTheFrozenErrorCatalog(ErrorType $type, string $slug, int $status, string $title): void
    {
        self::assertSame($slug, $type->value);
        self::assertSame('https://statflow.io/errors/' . $slug, $type->uri());
        self::assertSame($status, $type->status());
        self::assertSame($title, $type->title());
    }

    /**
     * Mirrors docs/api/error-catalog.md "Error Type Quick Reference".
     *
     * @return iterable<string, array{0: ErrorType, 1: string, 2: int, 3: string}>
     */
    public static function catalogProvider(): iterable
    {
        yield 'authentication-required' => [ErrorType::AuthenticationRequired, 'authentication-required', 401, 'Authentication Required'];
        yield 'invalid-credentials' => [ErrorType::InvalidCredentials, 'invalid-credentials', 401, 'Invalid Credentials'];
        yield 'token-expired' => [ErrorType::TokenExpired, 'token-expired', 401, 'Token Expired'];
        yield 'token-revoked' => [ErrorType::TokenRevoked, 'token-revoked', 401, 'Token Revoked'];
        yield 'invalid-api-key' => [ErrorType::InvalidApiKey, 'invalid-api-key', 401, 'Invalid API Key'];
        yield 'invalid-tracker-key' => [ErrorType::InvalidTrackerKey, 'invalid-tracker-key', 401, 'Invalid Tracker Key'];
        yield 'origin-not-allowed' => [ErrorType::OriginNotAllowed, 'origin-not-allowed', 401, 'Origin Not Allowed'];
        yield 'permission-denied' => [ErrorType::PermissionDenied, 'permission-denied', 403, 'Permission Denied'];
        yield 'api-key-scope-insufficient' => [ErrorType::ApiKeyScopeInsufficient, 'api-key-scope-insufficient', 403, 'API Key Scope Insufficient'];
        yield 'site-access-denied' => [ErrorType::SiteAccessDenied, 'site-access-denied', 403, 'Site Access Denied'];
        yield 'not-found' => [ErrorType::NotFound, 'not-found', 404, 'Not Found'];
        yield 'resource-deleted' => [ErrorType::ResourceDeleted, 'resource-deleted', 410, 'Resource Deleted'];
        yield 'conflict' => [ErrorType::Conflict, 'conflict', 409, 'Conflict'];
        yield 'validation-failed' => [ErrorType::ValidationFailed, 'validation-failed', 422, 'Validation Failed'];
        yield 'malformed-json' => [ErrorType::MalformedJson, 'malformed-json', 400, 'Malformed JSON'];
        yield 'unsupported-content-type' => [ErrorType::UnsupportedContentType, 'unsupported-content-type', 415, 'Unsupported Content Type'];
        yield 'invalid-filter' => [ErrorType::InvalidFilter, 'invalid-filter', 422, 'Invalid Filter'];
        yield 'invalid-date-range' => [ErrorType::InvalidDateRange, 'invalid-date-range', 422, 'Invalid Date Range'];
        yield 'funnel-steps-invalid' => [ErrorType::FunnelStepsInvalid, 'funnel-steps-invalid', 422, 'Funnel Steps Invalid'];
        yield 'rate-limit-exceeded' => [ErrorType::RateLimitExceeded, 'rate-limit-exceeded', 429, 'Rate Limit Exceeded'];
        yield 'event-payload-too-large' => [ErrorType::EventPayloadTooLarge, 'event-payload-too-large', 413, 'Event Payload Too Large'];
        yield 'internal-error' => [ErrorType::InternalError, 'internal-error', 500, 'Internal Server Error'];
        yield 'dependency-unavailable' => [ErrorType::DependencyUnavailable, 'dependency-unavailable', 503, 'Dependency Unavailable'];
        yield 'query-timeout' => [ErrorType::QueryTimeout, 'query-timeout', 504, 'Query Timeout'];
    }

    #[Test]
    public function everyCaseIsCovered(): void
    {
        $covered = array_map(
            static fn (array $row): ErrorType => $row[0],
            iterator_to_array(self::catalogProvider()),
        );

        self::assertEqualsCanonicalizing(ErrorType::cases(), array_values($covered));
    }

    #[Test]
    public function allTypeUrisAreUnique(): void
    {
        $uris = array_map(static fn (ErrorType $t): string => $t->uri(), ErrorType::cases());

        self::assertSame($uris, array_unique($uris));
    }
}
