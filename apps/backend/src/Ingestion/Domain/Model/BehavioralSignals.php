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

namespace App\Ingestion\Domain\Model;

/**
 * Behavioral signals attached to interaction events (event-contract.md §5).
 *
 * Coordinates are document-relative (clientX + scrollX); §5.1 fixes a single
 * coordinate origin end to end. Every field is nullable because most event
 * types carry only a subset.
 */
final readonly class BehavioralSignals
{
    public function __construct(
        public ?int $clickX = null,
        public ?int $clickY = null,
        public ?float $clickXPct = null,
        public ?float $clickYPct = null,
        public ?string $elementTag = null,
        public ?string $elementText = null,
        public ?string $elementSelector = null,
        public ?string $elementId = null,
        public ?int $scrollDepthPct = null,
        public ?int $scrollDepthPx = null,
        public ?int $engagementTimeMs = null,
        public bool $isRageClick = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->clickX === null
            && $this->clickY === null
            && $this->clickXPct === null
            && $this->clickYPct === null
            && $this->elementTag === null
            && $this->elementText === null
            && $this->elementSelector === null
            && $this->elementId === null
            && $this->scrollDepthPct === null
            && $this->scrollDepthPx === null
            && $this->engagementTimeMs === null
            && !$this->isRageClick;
    }
}
