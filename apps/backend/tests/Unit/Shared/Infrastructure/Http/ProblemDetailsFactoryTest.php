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

use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Infrastructure\Http\ProblemDetailsFactory;
use App\Tests\Unit\Shared\Infrastructure\Http\Fixture\FixedTraceIdProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ProblemDetailsFactory::class)]
final class ProblemDetailsFactoryTest extends TestCase
{
    private ProblemDetailsFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ProblemDetailsFactory(new FixedTraceIdProvider());
    }

    /**
     * The error catalog mandates that `trace_id` is always present and that every
     * `type` URI resolves under the canonical `statflow.io` host. This holds for
     * the entire closed catalogue, with no ad-hoc URIs slipping through.
     */
    #[Test]
    #[DataProvider('everyErrorType')]
    public function everyErrorTypeAlwaysCarriesATraceIdUnderTheStatflowIoHost(ErrorType $errorType): void
    {
        $response = $this->factory->fromErrorType($errorType, detail: 'boom', instance: '/api/v1/x');

        $body = $this->decode($response);

        self::assertSame(FixedTraceIdProvider::TRACE_ID, $body['trace_id']);
        self::assertSame('https://statflow.io/errors/' . $errorType->value, $body['type']);
        self::assertStringStartsWith('https://statflow.io/', $body['type']);
        self::assertSame($errorType->status(), $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itPassesHeadersThroughTheDomainExceptionPath(): void
    {
        $exception = ValidationException::forField('pathname', 'required', 'pathname is required.');

        $response = $this->factory->fromDomainException(
            $exception,
            '/api/v1/sites',
            [
                'Retry-After' => '30',
            ],
        );

        self::assertSame('30', $response->headers->get('Retry-After'));
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $this->decode($response)['trace_id']);
    }

    #[Test]
    public function itInjectsTheTraceIdAndInstanceForAnErrorType(): void
    {
        $response = $this->factory->fromErrorType(
            ErrorType::DependencyUnavailable,
            detail: 'ClickHouse is unreachable.',
            instance: '/api/v1/analytics',
            headers: [
                'Retry-After' => '30',
            ],
        );

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('30', $response->headers->get('Retry-After'));

        $body = $this->decode($response);

        self::assertSame(FixedTraceIdProvider::TRACE_ID, $body['trace_id']);
        self::assertSame('/api/v1/analytics', $body['instance']);
        self::assertSame('https://statflow.io/errors/dependency-unavailable', $body['type']);
    }

    #[Test]
    public function itRendersADomainExceptionWithItsFieldErrors(): void
    {
        $exception = ValidationException::forField('pathname', 'required', 'pathname is required.');

        $response = $this->factory->fromDomainException($exception, '/api/v1/sites');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $body = $this->decode($response);

        self::assertSame('https://statflow.io/errors/validation-failed', $body['type']);
        self::assertSame('Validation Failed', $body['title']);
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $body['trace_id']);
        self::assertIsArray($body['errors']);
        self::assertIsArray($body['errors'][0]);
        self::assertSame('pathname', $body['errors'][0]['field']);
    }

    #[Test]
    public function itRendersAnInternalServerError(): void
    {
        $response = $this->factory->internalServerError('boom', '/api/v1/x');

        $body = $this->decode($response);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('boom', $body['detail']);
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $body['trace_id']);
    }

    /**
     * @return iterable<string, array{ErrorType}>
     */
    public static function everyErrorType(): iterable
    {
        foreach (ErrorType::cases() as $case) {
            yield $case->value => [$case];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
