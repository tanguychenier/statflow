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

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Infrastructure\Http\ExceptionListener;
use App\Shared\Infrastructure\Http\ProblemDetailsFactory;
use App\Tests\Unit\Shared\Infrastructure\Http\Fixture\FixedTraceIdProvider;
use App\Tests\Unit\Shared\Infrastructure\Http\Fixture\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

#[CoversClass(ExceptionListener::class)]
final class ExceptionListenerTest extends TestCase
{
    #[Test]
    public function itRendersADomainExceptionWithItsDeclaredStatus(): void
    {
        $event = $this->dispatch(NotFoundException::of('Site', 'abc'), '/api/v1/sites/abc');

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $body = $this->decode($event);
        self::assertSame('https://statflow.io/errors/not-found', $body['type']);
        self::assertSame('/api/v1/sites/abc', $body['instance']);
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $body['trace_id']);
    }

    #[Test]
    public function itRendersValidationFieldErrors(): void
    {
        $exception = ValidationException::forField('pathname', 'required', 'pathname is required.');
        $event = $this->dispatch($exception, '/api/v1/events');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $event->getResponse()?->getStatusCode());

        $body = $this->decode($event);
        self::assertIsArray($body['errors']);
        self::assertIsArray($body['errors'][0]);
        self::assertSame('pathname', $body['errors'][0]['field']);
    }

    #[Test]
    public function itMapsAKnownHttpExceptionToTheCanonicalType(): void
    {
        $event = $this->dispatch(new ServiceUnavailableHttpException(30, 'down'), '/api/v1/analytics');

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $event->getResponse()?->getStatusCode());

        $body = $this->decode($event);
        self::assertSame('https://statflow.io/errors/dependency-unavailable', $body['type']);
    }

    #[Test]
    public function itMapsUnauthorizedToAuthenticationRequired(): void
    {
        $event = $this->dispatch(new UnauthorizedHttpException('Bearer'), '/api/v1/sites');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()?->getStatusCode());

        $body = $this->decode($event);
        self::assertSame('https://statflow.io/errors/authentication-required', $body['type']);
    }

    #[Test]
    public function itPreservesUnmappedHttpStatusWithAGenericType(): void
    {
        $event = $this->dispatch(new MethodNotAllowedHttpException(['POST']), '/api/v1/ingest');

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $event->getResponse()?->getStatusCode());

        $body = $this->decode($event);
        self::assertSame('https://statflow.io/errors/http-405', $body['type']);
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $body['trace_id']);
    }

    #[Test]
    public function itHidesUnexpectedExceptionDetailsOutsideDebug(): void
    {
        $event = $this->dispatch(new RuntimeException('secret stack detail'), '/api/v1/x', debug: false);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $event->getResponse()?->getStatusCode());

        $body = $this->decode($event);
        self::assertSame('An unexpected error occurred.', $body['detail']);
        self::assertSame('https://statflow.io/errors/internal-error', $body['type']);
    }

    #[Test]
    public function itExposesExceptionDetailsInDebug(): void
    {
        $event = $this->dispatch(new RuntimeException('secret stack detail'), '/api/v1/x', debug: true);

        self::assertSame('secret stack detail', $this->decode($event)['detail']);
    }

    #[Test]
    public function itLogsUnexpectedExceptionsWithTheTraceId(): void
    {
        $logger = new RecordingLogger();
        $event = $this->dispatch(new RuntimeException('boom'), '/api/v1/x', logger: $logger);

        self::assertNotNull($event->getResponse());
        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::ERROR, $logger->records[0]['level']);
        self::assertSame('Unhandled exception', $logger->records[0]['message']);
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $logger->records[0]['context']['trace_id']);
    }

    private function dispatch(
        Throwable $exception,
        string $uri,
        bool $debug = false,
        ?LoggerInterface $logger = null,
    ): ExceptionEvent {
        $traceProvider = new FixedTraceIdProvider();

        $listener = new ExceptionListener(
            new ProblemDetailsFactory($traceProvider),
            $traceProvider,
            $logger ?? new NullLogger(),
            $debug,
        );

        $event = new ExceptionEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create($uri),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        $listener->onKernelException($event);

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ExceptionEvent $event): array
    {
        $response = $event->getResponse();
        self::assertNotNull($response);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
