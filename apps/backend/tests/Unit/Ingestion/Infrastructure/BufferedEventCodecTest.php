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

use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Model\EventName;
use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Service\CanonicalEventFactory;
use App\Ingestion\Domain\Service\WireToCanonicalNormalizer;
use App\Ingestion\Infrastructure\Redis\BufferedEventCodec;
use App\Tests\Ingestion\Support\EventFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferedEventCodec::class)]
final class BufferedEventCodecTest extends TestCase
{
    private BufferedEventCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new BufferedEventCodec();
    }

    #[Test]
    public function itRoundTripsAFullEvent(): void
    {
        $original = $this->buffered();

        $decoded = $this->codec->decode($this->codec->encode($original));

        self::assertSame($original->siteId, $decoded->siteId);
        self::assertSame($original->event->eventId, $decoded->event->eventId);
        self::assertSame(EventName::Click, $decoded->event->eventName);
        self::assertSame(
            $original->event->timestamp->format('Y-m-d\TH:i:s.v\Z'),
            $decoded->event->timestamp->format('Y-m-d\TH:i:s.v\Z'),
        );
        self::assertSame(10, $decoded->event->behavioral->clickX);
        self::assertSame(20, $decoded->event->behavioral->clickY);
        self::assertTrue($decoded->event->behavioral->isRageClick);
        self::assertSame([
            'plan' => 'pro',
            'revenue' => 19.9,
            'currency' => 'EUR',
        ], $decoded->event->customProperties);
    }

    #[Test]
    public function itPreservesTheRequestContext(): void
    {
        $decoded = $this->codec->decode($this->codec->encode($this->buffered()));

        self::assertSame('203.0.113.5', $decoded->context->ipAddress);
        self::assertSame('fr-FR', $decoded->context->acceptLanguage);
        self::assertSame('https://example.com', $decoded->context->origin);
    }

    #[Test]
    public function itRoundTripsAnEventWithoutBehavioralOrProps(): void
    {
        $factory = new CanonicalEventFactory();
        $normalizer = new WireToCanonicalNormalizer();
        $event = $factory->fromCanonical($normalizer->normalize(EventFixtures::canonicalPageview()));
        $context = new RequestContext('1.2.3.4', 'UA', 'en', null);
        $buffered = new BufferedEvent(EventFixtures::SITE_ID, $event, $context);

        $decoded = $this->codec->decode($this->codec->encode($buffered));

        self::assertTrue($decoded->event->behavioral->isEmpty());
        self::assertSame([], $decoded->event->customProperties);
        self::assertNull($decoded->context->origin);
    }

    private function buffered(): BufferedEvent
    {
        $factory = new CanonicalEventFactory();
        $normalizer = new WireToCanonicalNormalizer();

        $event = $factory->fromCanonical($normalizer->normalize(EventFixtures::wirePageview([
            'e' => 'click',
            'b' => [
                'cx' => 10,
                'cy' => 20,
                'rc' => true,
            ],
            'props' => [
                'plan' => 'pro',
                'revenue' => 19.9,
                'currency' => 'EUR',
            ],
        ])));

        $context = new RequestContext('203.0.113.5', 'Mozilla/5.0 Chrome/120 Safari', 'fr-FR', 'https://example.com');

        return new BufferedEvent(EventFixtures::SITE_ID, $event, $context);
    }
}
