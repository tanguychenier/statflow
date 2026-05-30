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

namespace App\Identity\Infrastructure\Redis;

use App\Identity\Domain\Port\RefreshTokenStore;
use App\Identity\Domain\Port\TokenGenerator;
use App\Identity\Domain\ValueObject\RefreshToken;
use App\Shared\Domain\ValueObject\Uuid;
use Predis\ClientInterface;

/**
 * Redis-backed store for opaque refresh tokens (api/README.md §2.1).
 *
 * Only the SHA-256 hash of each token is persisted (`rt:{hash}` → user id), so a
 * Redis dump cannot be replayed as a live session. A per-user set
 * (`rt:user:{id}`) indexes a user's outstanding tokens, enabling the
 * logout-everywhere semantics that password change / reset require. Rotation is
 * single-use: refreshing revokes the presented token and mints a replacement.
 */
final readonly class RedisRefreshTokenStore implements RefreshTokenStore
{
    private const TOKEN_PREFIX = 'rt:';

    private const USER_PREFIX = 'rt:user:';

    /**
     * 30 days (api/README.md §2.1).
     */
    private const TTL_SECONDS = 2_592_000;

    public function __construct(
        private ClientInterface $redis,
        private TokenGenerator $tokenGenerator,
    ) {
    }

    public function issue(Uuid $userId): RefreshToken
    {
        $raw = $this->tokenGenerator->generate(32);
        $this->persist($raw, $userId);

        return new RefreshToken($raw, self::TTL_SECONDS);
    }

    public function resolveUserId(string $rawToken): ?Uuid
    {
        $userId = $this->redis->executeRaw(['GET', $this->tokenKey($rawToken)]);

        return is_string($userId) && $userId !== '' ? Uuid::fromString($userId) : null;
    }

    public function rotate(string $rawToken): ?RefreshToken
    {
        $userId = $this->resolveUserId($rawToken);

        if ($userId === null) {
            return null;
        }

        $this->revoke($rawToken);

        return $this->issue($userId);
    }

    public function revoke(string $rawToken): void
    {
        $userId = $this->resolveUserId($rawToken);
        $hash = $this->hash($rawToken);

        $this->redis->executeRaw(['DEL', self::TOKEN_PREFIX . $hash]);

        if ($userId !== null) {
            $this->redis->executeRaw(['SREM', self::USER_PREFIX . $userId->getValue(), $hash]);
        }
    }

    public function revokeAllForUser(Uuid $userId): void
    {
        $userKey = self::USER_PREFIX . $userId->getValue();
        $hashes = $this->redis->executeRaw(['SMEMBERS', $userKey]);

        if (is_array($hashes)) {
            foreach ($hashes as $hash) {
                $this->redis->executeRaw(['DEL', self::TOKEN_PREFIX . (is_scalar($hash) ? (string) $hash : '')]);
            }
        }

        $this->redis->executeRaw(['DEL', $userKey]);
    }

    private function persist(string $rawToken, Uuid $userId): void
    {
        $hash = $this->hash($rawToken);

        $this->redis->executeRaw([
            'SET', self::TOKEN_PREFIX . $hash, $userId->getValue(), 'EX', (string) self::TTL_SECONDS,
        ]);
        $this->redis->executeRaw(['SADD', self::USER_PREFIX . $userId->getValue(), $hash]);
        $this->redis->executeRaw(['EXPIRE', self::USER_PREFIX . $userId->getValue(), (string) self::TTL_SECONDS]);
    }

    private function tokenKey(string $rawToken): string
    {
        return self::TOKEN_PREFIX . $this->hash($rawToken);
    }

    private function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
