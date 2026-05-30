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

namespace App\Sites\Application\Dto;

/**
 * Outcome of a tracker-key rotation, shaped to the OpenAPI rotateTrackerKey
 * 200 response: the new key plus the instant the old one is fully revoked.
 * A site holds a single key (ADR-0009 §1), so revocation is immediate and
 * `old_key_valid_until` is the rotation timestamp itself.
 */
final readonly class TrackerKeyRotationResult
{
    public function __construct(
        public string $trackerKey,
        public string $oldKeyValidUntil,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'tracker_key' => $this->trackerKey,
            'old_key_valid_until' => $this->oldKeyValidUntil,
        ];
    }
}
