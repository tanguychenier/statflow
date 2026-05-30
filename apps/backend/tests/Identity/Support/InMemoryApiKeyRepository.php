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

namespace App\Tests\Identity\Support;

use App\Identity\Domain\Model\ApiKey;
use App\Identity\Domain\Port\ApiKeyRepository;
use App\Shared\Domain\ValueObject\Uuid;

final class InMemoryApiKeyRepository implements ApiKeyRepository
{
    /**
     * @var array<string, ApiKey>
     */
    private array $keys = [];

    public function save(ApiKey $apiKey): void
    {
        $this->keys[$apiKey->id()->getValue()] = $apiKey;
    }

    public function findById(Uuid $id): ?ApiKey
    {
        return $this->keys[$id->getValue()] ?? null;
    }

    public function findByHash(string $keyHash): ?ApiKey
    {
        foreach ($this->keys as $key) {
            if (hash_equals($key->keyHash(), $keyHash)) {
                return $key;
            }
        }

        return null;
    }

    public function findActiveByTeam(Uuid $teamId): array
    {
        return array_values(array_filter(
            $this->keys,
            static fn (ApiKey $k): bool => $k->teamId()->equals($teamId) && !$k->isRevoked(),
        ));
    }
}
