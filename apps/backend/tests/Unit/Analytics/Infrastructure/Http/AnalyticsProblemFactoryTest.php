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

namespace App\Tests\Unit\Analytics\Infrastructure\Http;

use App\Analytics\Domain\Exception\FunnelNotFound;
use App\Analytics\Domain\Exception\InvalidDateRange;
use App\Analytics\Domain\Exception\InvalidFilter;
use App\Analytics\Domain\Exception\SegmentNotFound;
use App\Analytics\Infrastructure\Http\Support\AnalyticsProblemFactory;
use App\Shared\Domain\Exception\ErrorType;
use App\Tests\Unit\Analytics\Support\AnalyticsProblemFactoryFactory;
use App\Tests\Unit\Analytics\Support\FixedTraceIdProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(AnalyticsProblemFactory::class)]
final class AnalyticsProblemFactoryTest extends TestCase
{
    private AnalyticsProblemFactory $factory;

    protected function setUp(): void
    {
        $this->factory = AnalyticsProblemFactoryFactory::create();
    }

    #[Test]
    public function notFoundDomainErrorsBecome404(): void
    {
        $response = $this->factory->fromThrowable(SegmentNotFound::withId('x'));
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('https://statflow.io/errors/not-found', $this->decode($response)['type']);

        $response = $this->factory->fromThrowable(FunnelNotFound::withId('y'));
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function otherDomainErrorsBecome422(): void
    {
        $response = $this->factory->fromThrowable(InvalidDateRange::orderInverted());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function invalidArgumentsBecome400(): void
    {
        $response = $this->factory->fromThrowable(new InvalidArgumentException('bad json'));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('https://statflow.io/errors/malformed-json', $this->decode($response)['type']);
    }

    #[Test]
    public function everyErrorBodyUsesTheDocumentedTypeHost(): void
    {
        $cases = [
            InvalidDateRange::orderInverted(),
            InvalidFilter::missingField('property'),
            SegmentNotFound::withId('x'),
            new InvalidArgumentException('bad json'),
        ];

        foreach ($cases as $exception) {
            $decoded = $this->decode($this->factory->fromThrowable($exception));
            self::assertIsString($decoded['type']);
            $type = $decoded['type'];

            self::assertStringStartsWith(ErrorType::BASE_URI, $type);
            self::assertStringContainsString('statflow.io', $type);
            self::assertStringNotContainsString('statflow.dev', $type);
        }
    }

    #[Test]
    public function traceIdIsAlwaysPresentInTheBody(): void
    {
        $domain = $this->decode($this->factory->fromThrowable(InvalidDateRange::orderInverted(), '/api/v1/analytics/s/aggregate'));
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $domain['trace_id']);
        self::assertSame('/api/v1/analytics/s/aggregate', $domain['instance']);

        $malformed = $this->decode($this->factory->fromThrowable(new InvalidArgumentException('bad json'), '/api/v1/analytics/s/breakdown'));
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $malformed['trace_id']);
        self::assertSame('/api/v1/analytics/s/breakdown', $malformed['instance']);
    }

    #[Test]
    public function unexpectedThrowablesAreRethrown(): void
    {
        $this->expectException(RuntimeException::class);

        $this->factory->fromThrowable(new RuntimeException('boom'));
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
