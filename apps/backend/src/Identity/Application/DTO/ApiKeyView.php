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

namespace App\Identity\Application\DTO;

use App\Identity\Domain\Model\ApiKey;
use App\Identity\Domain\ValueObject\ApiKeyScope;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Read model for the ApiKey schema (openapi.yaml). Only the masked prefix is
 * exposed; the raw key (rawKey) is set exclusively on the create response and
 * shown once. The hash is never serialised.
 */
final readonly class ApiKeyView
{
    /**
     * @param list<string> $scopes
     * @param list<string> $siteIds
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $keyPrefix,
        public array $scopes,
        public array $siteIds,
        public ?string $expiresAt,
        public ?string $lastUsedAt,
        public string $createdAt,
        public ?string $rawKey = null,
    ) {
    }

    public static function fromEntity(ApiKey $apiKey, ?string $rawKey = null): self
    {
        return new self(
            $apiKey->id()->getValue(),
            $apiKey->name(),
            $apiKey->keyPrefix(),
            array_map(static fn (ApiKeyScope $scope): string => $scope->value, $apiKey->scopes()),
            array_map(static fn (Uuid $siteId): string => $siteId->getValue(), $apiKey->siteIds()),
            $apiKey->expiresAt()?->format(DATE_ATOM),
            $apiKey->lastUsedAt()?->format(DATE_ATOM),
            $apiKey->createdAt()->format(DATE_ATOM),
            $rawKey,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'key_prefix' => $this->keyPrefix,
            'scopes' => $this->scopes,
            'site_ids' => $this->siteIds,
            'expires_at' => $this->expiresAt,
            'last_used_at' => $this->lastUsedAt,
            'created_at' => $this->createdAt,
        ];

        if ($this->rawKey !== null) {
            $data['raw_key'] = $this->rawKey;
        }

        return $data;
    }
}
