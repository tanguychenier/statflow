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

use App\Shared\Domain\Clock\Clock;

/**
 * Verifies and decodes ES256 access tokens issued by {@see Es256AccessTokenIssuer}
 * using the OpenSSL extension only. Validates the alg header, the signature
 * against the EC public key, and the exp claim. Returns the decoded claims on
 * success or null on any failure (bad shape, wrong alg, bad signature, expired).
 */
final readonly class Es256AccessTokenVerifier
{
    public function __construct(
        private string $publicKeyPem,
        private Clock $clock,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verify(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJson($this->base64UrlDecode($encodedHeader));
        if (!is_array($header) || ($header['alg'] ?? null) !== 'ES256') {
            return null;
        }

        $signature = $this->base64UrlDecode($encodedSignature);
        if (!$this->isSignatureValid($encodedHeader . '.' . $encodedPayload, $signature)) {
            return null;
        }

        $payload = $this->decodeJson($this->base64UrlDecode($encodedPayload));
        if (!is_array($payload)) {
            return null;
        }

        $expiry = $payload['exp'] ?? null;
        if (!is_int($expiry) || $expiry < $this->clock->now()->getTimestamp()) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    private function isSignatureValid(string $signingInput, string $joseSignature): bool
    {
        if (strlen($joseSignature) !== 64) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($this->publicKeyPem);
        if ($publicKey === false) {
            return false;
        }

        $der = $this->joseToDer($joseSignature);

        return openssl_verify($signingInput, $der, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Re-encode a JOSE fixed-length R||S signature as ASN.1 DER for openssl_verify.
     */
    private function joseToDer(string $signature): string
    {
        $r = $this->encodeAsn1Integer(substr($signature, 0, 32));
        $s = $this->encodeAsn1Integer(substr($signature, 32, 32));
        $sequence = $r . $s;

        return "\x30" . $this->encodeAsn1Length(strlen($sequence)) . $sequence;
    }

    private function encodeAsn1Integer(string $value): string
    {
        $value = ltrim($value, "\x00");

        if ($value === '') {
            $value = "\x00";
        }

        // Prepend a zero byte when the high bit is set, to keep it positive.
        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }

        return "\x02" . $this->encodeAsn1Length(strlen($value)) . $value;
    }

    private function encodeAsn1Length(int $length): string
    {
        if ($length <= 0x7F) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function decodeJson(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
