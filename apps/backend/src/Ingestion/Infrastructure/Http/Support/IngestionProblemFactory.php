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

namespace App\Ingestion\Infrastructure\Http\Support;

use App\Ingestion\Application\Dto\EventResult;
use App\Ingestion\Domain\Exception\EventValidationFailed;
use App\Ingestion\Domain\Exception\FieldViolation;
use App\Ingestion\Domain\Exception\IngestionException;
use App\Ingestion\Domain\Exception\InvalidTrackerKey;
use App\Ingestion\Domain\Exception\RateLimitExceeded;
use App\Shared\Domain\Exception\FieldError;
use App\Shared\Domain\Trace\TraceIdProvider;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Renders an IngestionException as an RFC 9457 Problem Details response.
 *
 * Routes through the Shared {@see ApiResponse} builder and {@see TraceIdProvider}
 * so every body carries the request-scoped `trace_id` and `instance` members the
 * error contract mandates (error-catalog.md), instead of hand-building the
 * payload. The per-field `errors` array is attached for validation failures and
 * the `Retry-After` header for rate-limit failures (openapi.yaml responses).
 */
final readonly class IngestionProblemFactory
{
    public function __construct(
        private TraceIdProvider $traceIdProvider,
    ) {
    }

    public function fromException(IngestionException $exception, ?string $instance = null): JsonResponse
    {
        $headers = [];
        if ($exception instanceof RateLimitExceeded) {
            $headers['Retry-After'] = (string) $exception->retryAfterSeconds();
        }

        return ApiResponse::problem(
            status: $exception->status(),
            type: $exception->type(),
            title: $exception->title(),
            detail: $exception->getMessage(),
            traceId: $this->traceIdProvider->current(),
            instance: $instance,
            errors: $this->fieldErrors($exception),
            headers: $headers,
        );
    }

    /**
     * Renders a single-event endpoint's per-event rejection as a top-level
     * Problem Details, reusing the canonical exception for that code so the
     * type/title/status (and trace_id/instance) stay consistent with the batch
     * and request-level error paths.
     */
    public function fromRejection(EventResult $rejection, ?string $instance = null): JsonResponse
    {
        $exception = $rejection->code === 'invalid-tracker-key'
            ? InvalidTrackerKey::unknown()
            : EventValidationFailed::withViolations($rejection->errors);

        return $this->fromException($exception, $instance);
    }

    /**
     * @return list<FieldError>
     */
    private function fieldErrors(IngestionException $exception): array
    {
        if (!$exception instanceof EventValidationFailed) {
            return [];
        }

        return array_map(
            static fn (FieldViolation $violation): FieldError => new FieldError(
                $violation->field,
                $violation->code,
                $violation->message,
            ),
            $exception->violations(),
        );
    }
}
