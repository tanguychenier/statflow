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

namespace App\Analytics\Domain\Model;

/**
 * One ordered step of a persisted funnel (`postgres.funnel_steps`).
 *
 * A step matches either a pageview whose pathname matches `urlPattern`, or an
 * event whose name equals `eventName`. The matched events have been
 * pre-materialised into ClickHouse `funnel_events` with this `stepIndex`.
 */
final readonly class FunnelStep
{
    private function __construct(
        public int $stepIndex,
        public string $label,
        public FunnelTriggerType $triggerType,
        public ?string $urlPattern,
        public ?string $eventName,
    ) {
    }

    public static function pageview(int $stepIndex, string $label, string $urlPattern): self
    {
        return new self($stepIndex, $label, FunnelTriggerType::Pageview, $urlPattern, null);
    }

    public static function event(int $stepIndex, string $label, string $eventName): self
    {
        return new self($stepIndex, $label, FunnelTriggerType::Event, null, $eventName);
    }

    /**
     * The display name surfaced in the funnel response: the human label, falling
     * back to the matched event name or URL pattern.
     */
    public function displayEventName(): string
    {
        return $this->eventName ?? $this->urlPattern ?? $this->label;
    }
}
