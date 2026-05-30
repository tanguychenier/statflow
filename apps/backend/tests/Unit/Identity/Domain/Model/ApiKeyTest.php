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

namespace App\Tests\Unit\Identity\Domain\Model;

use App\Identity\Domain\Exception\InvalidApiKeyScopeException;
use App\Identity\Domain\Model\ApiKey;
use App\Identity\Domain\ValueObject\ApiKeyScope;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiKey::class)]
final class ApiKeyTest extends TestCase
{
    private const NOW = '2026-05-29T10:00:00+00:00';

    #[Test]
    public function itIssuesAKeyWithDeduplicatedScopes(): void
    {
        $key = $this->issue([ApiKeyScope::AnalyticsRead, ApiKeyScope::AnalyticsRead, ApiKeyScope::ReportsRead], []);

        self::assertCount(2, $key->scopes());
        self::assertTrue($key->hasScope(ApiKeyScope::AnalyticsRead));
        self::assertTrue($key->hasScope(ApiKeyScope::ReportsRead));
        self::assertFalse($key->hasScope(ApiKeyScope::SitesWrite));
    }

    #[Test]
    public function itRejectsAnEmptyScopeList(): void
    {
        $this->expectException(InvalidApiKeyScopeException::class);

        $this->issue([], []);
    }

    #[Test]
    public function anUnrestrictedKeyAllowsEverySite(): void
    {
        $key = $this->issue([ApiKeyScope::AnalyticsRead], []);

        self::assertFalse($key->isRestrictedToSites());
        self::assertTrue($key->allowsSite(Uuid::generate()));
    }

    #[Test]
    public function aRestrictedKeyAllowsOnlyListedSites(): void
    {
        $allowed = Uuid::generate();
        $key = $this->issue([ApiKeyScope::AnalyticsRead], [$allowed]);

        self::assertTrue($key->isRestrictedToSites());
        self::assertTrue($key->allowsSite($allowed));
        self::assertFalse($key->allowsSite(Uuid::generate()));
    }

    #[Test]
    public function revocationIsIdempotentAndDeactivates(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $key = $this->issue([ApiKeyScope::AnalyticsRead], []);

        self::assertTrue($key->isActive($now));

        $key->revoke($now);
        $revokedAt = $key->revokedAt();
        $key->revoke(new DateTimeImmutable('2026-06-01T00:00:00+00:00'));

        self::assertTrue($key->isRevoked());
        self::assertFalse($key->isActive($now));
        self::assertEquals($revokedAt, $key->revokedAt());
    }

    #[Test]
    public function anExpiredKeyIsInactive(): void
    {
        $key = ApiKey::issue(
            Uuid::generate(),
            Uuid::generate(),
            Uuid::generate(),
            'CI',
            'hash',
            'sfk_live_xyz',
            [ApiKeyScope::AnalyticsRead],
            [],
            new DateTimeImmutable('2026-05-29T09:00:00+00:00'),
            new DateTimeImmutable('2026-05-29T08:00:00+00:00'),
        );

        self::assertTrue($key->isExpired(new DateTimeImmutable(self::NOW)));
        self::assertFalse($key->isActive(new DateTimeImmutable(self::NOW)));
    }

    #[Test]
    public function itRecordsUsage(): void
    {
        $key = $this->issue([ApiKeyScope::AnalyticsRead], []);
        $usedAt = new DateTimeImmutable('2026-05-29T11:00:00+00:00');

        $key->recordUsage($usedAt);

        self::assertEquals($usedAt, $key->lastUsedAt());
    }

    /**
     * @param list<ApiKeyScope> $scopes intentionally list (not non-empty) to allow testing the empty-scopes rejection path
     * @param list<Uuid>        $siteIds
     */
    private function issue(array $scopes, array $siteIds): ApiKey
    {
        return ApiKey::issue(
            Uuid::generate(),
            Uuid::generate(),
            Uuid::generate(),
            'CI key',
            'sha256hash',
            'sfk_live_abc',
            // @phpstan-ignore-next-line ($scopes may be empty to test the empty-scopes rejection path)
            $scopes,
            $siteIds,
            null,
            new DateTimeImmutable(self::NOW),
        );
    }
}
