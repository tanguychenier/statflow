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

namespace App\Identity\Domain\Service;

use App\Identity\Domain\Model\ApiKey;
use App\Identity\Domain\Port\TokenGenerator;
use App\Identity\Domain\ValueObject\GeneratedApiKey;

/**
 * Mints programmatic API-key secrets in the frozen shape (ADR-0009 §4):
 * `sfk_live_` / `sfk_test_` prefix, a high-entropy random body, a 12-char stored
 * display prefix, and a SHA-256 hash. The raw key leaves this service only once,
 * inside GeneratedApiKey, and is never persisted.
 */
final readonly class ApiKeyFactory
{
    public const LIVE_PREFIX = 'sfk_live_';

    public const TEST_PREFIX = 'sfk_test_';

    public function __construct(
        private TokenGenerator $tokenGenerator,
    ) {
    }

    public function create(bool $live = true): GeneratedApiKey
    {
        $environmentPrefix = $live ? self::LIVE_PREFIX : self::TEST_PREFIX;
        $rawKey = $environmentPrefix . $this->tokenGenerator->generate(32);

        return new GeneratedApiKey(
            $rawKey,
            substr($rawKey, 0, ApiKey::PREFIX_LENGTH),
            self::hash($rawKey),
        );
    }

    /**
     * SHA-256 hex digest used both at creation and at authentication lookup.
     */
    public static function hash(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }
}
