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
use App\Shared\Domain\Exception\FieldError;
use App\Shared\Infrastructure\Http\ApiResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ApiResponse::class)]
final class ApiResponseTest extends TestCase
{
    #[Test]
    public function itReturnsAProblemDetailsResponse(): void
    {
        $response = ApiResponse::problem(
            status: Response::HTTP_BAD_REQUEST,
            type: 'https://statflow.io/errors/malformed-json',
            title: 'Malformed JSON',
            detail: 'Some detail',
            traceId: '01HXK2Q4B7T8NMPZW6RDYGJ5FM',
            instance: '/api/v1/sites',
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $body = $this->decode($response);

        self::assertSame('https://statflow.io/errors/malformed-json', $body['type']);
        self::assertSame('Malformed JSON', $body['title']);
        self::assertSame(Response::HTTP_BAD_REQUEST, $body['status']);
        self::assertSame('Some detail', $body['detail']);
        self::assertSame('/api/v1/sites', $body['instance']);
        self::assertSame('01HXK2Q4B7T8NMPZW6RDYGJ5FM', $body['trace_id']);
    }

    #[Test]
    public function itOmitsEmptyOptionalMembers(): void
    {
        $response = ApiResponse::problem(
            status: Response::HTTP_NOT_FOUND,
            type: 'https://statflow.io/errors/not-found',
            title: 'Not Found',
            traceId: '01HXK2Q4B7T8NMPZW6RDYGJ5FM',
        );

        $body = $this->decode($response);

        self::assertArrayNotHasKey('detail', $body);
        self::assertArrayNotHasKey('instance', $body);
        self::assertArrayNotHasKey('errors', $body);
        self::assertArrayHasKey('trace_id', $body);
    }

    #[Test]
    public function itSerialisesFieldErrors(): void
    {
        $response = ApiResponse::problem(
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            type: 'https://statflow.io/errors/validation-failed',
            title: 'Validation Failed',
            traceId: '01HXK2Q4B7T8NMPZW6RDYGJ5FM',
            errors: [new FieldError('pathname', 'required', 'pathname is required.')],
        );

        $body = $this->decode($response);

        self::assertSame([
            [
                'field' => 'pathname',
                'code' => 'required',
                'message' => 'pathname is required.',
            ],
        ], $body['errors']);
    }

    #[Test]
    public function itBuildsFromErrorType(): void
    {
        $response = ApiResponse::fromErrorType(
            ErrorType::RateLimitExceeded,
            traceId: '01HXK2Q4B7T8NMPZW6RDYGJ5FM',
            headers: [
                'Retry-After' => '30',
            ],
        );

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('30', $response->headers->get('Retry-After'));

        $body = $this->decode($response);

        self::assertSame('https://statflow.io/errors/rate-limit-exceeded', $body['type']);
        self::assertSame('Rate Limit Exceeded', $body['title']);
    }

    #[Test]
    public function itReturnsA500InternalServerError(): void
    {
        $response = ApiResponse::internalServerError(traceId: '01HXK2Q4B7T8NMPZW6RDYGJ5FM');

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $body = $this->decode($response);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $body['status']);
        self::assertSame('https://statflow.io/errors/internal-error', $body['type']);
    }

    #[Test]
    public function itReturnsNoContent(): void
    {
        $response = ApiResponse::noContent();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', (string) $response->getContent());
    }

    #[Test]
    public function itReturnsJsonSuccess(): void
    {
        $response = ApiResponse::json([
            'id' => '42',
        ], Response::HTTP_CREATED);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame([
            'id' => '42',
        ], $this->decode($response));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
