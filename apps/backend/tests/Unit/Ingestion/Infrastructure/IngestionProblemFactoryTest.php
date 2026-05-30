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

namespace App\Tests\Unit\Ingestion\Infrastructure;

use App\Ingestion\Application\Dto\EventResult;
use App\Ingestion\Domain\Exception\EventValidationFailed;
use App\Ingestion\Domain\Exception\FieldViolation;
use App\Ingestion\Domain\Exception\InvalidTrackerKey;
use App\Ingestion\Domain\Exception\RateLimitExceeded;
use App\Ingestion\Infrastructure\Http\Support\IngestionProblemFactory;
use App\Tests\Unit\Shared\Infrastructure\Http\Fixture\FixedTraceIdProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestionProblemFactory::class)]
final class IngestionProblemFactoryTest extends TestCase
{
    private IngestionProblemFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new IngestionProblemFactory(new FixedTraceIdProvider());
    }

    #[Test]
    public function itRendersTheRfc9457ShapeForAnAuthFailure(): void
    {
        $response = $this->factory->fromException(InvalidTrackerKey::unknown(), '/api/v1/events');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $body = $this->decode($response->getContent());
        self::assertSame('https://statflow.io/errors/invalid-tracker-key', $body['type']);
        self::assertSame('Invalid Tracker Key', $body['title']);
        self::assertSame(401, $body['status']);
    }

    #[Test]
    public function everyProblemBodyCarriesTheTraceIdAndInstance(): void
    {
        $response = $this->factory->fromException(InvalidTrackerKey::unknown(), '/api/v1/events');

        $body = $this->decode($response->getContent());
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $body['trace_id']);
        self::assertSame('/api/v1/events', $body['instance']);
    }

    #[Test]
    public function itAttachesPerFieldErrorsForValidationFailures(): void
    {
        $exception = EventValidationFailed::withViolations([
            new FieldViolation('event_name', 'invalid_enum_value', 'bad name'),
            new FieldViolation('seq', 'out_of_range', 'too small'),
        ]);

        $response = $this->factory->fromException($exception, '/api/v1/events');
        $body = $this->decode($response->getContent());

        self::assertSame(422, $response->getStatusCode());
        /** @var list<array<string, mixed>> $errors */
        $errors = $body['errors'];
        self::assertCount(2, $errors);
        self::assertSame('event_name', $errors[0]['field']);
        self::assertSame('invalid_enum_value', $errors[0]['code']);
    }

    #[Test]
    public function itSetsRetryAfterForRateLimits(): void
    {
        $response = $this->factory->fromException(RateLimitExceeded::retryAfter(42));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('42', $response->headers->get('Retry-After'));
    }

    #[Test]
    public function itRendersAPerEventRejectionWithTraceIdAndType(): void
    {
        $response = $this->factory->fromRejection(
            EventResult::rejected(0, 'validation-failed', [
                new FieldViolation('seq', 'out_of_range', 'too small'),
            ]),
            '/api/v1/events',
        );

        self::assertSame(422, $response->getStatusCode());

        $body = $this->decode($response->getContent());
        self::assertSame('https://statflow.io/errors/validation-failed', $body['type']);
        self::assertSame(FixedTraceIdProvider::TRACE_ID, $body['trace_id']);
        self::assertSame('/api/v1/events', $body['instance']);
        /** @var list<array<string, mixed>> $bodyErrors */
        $bodyErrors = $body['errors'];
        self::assertSame('seq', $bodyErrors[0]['field']);
    }

    #[Test]
    public function itRendersAnInvalidTrackerKeyRejectionAs401(): void
    {
        $response = $this->factory->fromRejection(EventResult::rejected(0, 'invalid-tracker-key'), '/api/v1/events');

        self::assertSame(401, $response->getStatusCode());

        $body = $this->decode($response->getContent());
        self::assertSame('https://statflow.io/errors/invalid-tracker-key', $body['type']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string|false $content): array
    {
        self::assertIsString($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
