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

namespace App\Tests\Unit\Ingestion\Application;

use App\Ingestion\Application\Service\EventEnricher;
use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Model\GeoLocation;
use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Service\CanonicalEventFactory;
use App\Ingestion\Domain\Service\IdentityHasher;
use App\Ingestion\Domain\Service\ReferrerClassifier;
use App\Ingestion\Domain\Service\SessionWindowResolver;
use App\Ingestion\Domain\Service\UserAgentParser;
use App\Ingestion\Domain\Service\UtmParser;
use App\Ingestion\Domain\Service\WireToCanonicalNormalizer;
use App\Tests\Ingestion\Support\EventFixtures;
use App\Tests\Ingestion\Support\FixedSaltProvider;
use App\Tests\Ingestion\Support\StubGeoLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventEnricher::class)]
final class EventEnricherTest extends TestCase
{
    private EventEnricher $enricher;

    private StubGeoLocator $geo;

    protected function setUp(): void
    {
        $this->geo = new StubGeoLocator();
        $this->enricher = new EventEnricher(
            new IdentityHasher(new FixedSaltProvider()),
            new SessionWindowResolver(),
            $this->geo,
            new UserAgentParser(),
            new ReferrerClassifier(),
            new UtmParser(),
        );
    }

    #[Test]
    public function itProducesAFullyEnrichedRowReadyEvent(): void
    {
        $this->geo->set('203.0.113.5', new GeoLocation('FR', 'Île-de-France', 'Paris'));

        $enriched = $this->enricher->enrich($this->buffered(
            'https://example.com/p?utm_source=newsletter',
            'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari',
            'https://www.google.com/',
        ));

        self::assertSame(EventFixtures::SITE_ID, $enriched->siteId);
        self::assertSame(64, strlen($enriched->identity->visitorId));
        self::assertSame('FR', $enriched->geo->countryCode);
        self::assertSame('Paris', $enriched->geo->city);
        self::assertSame('desktop', $enriched->device->deviceType);
        self::assertSame('Chrome', $enriched->device->browser);
        self::assertSame('google', $enriched->referrerSource);
        self::assertSame('newsletter', $enriched->event->utmSource);
    }

    #[Test]
    public function unknownIpResolvesToEmptyGeoWithoutFailing(): void
    {
        $enriched = $this->enricher->enrich($this->buffered('https://example.com/p', 'Mozilla/5.0 Chrome/1 Safari', null));

        self::assertSame('', $enriched->geo->countryCode);
    }

    #[Test]
    public function identityUsesTheEventDaySalt(): void
    {
        $hasher = new IdentityHasher(new FixedSaltProvider());
        $resolver = new SessionWindowResolver();

        $buffered = $this->buffered('https://example.com/p', 'Mozilla/5.0 Chrome/1 Safari', null);
        $enriched = $this->enricher->enrich($buffered);

        $expected = $hasher->derive(
            EventFixtures::SITE_ID,
            $buffered->context,
            $buffered->event->timestamp->format('Y-m-d'),
            $resolver->resolve($buffered->event->timestamp),
        );

        self::assertSame($expected->visitorId, $enriched->identity->visitorId);
        self::assertSame($expected->sessionId, $enriched->identity->sessionId);
    }

    private function buffered(string $url, string $userAgent, ?string $referrer): BufferedEvent
    {
        $factory = new CanonicalEventFactory();
        $normalizer = new WireToCanonicalNormalizer();

        $overrides = [
            'url' => $url,
        ];
        if ($referrer !== null) {
            $overrides['referrer'] = $referrer;
        }

        $event = $factory->fromCanonical($normalizer->normalize(EventFixtures::canonicalPageview($overrides)));
        $context = new RequestContext('203.0.113.5', $userAgent, 'fr-FR', null);

        return new BufferedEvent(EventFixtures::SITE_ID, $event, $context);
    }
}
