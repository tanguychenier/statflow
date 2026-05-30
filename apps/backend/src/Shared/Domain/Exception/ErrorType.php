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

namespace App\Shared\Domain\Exception;

/**
 * The closed catalogue of RFC 9457 error types.
 *
 * Each case maps one-to-one to an entry in `docs/api/error-catalog.md`: the slug
 * becomes the dereferenceable `type` URI and is paired with a stable HTTP status
 * and human title. Centralising this here guarantees every context emits exactly
 * the documented contract — there are no ad-hoc URIs anywhere in the codebase.
 */
enum ErrorType: string
{
    case AuthenticationRequired = 'authentication-required';
    case InvalidCredentials = 'invalid-credentials';
    case TokenExpired = 'token-expired';
    case TokenRevoked = 'token-revoked';
    case InvalidApiKey = 'invalid-api-key';
    case InvalidTrackerKey = 'invalid-tracker-key';
    case OriginNotAllowed = 'origin-not-allowed';
    case PermissionDenied = 'permission-denied';
    case ApiKeyScopeInsufficient = 'api-key-scope-insufficient';
    case SiteAccessDenied = 'site-access-denied';
    case NotFound = 'not-found';
    case ResourceDeleted = 'resource-deleted';
    case Conflict = 'conflict';
    case ValidationFailed = 'validation-failed';
    case MalformedJson = 'malformed-json';
    case UnsupportedContentType = 'unsupported-content-type';
    case InvalidFilter = 'invalid-filter';
    case InvalidDateRange = 'invalid-date-range';
    case FunnelStepsInvalid = 'funnel-steps-invalid';
    case RateLimitExceeded = 'rate-limit-exceeded';
    case EventPayloadTooLarge = 'event-payload-too-large';
    case InternalError = 'internal-error';
    case DependencyUnavailable = 'dependency-unavailable';
    case QueryTimeout = 'query-timeout';

    /**
     * Base URI for all dereferenceable error type identifiers.
     */
    public const BASE_URI = 'https://statflow.io/errors/';

    public function uri(): string
    {
        return self::BASE_URI . $this->value;
    }

    public function title(): string
    {
        return match ($this) {
            self::AuthenticationRequired => 'Authentication Required',
            self::InvalidCredentials => 'Invalid Credentials',
            self::TokenExpired => 'Token Expired',
            self::TokenRevoked => 'Token Revoked',
            self::InvalidApiKey => 'Invalid API Key',
            self::InvalidTrackerKey => 'Invalid Tracker Key',
            self::OriginNotAllowed => 'Origin Not Allowed',
            self::PermissionDenied => 'Permission Denied',
            self::ApiKeyScopeInsufficient => 'API Key Scope Insufficient',
            self::SiteAccessDenied => 'Site Access Denied',
            self::NotFound => 'Not Found',
            self::ResourceDeleted => 'Resource Deleted',
            self::Conflict => 'Conflict',
            self::ValidationFailed => 'Validation Failed',
            self::MalformedJson => 'Malformed JSON',
            self::UnsupportedContentType => 'Unsupported Content Type',
            self::InvalidFilter => 'Invalid Filter',
            self::InvalidDateRange => 'Invalid Date Range',
            self::FunnelStepsInvalid => 'Funnel Steps Invalid',
            self::RateLimitExceeded => 'Rate Limit Exceeded',
            self::EventPayloadTooLarge => 'Event Payload Too Large',
            self::InternalError => 'Internal Server Error',
            self::DependencyUnavailable => 'Dependency Unavailable',
            self::QueryTimeout => 'Query Timeout',
        };
    }

    public function status(): int
    {
        return match ($this) {
            self::AuthenticationRequired,
            self::InvalidCredentials,
            self::TokenExpired,
            self::TokenRevoked,
            self::InvalidApiKey,
            self::InvalidTrackerKey,
            self::OriginNotAllowed => 401,
            self::PermissionDenied,
            self::ApiKeyScopeInsufficient,
            self::SiteAccessDenied => 403,
            self::NotFound => 404,
            self::Conflict => 409,
            self::ResourceDeleted => 410,
            self::EventPayloadTooLarge => 413,
            self::UnsupportedContentType => 415,
            self::ValidationFailed,
            self::InvalidFilter,
            self::InvalidDateRange,
            self::FunnelStepsInvalid => 422,
            self::RateLimitExceeded => 429,
            self::MalformedJson => 400,
            self::InternalError => 500,
            self::DependencyUnavailable => 503,
            self::QueryTimeout => 504,
        };
    }
}
