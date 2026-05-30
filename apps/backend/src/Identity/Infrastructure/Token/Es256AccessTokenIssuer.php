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

use App\Identity\Domain\Port\AccessTokenIssuer;
use App\Identity\Domain\ValueObject\AccessToken;
use App\Identity\Domain\ValueObject\TeamMembershipClaim;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;
use RuntimeException;

/**
 * Issues ES256 (ECDSA P-256) JWT access tokens (api/README.md §2.1) using the
 * bundled OpenSSL extension — no third-party JWT dependency, keeping the build
 * fully self-hostable. Claims follow the documented set: sub, iat, exp, jti, and
 * the teams array of {team_id, role}. The 15-minute TTL is fixed by the spec.
 *
 * The signing key (PEM-encoded EC private key) and its key id are supplied by the
 * wiring agent from the runtime environment; the matching public key is published
 * by {@see JwksProvider}.
 */
final readonly class Es256AccessTokenIssuer implements AccessTokenIssuer
{
    public const TTL_SECONDS = 900;

    public function __construct(
        private string $privateKeyPem,
        private string $keyId,
        private Clock $clock,
    ) {
    }

    /**
     * @param list<TeamMembershipClaim> $teams
     */
    public function issue(Uuid $userId, array $teams): AccessToken
    {
        $issuedAt = $this->clock->now()->getTimestamp();
        $expiresAt = $issuedAt + self::TTL_SECONDS;

        $header = [
            'alg' => 'ES256',
            'typ' => 'JWT',
            'kid' => $this->keyId,
        ];
        $payload = [
            'sub' => $userId->getValue(),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => Uuid::generate()->getValue(),
            'teams' => array_map(static fn (TeamMembershipClaim $c): array => $c->toArray(), $teams),
        ];

        $signingInput = $this->base64UrlEncode($this->encodeJson($header))
            . '.'
            . $this->base64UrlEncode($this->encodeJson($payload));

        $jwt = $signingInput . '.' . $this->base64UrlEncode($this->sign($signingInput));

        return new AccessToken($jwt, self::TTL_SECONDS);
    }

    private function sign(string $signingInput): string
    {
        $privateKey = openssl_pkey_get_private($this->privateKeyPem);

        if ($privateKey === false) {
            throw new RuntimeException('The ES256 signing key is not a valid EC private key.');
        }

        $derSignature = '';
        if (!openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign the access token.');
        }

        /** @var string $derSignature */
        return $this->derToJose($derSignature);
    }

    /**
     * Convert an ASN.1 DER ECDSA signature to the JOSE fixed-length R||S form
     * (64 bytes for P-256) required by RFC 7515 ES256.
     */
    private function derToJose(string $der): string
    {
        $offset = 0;
        $this->readByte($der, $offset); // 0x30 SEQUENCE tag
        $this->readLength($der, $offset);

        $r = $this->readInteger($der, $offset);
        $s = $this->readInteger($der, $offset);

        return $this->pad($r) . $this->pad($s);
    }

    private function readInteger(string $der, int &$offset): string
    {
        $this->readByte($der, $offset); // 0x02 INTEGER tag
        $length = $this->readLength($der, $offset);
        $value = substr($der, $offset, $length);
        $offset += $length;

        // Drop a leading zero byte added to keep the value positive.
        return ltrim($value, "\x00");
    }

    private function pad(string $value): string
    {
        return str_pad($value, 32, "\x00", STR_PAD_LEFT);
    }

    private function readByte(string $der, int &$offset): int
    {
        $byte = ord($der[$offset]);
        ++$offset;

        return $byte;
    }

    private function readLength(string $der, int &$offset): int
    {
        $length = $this->readByte($der, $offset);

        if ($length <= 0x7F) {
            return $length;
        }

        $bytes = $length & 0x7F;
        $length = 0;
        for ($i = 0; $i < $bytes; ++$i) {
            $length = ($length << 8) + $this->readByte($der, $offset);
        }

        return $length;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
