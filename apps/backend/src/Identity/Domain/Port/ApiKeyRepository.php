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

namespace App\Identity\Domain\Port;

use App\Identity\Domain\Model\ApiKey;
use App\Shared\Domain\ValueObject\Uuid;

interface ApiKeyRepository
{
    public function save(ApiKey $apiKey): void;

    public function findById(Uuid $id): ?ApiKey;

    public function findByHash(string $keyHash): ?ApiKey;

    /**
     * Non-revoked keys for the team, newest first.
     *
     * @return list<ApiKey>
     */
    public function findActiveByTeam(Uuid $teamId): array;
}
