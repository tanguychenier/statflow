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

namespace App\Tests\Unit\Identity\Infrastructure\Token;

use App\Identity\Domain\ValueObject\TeamMembershipClaim;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Identity\Infrastructure\Token\Es256AccessTokenIssuer;
use App\Identity\Infrastructure\Token\Es256AccessTokenVerifier;
use App\Identity\Infrastructure\Token\JwksProvider;
use App\Shared\Domain\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Es256AccessTokenIssuer::class)]
#[CoversClass(Es256AccessTokenVerifier::class)]
#[CoversClass(JwksProvider::class)]
final class Es256TokenRoundTripTest extends TestCase
{
    private string $privateKeyPem;

    private string $publicKeyPem;

    private FixedClock $clock;

    protected function setUp(): void
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        self::assertNotFalse($resource, 'OpenSSL must be able to generate an EC key.');

        $privateKeyPem = '';
        openssl_pkey_export($resource, $privateKeyPem);
        /** @var string $privateKeyPem */
        $this->privateKeyPem = $privateKeyPem;

        $details = openssl_pkey_get_details($resource);
        self::assertIsArray($details);
        /** @var array{key: string} $details */
        $this->publicKeyPem = $details['key'];

        $this->clock = new FixedClock(new DateTimeImmutable('2026-05-29T10:00:00+00:00'));
    }

    #[Test]
    public function aFreshlyIssuedTokenVerifiesAndCarriesTheClaims(): void
    {
        $userId = Uuid::generate();
        $teamId = Uuid::generate();

        $issuer = new Es256AccessTokenIssuer($this->privateKeyPem, 'kid-1', $this->clock);
        $verifier = new Es256AccessTokenVerifier($this->publicKeyPem, $this->clock);

        $token = $issuer->issue($userId, [new TeamMembershipClaim($teamId, TeamRole::Admin)]);
        self::assertSame(900, $token->expiresInSeconds);

        $claims = $verifier->verify($token->jwt);

        self::assertNotNull($claims);
        self::assertSame($userId->getValue(), $claims['sub']);
        self::assertIsArray($claims['teams']);
        /** @var list<array{team_id: string, role: string}> $teams */
        $teams = $claims['teams'];
        self::assertSame($teamId->getValue(), $teams[0]['team_id']);
        self::assertSame('admin', $teams[0]['role']);
    }

    #[Test]
    public function anExpiredTokenIsRejected(): void
    {
        $issuer = new Es256AccessTokenIssuer($this->privateKeyPem, 'kid-1', $this->clock);
        $token = $issuer->issue(Uuid::generate(), []);

        $later = new FixedClock(new DateTimeImmutable('2026-05-29T11:00:00+00:00'));
        $verifier = new Es256AccessTokenVerifier($this->publicKeyPem, $later);

        self::assertNull($verifier->verify($token->jwt));
    }

    #[Test]
    public function aTamperedTokenFailsSignatureVerification(): void
    {
        $issuer = new Es256AccessTokenIssuer($this->privateKeyPem, 'kid-1', $this->clock);
        $verifier = new Es256AccessTokenVerifier($this->publicKeyPem, $this->clock);

        $token = $issuer->issue(Uuid::generate(), []);
        [$header, $payload] = explode('.', $token->jwt);
        $forged = $header . '.' . $payload . '.' . rtrim(strtr(base64_encode(str_repeat('x', 64)), '+/', '-_'), '=');

        self::assertNull($verifier->verify($forged));
    }

    #[Test]
    public function aTokenSignedByAnotherKeyIsRejected(): void
    {
        $otherResource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        self::assertNotFalse($otherResource);
        $otherPrivate = '';
        openssl_pkey_export($otherResource, $otherPrivate);
        /** @var string $otherPrivate */

        $foreignIssuer = new Es256AccessTokenIssuer($otherPrivate, 'kid-x', $this->clock);
        $verifier = new Es256AccessTokenVerifier($this->publicKeyPem, $this->clock);

        $token = $foreignIssuer->issue(Uuid::generate(), []);

        self::assertNull($verifier->verify($token->jwt));
    }

    #[Test]
    public function malformedTokensAreRejected(): void
    {
        $verifier = new Es256AccessTokenVerifier($this->publicKeyPem, $this->clock);

        self::assertNull($verifier->verify('not-a-jwt'));
        self::assertNull($verifier->verify('only.two'));
    }

    #[Test]
    public function jwksExposesTheP256PublicKey(): void
    {
        $jwks = (new JwksProvider($this->publicKeyPem, 'kid-1'))->jwks();

        self::assertCount(1, $jwks['keys']);
        $key = $jwks['keys'][0];
        self::assertSame('EC', $key['kty']);
        self::assertSame('P-256', $key['crv']);
        self::assertSame('ES256', $key['alg']);
        self::assertSame('kid-1', $key['kid']);
        self::assertNotSame('', $key['x']);
        self::assertNotSame('', $key['y']);
    }
}
