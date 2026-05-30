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

namespace App\Identity\Domain\ValueObject;

/**
 * The result of minting a programmatic API key: the raw secret returned once to
 * the caller, the 12-char display prefix, and the SHA-256 hash stored at rest
 * (ADR-0009 §4). The raw value must never be persisted.
 */
final readonly class GeneratedApiKey
{
    public function __construct(
        private string $rawKey,
        public string $prefix,
        public string $hash
    ) {
    }

    public function __debugInfo(): array
    {
        return [
            'rawKey' => '********',
            'prefix' => $this->prefix,
            'hash' => $this->hash,
        ];
    }

    public function reveal(): string
    {
        return $this->rawKey;
    }
}
