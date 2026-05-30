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

namespace App\Tests\Unit\Ingestion\Domain\Service;

use App\Ingestion\Domain\Service\WireToCanonicalNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WireToCanonicalNormalizer::class)]
final class WireToCanonicalNormalizerTest extends TestCase
{
    private WireToCanonicalNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new WireToCanonicalNormalizer();
    }

    #[Test]
    public function itRenamesEveryTopLevelWireKey(): void
    {
        $canonical = $this->normalizer->normalize([
            'eid' => 'id',
            'k' => 'stk_x',
            'e' => 'click',
            'seq' => 3,
            'u' => 'https://e.com/p',
            'p' => '/p',
            'h' => 'e.com',
            'r' => 'https://ref',
            't' => 'Title',
            'vw' => 1440,
            'vh' => 900,
            'sw' => 2560,
            'sh' => 1600,
            'dpr' => 2.0,
            'ct' => '4g',
            'tv' => '1.0.0',
            'lang' => 'fr',
            'tz' => 'Europe/Paris',
        ]);

        self::assertSame('id', $canonical['event_id']);
        self::assertSame('stk_x', $canonical['site_key']);
        self::assertSame('click', $canonical['event_name']);
        self::assertSame('https://e.com/p', $canonical['url']);
        self::assertSame('/p', $canonical['pathname']);
        self::assertSame('e.com', $canonical['hostname']);
        self::assertSame('https://ref', $canonical['referrer']);
        self::assertSame('Title', $canonical['title']);
        self::assertSame(1440, $canonical['viewport_width']);
        self::assertSame(900, $canonical['viewport_height']);
        self::assertSame(2560, $canonical['screen_width']);
        self::assertSame(1600, $canonical['screen_height']);
        self::assertSame(2.0, $canonical['device_pixel_ratio']);
        self::assertSame('4g', $canonical['connection_type']);
        self::assertSame('1.0.0', $canonical['tracker_version']);
        self::assertSame('fr', $canonical['language']);
        self::assertSame('Europe/Paris', $canonical['timezone']);
    }

    #[Test]
    public function itConvertsUnixMillisecondsToIso8601Utc(): void
    {
        $canonical = $this->normalizer->normalize([
            'ts' => 1749997800123,
        ]);

        self::assertSame('2025-06-15T14:30:00.123Z', $canonical['timestamp']);
    }

    #[Test]
    public function itPadsSubSecondMillisecondsToThreeDigits(): void
    {
        $canonical = $this->normalizer->normalize([
            'ts' => 1749997800007,
        ]);

        self::assertSame('2025-06-15T14:30:00.007Z', $canonical['timestamp']);
    }

    #[Test]
    public function itLeavesAnIso8601StringTimestampUntouched(): void
    {
        $canonical = $this->normalizer->normalize([
            'ts' => '2025-06-15T14:30:00.123Z',
        ]);

        self::assertSame('2025-06-15T14:30:00.123Z', $canonical['timestamp']);
    }

    #[Test]
    public function itMapsBehavioralShortKeys(): void
    {
        $canonical = $this->normalizer->normalize([
            'b' => [
                'cx' => 742,
                'cy' => 318,
                'cxp' => 51.5,
                'cyp' => 35.3,
                'etag' => 'button',
                'etxt' => 'Buy',
                'esel' => '.cta',
                'eid' => 'buy-btn',
                'sd' => 42,
                'sdpx' => 1200,
                'em' => 8340,
                'rc' => true,
            ],
        ]);

        /** @var array<string, mixed> $behavioral */
        $behavioral = $canonical['behavioral'];
        self::assertSame(742, $behavioral['click_x']);
        self::assertSame(318, $behavioral['click_y']);
        self::assertSame(51.5, $behavioral['click_x_pct']);
        self::assertSame(35.3, $behavioral['click_y_pct']);
        self::assertSame('button', $behavioral['element_tag']);
        self::assertSame('Buy', $behavioral['element_text']);
        self::assertSame('.cta', $behavioral['element_selector']);
        self::assertSame('buy-btn', $behavioral['element_id']);
        self::assertSame(42, $behavioral['scroll_depth_pct']);
        self::assertSame(1200, $behavioral['scroll_depth_px']);
        self::assertSame(8340, $behavioral['engagement_time_ms']);
        self::assertTrue($behavioral['is_rage_click']);
    }

    #[Test]
    public function itMapsPropsToCustomProperties(): void
    {
        $canonical = $this->normalizer->normalize([
            'props' => [
                'plan' => 'pro',
            ],
        ]);

        self::assertSame([
            'plan' => 'pro',
        ], $canonical['custom_properties']);
    }

    #[Test]
    public function itPassesThroughAlreadyCanonicalPayloadAndNormalisesNestedBehavioral(): void
    {
        $canonical = $this->normalizer->normalize([
            'event_name' => 'click',
            'behavioral' => [
                'cx' => 10,
                'click_y' => 20,
            ],
        ]);

        self::assertSame('click', $canonical['event_name']);
        /** @var array<string, mixed> $beh */
        $beh = $canonical['behavioral'];
        self::assertSame(10, $beh['click_x']);
        self::assertSame(20, $beh['click_y']);
    }

    #[Test]
    public function itLeavesNonNumericNonStringTimestampUntouchedForTheValidator(): void
    {
        $canonical = $this->normalizer->normalize([
            'ts' => ['nope'],
        ]);

        self::assertSame(['nope'], $canonical['timestamp']);
    }

    #[Test]
    public function itTreatsNonArrayBehavioralAsPassthrough(): void
    {
        $canonical = $this->normalizer->normalize([
            'b' => 'oops',
        ]);

        self::assertSame('oops', $canonical['behavioral']);
    }
}
