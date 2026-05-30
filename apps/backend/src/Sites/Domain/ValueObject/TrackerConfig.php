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

namespace App\Sites\Domain\ValueObject;

use App\Sites\Domain\Exception\InvalidTrackerConfigException;

/**
 * What the JS tracker collects on a given site.
 *
 * Mirrors the OpenAPI `TrackerConfig` schema. The boolean toggles and
 * `sampling_rate` map onto the corresponding `site_settings` columns; the
 * remaining structured fields (`ignored_selectors`, `script_variant`) are
 * persisted alongside them. Immutable: editing produces a new instance.
 */
final readonly class TrackerConfig
{
    public const MAX_IGNORED_SELECTORS = 50;

    /**
     * @param list<string> $ignoredSelectors
     */
    private function __construct(
        private bool $trackClicks,
        private bool $trackScroll,
        private bool $trackEngagementTime,
        private bool $trackOutboundLinks,
        private bool $hashBasedRouting,
        private array $ignoredSelectors,
        private SamplingRate $samplingRate,
        private ScriptVariant $scriptVariant,
    ) {
    }

    public static function default(): self
    {
        return new self(
            trackClicks: true,
            trackScroll: true,
            trackEngagementTime: true,
            trackOutboundLinks: true,
            hashBasedRouting: false,
            ignoredSelectors: [],
            samplingRate: SamplingRate::default(),
            scriptVariant: ScriptVariant::Default,
        );
    }

    /**
     * @param list<string> $ignoredSelectors
     */
    public static function create(
        bool $trackClicks,
        bool $trackScroll,
        bool $trackEngagementTime,
        bool $trackOutboundLinks,
        bool $hashBasedRouting,
        array $ignoredSelectors,
        SamplingRate $samplingRate,
        ScriptVariant $scriptVariant,
    ): self {
        $selectors = self::normaliseSelectors($ignoredSelectors);

        return new self(
            $trackClicks,
            $trackScroll,
            $trackEngagementTime,
            $trackOutboundLinks,
            $hashBasedRouting,
            $selectors,
            $samplingRate,
            $scriptVariant,
        );
    }

    public function trackClicks(): bool
    {
        return $this->trackClicks;
    }

    public function trackScroll(): bool
    {
        return $this->trackScroll;
    }

    public function trackEngagementTime(): bool
    {
        return $this->trackEngagementTime;
    }

    public function trackOutboundLinks(): bool
    {
        return $this->trackOutboundLinks;
    }

    public function hashBasedRouting(): bool
    {
        return $this->hashBasedRouting;
    }

    /**
     * @return list<string>
     */
    public function ignoredSelectors(): array
    {
        return $this->ignoredSelectors;
    }

    public function samplingRate(): SamplingRate
    {
        return $this->samplingRate;
    }

    public function scriptVariant(): ScriptVariant
    {
        return $this->scriptVariant;
    }

    /**
     * @param list<string> $raw
     *
     * @return list<string>
     */
    private static function normaliseSelectors(array $raw): array
    {
        $clean = [];

        foreach ($raw as $selector) {
            $selector = trim($selector);

            if ($selector !== '') {
                $clean[$selector] = $selector;
            }
        }

        $clean = array_values($clean);

        if (count($clean) > self::MAX_IGNORED_SELECTORS) {
            throw InvalidTrackerConfigException::tooManySelectors(self::MAX_IGNORED_SELECTORS);
        }

        return $clean;
    }
}
