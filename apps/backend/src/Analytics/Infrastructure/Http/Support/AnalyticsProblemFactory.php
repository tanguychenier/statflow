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

namespace App\Analytics\Infrastructure\Http\Support;

use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Infrastructure\Http\ProblemDetailsFactory;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Maps Analytics failures to RFC 9457 Problem Details responses.
 *
 * Domain failures carry their own canonical {@see ErrorType} (status, type URI,
 * title); a malformed JSON body or a bad `{site_id}` surfaces as an
 * {@see InvalidArgumentException} and maps to a 400. All rendering goes through
 * the Shared {@see ProblemDetailsFactory} so every body carries the request
 * `trace_id` and `instance`, and the type host stays the documented one.
 */
final readonly class AnalyticsProblemFactory
{
    public function __construct(
        private ProblemDetailsFactory $problemDetails,
    ) {
    }

    public function fromThrowable(\Throwable $exception, ?string $instance = null): JsonResponse
    {
        return match (true) {
            $exception instanceof DomainException => $this->problemDetails->fromDomainException($exception, $instance),
            $exception instanceof InvalidArgumentException => $this->problemDetails->fromErrorType(
                ErrorType::MalformedJson,
                detail: $exception->getMessage(),
                instance: $instance,
            ),
            default => throw $exception,
        };
    }
}
