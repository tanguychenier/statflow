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

use App\Sites\Domain\Model\Site;

/**
 * Read model for a single site, shaped to the OpenAPI `Site` schema.
 * Built from the aggregate so controllers never touch domain entities directly.
 */
final readonly class SiteView
{
    private function __construct(
        public string $id,
        public string $teamId,
        public string $name,
        public string $domain,
        public string $trackerKey,
        public string $timezone,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromSite(Site $site): self
    {
        return new self(
            id: $site->id()->getValue(),
            teamId: $site->teamId()->getValue(),
            name: $site->name()->value(),
            domain: $site->domain()->value(),
            trackerKey: $site->trackerKey()->value(),
            timezone: $site->timezone()->value(),
            createdAt: $site->createdAt()->format(DATE_ATOM),
            updatedAt: $site->updatedAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->teamId,
            'name' => $this->name,
            'domain' => $this->domain,
            'tracker_key' => $this->trackerKey,
            'timezone' => $this->timezone,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
