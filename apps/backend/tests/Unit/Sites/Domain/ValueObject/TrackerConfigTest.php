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

namespace App\Tests\Unit\Sites\Domain\ValueObject;

use App\Sites\Domain\Exception\InvalidScriptVariantException;
use App\Sites\Domain\Exception\InvalidTrackerConfigException;
use App\Sites\Domain\ValueObject\SamplingRate;
use App\Sites\Domain\ValueObject\ScriptVariant;
use App\Sites\Domain\ValueObject\TrackerConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrackerConfig::class)]
#[CoversClass(ScriptVariant::class)]
#[CoversClass(InvalidTrackerConfigException::class)]
#[CoversClass(InvalidScriptVariantException::class)]
final class TrackerConfigTest extends TestCase
{
    #[Test]
    public function defaultMatchesDocumentedDefaults(): void
    {
        $config = TrackerConfig::default();

        self::assertTrue($config->trackClicks());
        self::assertTrue($config->trackScroll());
        self::assertTrue($config->trackEngagementTime());
        self::assertTrue($config->trackOutboundLinks());
        self::assertFalse($config->hashBasedRouting());
        self::assertSame([], $config->ignoredSelectors());
        self::assertSame(1.0, $config->samplingRate()->value());
        self::assertSame(ScriptVariant::Default, $config->scriptVariant());
    }

    #[Test]
    public function createNormalisesSelectors(): void
    {
        $config = TrackerConfig::create(
            trackClicks: false,
            trackScroll: false,
            trackEngagementTime: false,
            trackOutboundLinks: false,
            hashBasedRouting: true,
            ignoredSelectors: [' .ad ', '.ad', '', '#x'],
            samplingRate: SamplingRate::fromFloat(0.5),
            scriptVariant: ScriptVariant::Compat,
        );

        self::assertSame(['.ad', '#x'], $config->ignoredSelectors());
        self::assertTrue($config->hashBasedRouting());
        self::assertSame(0.5, $config->samplingRate()->value());
        self::assertSame(ScriptVariant::Compat, $config->scriptVariant());
    }

    #[Test]
    public function itRejectsTooManySelectors(): void
    {
        $selectors = [];
        for ($i = 0; $i < TrackerConfig::MAX_IGNORED_SELECTORS + 1; ++$i) {
            $selectors[] = '.s' . $i;
        }

        $this->expectException(InvalidTrackerConfigException::class);

        TrackerConfig::create(
            trackClicks: true,
            trackScroll: true,
            trackEngagementTime: true,
            trackOutboundLinks: true,
            hashBasedRouting: false,
            ignoredSelectors: $selectors,
            samplingRate: SamplingRate::default(),
            scriptVariant: ScriptVariant::Default,
        );
    }

    #[Test]
    public function scriptVariantParsesKnownValues(): void
    {
        self::assertSame(ScriptVariant::Entropy, ScriptVariant::fromString('entropy'));
        self::assertSame(ScriptVariant::Manual, ScriptVariant::fromString('manual'));
    }

    #[Test]
    public function scriptVariantRejectsUnknownValue(): void
    {
        $this->expectException(InvalidScriptVariantException::class);

        ScriptVariant::fromString('rocket');
    }
}
