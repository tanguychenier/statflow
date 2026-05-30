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

use App\Ingestion\Application\Service\EventEnricher;
use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Model\EnrichedEvent;
use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Service\CanonicalEventFactory;
use App\Ingestion\Domain\Service\IdentityHasher;
use App\Ingestion\Domain\Service\ReferrerClassifier;
use App\Ingestion\Domain\Service\SessionWindowResolver;
use App\Ingestion\Domain\Service\UserAgentParser;
use App\Ingestion\Domain\Service\UtmParser;
use App\Ingestion\Domain\Service\WireToCanonicalNormalizer;
use App\Ingestion\Infrastructure\ClickHouse\ClickHouseRowMapper;
use App\Tests\Ingestion\Support\EventFixtures;
use App\Tests\Ingestion\Support\FixedSaltProvider;
use App\Tests\Ingestion\Support\StubGeoLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClickHouseRowMapper::class)]
final class ClickHouseRowMapperTest extends TestCase
{
    private ClickHouseRowMapper $mapper;

    private StubGeoLocator $geo;

    private EventEnricher $enricher;

    protected function setUp(): void
    {
        $this->mapper = new ClickHouseRowMapper();
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
    public function itMapsCoreColumnsWithTheClickHouseTimestampFormat(): void
    {
        $row = $this->mapper->toRow($this->enrich());

        self::assertSame(EventFixtures::SITE_ID, $row['site_id']);
        self::assertSame(EventFixtures::EVENT_ID, $row['event_id']);
        self::assertSame('pageview', $row['event_name']);
        self::assertSame('2025-06-15 14:30:00.123', $row['timestamp']);
        self::assertIsString($row['visitor_id']);
        self::assertSame(64, strlen($row['visitor_id']));
    }

    #[Test]
    public function itPromotesRevenueAndCurrencyOutOfTheProperties(): void
    {
        $row = $this->mapper->toRow($this->enrich([
            'event_name' => 'custom',
            'custom_properties' => [
                'plan' => 'pro',
                'revenue' => 19.9,
                'currency' => 'EUR',
            ],
        ]));

        self::assertSame('19.9', $row['revenue']);
        self::assertSame('EUR', $row['currency']);
        self::assertSame([
            'plan' => 'pro',
        ], $row['properties']);
    }

    #[Test]
    public function itCoercesPropertyValuesToTheirCanonicalStringForm(): void
    {
        $row = $this->mapper->toRow($this->enrich([
            'event_name' => 'custom',
            'custom_properties' => [
                'flag' => true,
                'off' => false,
                'count' => 7,
                'ratio' => 1.5,
                'label' => 'x',
            ],
        ]));

        /** @var array<string, string> $properties */
        $properties = $row['properties'];
        self::assertSame('true', $properties['flag']);
        self::assertSame('false', $properties['off']);
        self::assertSame('7', $properties['count']);
        self::assertSame('1.5', $properties['ratio']);
        self::assertSame('x', $properties['label']);
    }

    #[Test]
    public function itFormatsFloatsAsTheShortestRoundTrippableDecimal(): void
    {
        // 0.1 has no exact binary representation; a naive cast under a high
        // serialize_precision yields 0.10000000000000001. The shortest decimal
        // that round-trips is "0.1", and the result must not depend on the ini.
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '17');

        try {
            $row = $this->mapper->toRow($this->enrich([
                'event_name' => 'custom',
                'custom_properties' => [
                    'a' => 0.1,
                    'b' => 0.3,
                    'c' => 1.005,
                    'd' => 100.0,
                    'revenue' => 19.99,
                ],
            ]));
        } finally {
            ini_set('serialize_precision', $previous === false ? '-1' : $previous);
        }

        /** @var array<string, string> $floatProperties */
        $floatProperties = $row['properties'];
        self::assertSame('0.1', $floatProperties['a']);
        self::assertSame('0.3', $floatProperties['b']);
        self::assertSame('1.005', $floatProperties['c']);
        self::assertSame('100', $floatProperties['d']);
        self::assertSame('19.99', $row['revenue']);
    }

    #[Test]
    public function everyFloatStringRoundTripsBackToTheSameValue(): void
    {
        foreach ([0.1, 0.2, 0.3, 1.005, 2.675, 1.0E-5, 1234567.891, -0.0001] as $value) {
            $row = $this->mapper->toRow($this->enrich([
                'event_name' => 'custom',
                'custom_properties' => [
                    'v' => $value,
                ],
            ]));

            /** @var array<string, string> $rowProperties */
            $rowProperties = $row['properties'];
            self::assertSame(
                $value,
                (float) $rowProperties['v'],
                sprintf('%s must round-trip', var_export($value, true)),
            );
            self::assertStringNotContainsStringIgnoringCase('e', $rowProperties['v']);
        }
    }

    #[Test]
    public function itMapsBehavioralColumnsAndRageClickAsZeroOrOne(): void
    {
        $row = $this->mapper->toRow($this->enrich([
            'event_name' => 'click',
            'behavioral' => [
                'click_x' => 742,
                'click_y' => 318,
                'click_x_pct' => 51.5,
                'scroll_depth_pct' => 42,
                'engagement_time_ms' => 8340,
                'is_rage_click' => true,
                'element_selector' => '.cta',
                'element_text' => 'Buy',
            ],
            'viewport_width' => 1440,
        ]));

        self::assertSame(742, $row['click_x']);
        self::assertSame(318, $row['click_y']);
        self::assertSame(51.5, $row['click_x_pct']);
        self::assertSame(42, $row['scroll_depth_pct']);
        self::assertSame(8340, $row['engagement_time_ms']);
        self::assertSame(1, $row['is_rage_click']);
        self::assertSame('.cta', $row['element_selector']);
        self::assertSame(1440, $row['viewport_width']);
    }

    #[Test]
    public function rageClickDefaultsToZeroForNonInteractionEvents(): void
    {
        $row = $this->mapper->toRow($this->enrich());

        self::assertSame(0, $row['is_rage_click']);
        self::assertNull($row['click_x']);
    }

    #[Test]
    public function itDefaultsNullableScalarColumnsForClickHouse(): void
    {
        $row = $this->mapper->toRow($this->enrich());

        self::assertSame('', $row['referrer']);
        self::assertSame('', $row['utm_source']);
        self::assertSame(0, $row['screen_width']);
        self::assertSame('', $row['language']);
        self::assertSame('', $row['currency']);
        self::assertNull($row['revenue']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function enrich(array $overrides = []): EnrichedEvent
    {
        $factory = new CanonicalEventFactory();
        $normalizer = new WireToCanonicalNormalizer();
        $event = $factory->fromCanonical($normalizer->normalize(EventFixtures::canonicalPageview($overrides)));
        $context = new RequestContext('203.0.113.5', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari', 'fr-FR', null);

        return $this->enricher->enrich(new BufferedEvent(EventFixtures::SITE_ID, $event, $context));
    }
}
