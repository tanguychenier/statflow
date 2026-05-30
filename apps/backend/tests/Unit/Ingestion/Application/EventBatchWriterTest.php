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

use App\Ingestion\Application\Service\EventBatchWriter;
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
use App\Tests\Ingestion\Support\InMemoryEventWriter;
use App\Tests\Ingestion\Support\InMemorySiteRepository;
use App\Tests\Ingestion\Support\StubGeoLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventBatchWriter::class)]
final class EventBatchWriterTest extends TestCase
{
    private StubGeoLocator $geo;

    private EventEnricher $enricher;

    private InMemoryEventWriter $writer;

    private InMemorySiteRepository $sites;

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
        $this->writer = new InMemoryEventWriter();
        $this->sites = new InMemorySiteRepository();
    }

    #[Test]
    public function itEnrichesAndWritesABatchInOneInsert(): void
    {
        $this->sites->add(EventFixtures::site());

        $this->writer()->write([$this->buffered(), $this->buffered('22222222-2222-4222-8222-222222222222')]);

        self::assertCount(1, $this->writer->batches);
        self::assertCount(2, $this->writer->allEvents());
    }

    #[Test]
    public function itDropsEventsFromExcludedCountriesAfterGeoResolution(): void
    {
        $this->sites->add(EventFixtures::site([
            'excludedCountries' => ['FR'],
        ]));
        $this->geo->set('203.0.113.5', new GeoLocation('FR', 'Île-de-France', 'Paris'));

        $this->writer()->write([$this->buffered()]);

        self::assertSame([], $this->writer->allEvents());
    }

    #[Test]
    public function itKeepsEventsFromNonExcludedCountries(): void
    {
        $this->sites->add(EventFixtures::site([
            'excludedCountries' => ['US'],
        ]));
        $this->geo->set('203.0.113.5', new GeoLocation('FR', 'Île-de-France', 'Paris'));

        $this->writer()->write([$this->buffered()]);

        self::assertCount(1, $this->writer->allEvents());
    }

    #[Test]
    public function itDoesNotCallTheWriterForAnEmptyResult(): void
    {
        $this->sites->add(EventFixtures::site([
            'excludedCountries' => ['FR'],
        ]));
        $this->geo->set('203.0.113.5', new GeoLocation('FR', '', ''));

        $this->writer()->write([$this->buffered()]);

        self::assertSame([], $this->writer->batches);
    }

    private function writer(): EventBatchWriter
    {
        return new EventBatchWriter($this->enricher, $this->writer, $this->sites);
    }

    private function buffered(string $eventId = EventFixtures::EVENT_ID): BufferedEvent
    {
        $factory = new CanonicalEventFactory();
        $normalizer = new WireToCanonicalNormalizer();
        $event = $factory->fromCanonical($normalizer->normalize(
            EventFixtures::canonicalPageview([
                'event_id' => $eventId,
            ]),
        ));
        $context = new RequestContext('203.0.113.5', 'Mozilla/5.0 Chrome/120 Safari', 'fr', null);

        return new BufferedEvent(EventFixtures::SITE_ID, $event, $context);
    }
}
