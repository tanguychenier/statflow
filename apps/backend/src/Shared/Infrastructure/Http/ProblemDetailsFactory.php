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

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Domain\Exception\FieldError;
use App\Shared\Domain\Trace\TraceIdProvider;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Request-aware factory for RFC 9457 Problem Details responses.
 *
 * This is the single source of truth for error bodies: every context renders its
 * failures through this factory rather than calling {@see ApiResponse} directly,
 * which guarantees the two request-scoped members the error catalog mandates are
 * always present — the `trace_id` (from {@see TraceIdProvider}) and the `instance`
 * URI. The `type` host therefore always resolves to the canonical `statflow.io`
 * catalogue and the body always carries a correlation id.
 *
 * @see docs/api/error-catalog.md
 */
final readonly class ProblemDetailsFactory
{
    public function __construct(
        private TraceIdProvider $traceIdProvider
    ) {
    }

    /**
     * @param list<FieldError>     $errors
     * @param array<array-key, mixed> $headers
     */
    public function fromErrorType(
        ErrorType $errorType,
        string $detail = '',
        ?string $instance = null,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        return ApiResponse::fromErrorType(
            $errorType,
            detail: $detail,
            traceId: $this->traceIdProvider->current(),
            instance: $instance,
            errors: $errors,
            headers: $headers,
        );
    }

    /**
     * Render a self-describing domain exception, honouring its status, type,
     * title, and any attached field errors. Extra headers (e.g. `Retry-After`
     * on a rate-limit failure) are passed through unchanged.
     *
     * @param array<array-key, mixed> $headers
     */
    public function fromDomainException(
        DomainException $exception,
        ?string $instance = null,
        array $headers = [],
    ): JsonResponse {
        return ApiResponse::problem(
            status: $exception->getStatusCode(),
            type: $exception->getType(),
            title: $exception->getTitle(),
            detail: $exception->getMessage(),
            traceId: $this->traceIdProvider->current(),
            instance: $instance,
            errors: $exception->getErrors(),
            headers: $headers,
        );
    }

    public function internalServerError(string $detail, ?string $instance = null): JsonResponse
    {
        return ApiResponse::internalServerError(
            detail: $detail,
            traceId: $this->traceIdProvider->current(),
            instance: $instance,
        );
    }
}
