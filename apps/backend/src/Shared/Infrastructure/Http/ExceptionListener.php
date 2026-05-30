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
use App\Shared\Domain\Trace\TraceIdProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Converts every unhandled throwable into an RFC 9457 Problem Details response.
 *
 * Mapping:
 *   - {@see DomainException}        → its self-declared status/type/title/errors;
 *   - {@see HttpExceptionInterface} → mapped onto a canonical {@see ErrorType};
 *   - anything else                → 500, logged with the request `trace_id`.
 *
 * Only `/api/` (and health) routes are rewritten; other responses are left to
 * the framework so non-API surfaces keep their default behaviour.
 */
final readonly class ExceptionListener
{
    public function __construct(
        private ProblemDetailsFactory $problemDetails,
        private TraceIdProvider $traceIdProvider,
        private LoggerInterface $logger,
        private bool $debug,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $instance = $event->getRequest()->getRequestUri();

        if ($exception instanceof DomainException) {
            $event->setResponse($this->problemDetails->fromDomainException($exception, $instance));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse($this->renderHttpException($exception, $instance));

            return;
        }

        $this->logger->error('Unhandled exception', [
            'trace_id' => $this->traceIdProvider->current(),
            'exception' => $exception,
            'message' => $exception->getMessage(),
        ]);

        $detail = $this->debug ? $exception->getMessage() : 'An unexpected error occurred.';
        $event->setResponse($this->problemDetails->internalServerError($detail, $instance));
    }

    /**
     * Render an HTTP exception preserving its real status code. Known statuses
     * reuse the canonical {@see ErrorType} type/title; unmapped statuses fall back
     * to a generic `http-{status}` type so the body still conforms to RFC 9457.
     */
    private function renderHttpException(HttpExceptionInterface $exception, string $instance): JsonResponse
    {
        $status = $exception->getStatusCode();
        $errorType = $this->mapHttpStatus($status);
        $detail = $exception instanceof NotFoundHttpException ? '' : $exception->getMessage();

        if ($errorType !== null) {
            return $this->problemDetails->fromErrorType(
                $errorType,
                detail: $detail,
                instance: $instance,
                headers: $exception->getHeaders(),
            );
        }

        return ApiResponse::problem(
            status: $status,
            type: ErrorType::BASE_URI . 'http-' . $status,
            title: Response::$statusTexts[$status] ?? 'HTTP Error',
            detail: $detail,
            traceId: $this->traceIdProvider->current(),
            instance: $instance,
            headers: $exception->getHeaders(),
        );
    }

    /**
     * Map an HTTP status to a canonical error type when one exists with the
     * **same** status code. Returns null for statuses without a catalog entry
     * (e.g. 405), so the caller renders a generic type without altering the code.
     */
    private function mapHttpStatus(int $status): ?ErrorType
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED => ErrorType::AuthenticationRequired,
            Response::HTTP_FORBIDDEN => ErrorType::PermissionDenied,
            Response::HTTP_NOT_FOUND => ErrorType::NotFound,
            Response::HTTP_CONFLICT => ErrorType::Conflict,
            Response::HTTP_GONE => ErrorType::ResourceDeleted,
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE => ErrorType::UnsupportedContentType,
            Response::HTTP_REQUEST_ENTITY_TOO_LARGE => ErrorType::EventPayloadTooLarge,
            Response::HTTP_TOO_MANY_REQUESTS => ErrorType::RateLimitExceeded,
            Response::HTTP_UNPROCESSABLE_ENTITY => ErrorType::ValidationFailed,
            Response::HTTP_SERVICE_UNAVAILABLE => ErrorType::DependencyUnavailable,
            Response::HTTP_GATEWAY_TIMEOUT => ErrorType::QueryTimeout,
            default => null,
        };
    }
}
