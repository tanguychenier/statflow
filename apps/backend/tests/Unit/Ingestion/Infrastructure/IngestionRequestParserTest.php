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

use App\Ingestion\Domain\Exception\MalformedRequest;
use App\Ingestion\Domain\Exception\PayloadTooLarge;
use App\Ingestion\Infrastructure\Http\Support\IngestionRequestParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(IngestionRequestParser::class)]
#[CoversClass(MalformedRequest::class)]
#[CoversClass(PayloadTooLarge::class)]
final class IngestionRequestParserTest extends TestCase
{
    private IngestionRequestParser $parser;

    protected function setUp(): void
    {
        $this->parser = new IngestionRequestParser();
    }

    #[Test]
    public function itWrapsASingleEventIntoAOneElementList(): void
    {
        $events = $this->parser->parseEvents($this->request((string) json_encode([
            'e' => 'pageview',
        ])), isBatch: false);

        self::assertCount(1, $events);
        self::assertSame('pageview', $events[0]['e']);
    }

    #[Test]
    public function itUnwrapsTheBatchEnvelope(): void
    {
        $body = json_encode([
            'events' => [[
                'e' => 'a',
            ], [
                'e' => 'b',
            ]],
            'sent_at' => 1,
            'sdk' => 'tracker',
        ]);

        $events = $this->parser->parseEvents($this->request((string) $body), isBatch: true);

        self::assertCount(2, $events);
    }

    #[Test]
    public function itDecodesAGzipBodyFromServerIntegrations(): void
    {
        $body = (string) gzencode((string) json_encode([
            'e' => 'pageview',
        ]));

        $events = $this->parser->parseEvents(
            $this->request($body, [
                'Content-Encoding' => 'gzip',
            ]),
            isBatch: false,
        );

        self::assertCount(1, $events);
    }

    #[Test]
    public function itParsesJsonRegardlessOfContentType(): void
    {
        $events = $this->parser->parseEvents(
            $this->request((string) json_encode([
                'e' => 'pageview',
            ]), [
                'Content-Type' => 'text/plain',
            ]),
            isBatch: false,
        );

        self::assertCount(1, $events);
    }

    #[Test]
    public function itRejectsAnOversizeSingleEventWith413(): void
    {
        $this->expectException(PayloadTooLarge::class);

        $this->parser->parseEvents(
            $this->request((string) json_encode([
                'x' => str_repeat('a', 17 * 1024),
            ])),
            isBatch: false,
        );
    }

    #[Test]
    public function itRejectsAnOversizeBatchWith413(): void
    {
        $this->expectException(PayloadTooLarge::class);

        $this->parser->parseEvents(
            $this->request((string) json_encode([
                'events' => [[
                    'x' => str_repeat('a', 257 * 1024),
                ]],
            ])),
            isBatch: true,
        );
    }

    #[Test]
    public function itRejectsMoreThan100Events(): void
    {
        $body = (string) json_encode([
            'events' => array_fill(0, 101, [
                'e' => 'a',
            ]),
        ]);

        $this->expectException(PayloadTooLarge::class);

        $this->parser->parseEvents($this->request($body), isBatch: true);
    }

    #[Test]
    public function itRejectsInvalidJson(): void
    {
        $this->expectException(MalformedRequest::class);

        $this->parser->parseEvents($this->request('{not json'), isBatch: false);
    }

    #[Test]
    public function itRejectsAnEmptyBody(): void
    {
        $this->expectException(MalformedRequest::class);

        $this->parser->parseEvents($this->request('   '), isBatch: false);
    }

    #[Test]
    public function itRejectsAnEmptyEventsEnvelope(): void
    {
        $this->expectException(MalformedRequest::class);

        $this->parser->parseEvents($this->request((string) json_encode([
            'events' => [],
        ])), isBatch: true);
    }

    #[Test]
    public function itRejectsANonArrayEventsKey(): void
    {
        $this->expectException(MalformedRequest::class);

        $this->parser->parseEvents($this->request((string) json_encode([
            'events' => 'oops',
        ])), isBatch: true);
    }

    #[Test]
    public function itBuildsTheRequestContextFromHeaders(): void
    {
        $request = $this->request('{}', [
            'User-Agent' => 'Mozilla/5.0',
            'Accept-Language' => 'fr-FR',
            'Origin' => 'https://example.com',
        ]);
        $request->server->set('REMOTE_ADDR', '203.0.113.9');

        $context = $this->parser->buildContext($request);

        self::assertSame('203.0.113.9', $context->ipAddress);
        self::assertSame('Mozilla/5.0', $context->userAgent);
        self::assertSame('fr-FR', $context->acceptLanguage);
        self::assertSame('https://example.com', $context->origin);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $body, array $headers = []): Request
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new Request([], [], [], [], [], $server, $body);
    }
}
