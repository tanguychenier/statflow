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

use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Domain\Exception\FieldError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Low-level factory for API responses.
 *
 * Builds RFC 9457 Problem Details bodies and the standard success shapes. This
 * class is stateless and framework-static; request-scoped concerns (the
 * `trace_id`, the `instance` URI) are supplied by the caller — typically
 * {@see ProblemDetailsFactory}, which sources them from request context.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9457
 */
final class ApiResponse
{
    public const PROBLEM_CONTENT_TYPE = 'application/problem+json';

    public const JSON_CONTENT_TYPE = 'application/json';

    /**
     * Build an RFC 9457 Problem Details error response.
     *
     * @param list<FieldError>     $errors     per-field validation errors (422/400)
     * @param array<string, mixed> $extensions additional problem-details members
     * @param array<array-key, mixed> $headers    extra response headers (e.g. Retry-After)
     */
    public static function problem(
        int $status,
        string $type,
        string $title,
        string $detail = '',
        ?string $traceId = null,
        ?string $instance = null,
        array $errors = [],
        array $extensions = [],
        array $headers = [],
    ): JsonResponse {
        $body = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $instance,
            'trace_id' => $traceId,
        ];

        if ($errors !== []) {
            $body['errors'] = array_map(static fn (FieldError $e): array => $e->toArray(), $errors);
        }

        $body = array_filter(
            array_merge($body, $extensions),
            static fn (mixed $v): bool => $v !== '' && $v !== null,
        );

        return new JsonResponse(
            $body,
            $status,
            array_merge($headers, [
                'Content-Type' => self::PROBLEM_CONTENT_TYPE,
            ]),
        );
    }

    /**
     * Build a Problem Details response from a canonical {@see ErrorType}.
     *
     * @param list<FieldError>     $errors
     * @param array<array-key, mixed> $headers
     */
    public static function fromErrorType(
        ErrorType $errorType,
        string $detail = '',
        ?string $traceId = null,
        ?string $instance = null,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        return self::problem(
            status: $errorType->status(),
            type: $errorType->uri(),
            title: $errorType->title(),
            detail: $detail,
            traceId: $traceId,
            instance: $instance,
            errors: $errors,
            headers: $headers,
        );
    }

    /**
     * Generic 500 response used by the exception listener for unhandled faults.
     */
    public static function internalServerError(
        string $detail = 'An unexpected error occurred.',
        ?string $traceId = null,
        ?string $instance = null,
    ): JsonResponse {
        return self::fromErrorType(
            ErrorType::InternalError,
            detail: $detail,
            traceId: $traceId,
            instance: $instance,
        );
    }

    /**
     * Successful write with no body (event ingestion, deletes).
     */
    public static function noContent(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Successful JSON read/write.
     *
     * @param array<string, mixed>|list<mixed> $data
     */
    public static function json(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status, [
            'Content-Type' => self::JSON_CONTENT_TYPE,
        ]);
    }
}
