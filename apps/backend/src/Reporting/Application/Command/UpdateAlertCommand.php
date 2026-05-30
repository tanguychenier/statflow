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

namespace App\Reporting\Application\Command;

/**
 * Partially update an alert (OpenAPI PATCH). Condition and threshold must be
 * supplied together when either changes; the handler enforces this so the
 * comparison-period invariant always holds.
 */
final readonly class UpdateAlertCommand
{
    /**
     * @param list<array<string, mixed>>|null $notificationChannels
     */
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public string $alertId,
        public ?string $name = null,
        public ?string $condition = null,
        public ?float $threshold = null,
        public ?bool $isActive = null,
        public ?array $notificationChannels = null,
    ) {
    }
}
