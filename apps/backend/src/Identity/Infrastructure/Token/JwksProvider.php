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

namespace App\Identity\Infrastructure\Token;

use RuntimeException;

/**
 * Builds the JWKS document served at /auth/.well-known/jwks.json so external
 * verifiers can validate ES256 access tokens without a shared secret
 * (api/README.md §2.1). The EC P-256 public key's affine coordinates are read
 * from the PEM via OpenSSL and base64url-encoded per RFC 7518 §6.2.
 */
final readonly class JwksProvider
{
    public function __construct(
        private string $publicKeyPem,
        private string $keyId,
    ) {
    }

    /**
     * @return array{keys: list<array<string, string>>}
     */
    public function jwks(): array
    {
        $key = openssl_pkey_get_public($this->publicKeyPem);

        if ($key === false) {
            throw new RuntimeException('The ES256 public key is not a valid EC public key.');
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false) {
            throw new RuntimeException('Unable to read EC public key coordinates.');
        }

        $ec = $details['ec'] ?? null;
        if (!is_array($ec) || !isset($ec['x'], $ec['y'])) {
            throw new RuntimeException('Unable to read EC public key coordinates.');
        }

        /** @var string $xCoord */
        $xCoord = $ec['x'];
        /** @var string $yCoord */
        $yCoord = $ec['y'];

        return [
            'keys' => [
                [
                    'kty' => 'EC',
                    'crv' => 'P-256',
                    'use' => 'sig',
                    'alg' => 'ES256',
                    'kid' => $this->keyId,
                    'x' => $this->base64UrlEncode($xCoord),
                    'y' => $this->base64UrlEncode($yCoord),
                ],
            ],
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
