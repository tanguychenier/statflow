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

namespace App\Tests\Unit\Ingestion\Domain\Service;

use App\Ingestion\Domain\Exception\OriginNotAllowed;
use App\Ingestion\Domain\Model\Site;
use App\Ingestion\Domain\Service\OriginAuthorizer;
use App\Ingestion\Domain\Service\OriginDecision;
use App\Tests\Ingestion\Support\EventFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OriginAuthorizer::class)]
#[CoversClass(OriginNotAllowed::class)]
#[CoversClass(OriginDecision::class)]
final class OriginAuthorizerTest extends TestCase
{
    private OriginAuthorizer $authorizer;

    protected function setUp(): void
    {
        $this->authorizer = new OriginAuthorizer();
    }

    #[Test]
    public function emptyAllowlistReportsThePermissiveBootstrapPath(): void
    {
        // An unconfigured allowlist accepts any origin (and the missing-Origin
        // case too), but signals BootstrapAllowAll so the caller can log it
        // rather than silently allowing every origin (postgres-schema.sql §5).
        self::assertSame(
            OriginDecision::BootstrapAllowAll,
            $this->authorizer->assertAllowed(EventFixtures::site([
                'allowedDomains' => [],
            ]), 'https://anywhere.com'),
        );
        self::assertSame(
            OriginDecision::BootstrapAllowAll,
            $this->authorizer->assertAllowed(EventFixtures::site([
                'allowedDomains' => [],
            ]), null),
        );
    }

    #[Test]
    public function itAllowsAnExactHostMatch(): void
    {
        self::assertSame(
            OriginDecision::Matched,
            $this->authorizer->assertAllowed($this->siteWith(['example.com']), 'https://example.com'),
        );
    }

    #[Test]
    public function itAllowsAHostWithPort(): void
    {
        self::assertSame(
            OriginDecision::Matched,
            $this->authorizer->assertAllowed($this->siteWith(['localhost']), 'http://localhost:5173'),
        );
    }

    #[Test]
    public function itAllowsAWildcardSubdomain(): void
    {
        self::assertSame(
            OriginDecision::Matched,
            $this->authorizer->assertAllowed($this->siteWith(['*.example.com']), 'https://app.example.com'),
        );
        self::assertSame(
            OriginDecision::Matched,
            $this->authorizer->assertAllowed($this->siteWith(['*.example.com']), 'https://deep.app.example.com'),
        );
    }

    #[Test]
    public function aWildcardDoesNotMatchTheBareApex(): void
    {
        $this->expectException(OriginNotAllowed::class);

        $this->authorizer->assertAllowed($this->siteWith(['*.example.com']), 'https://example.com');
    }

    #[Test]
    public function itRejectsAnUnlistedOrigin(): void
    {
        $this->expectException(OriginNotAllowed::class);

        $this->authorizer->assertAllowed($this->siteWith(['example.com']), 'https://evil.com');
    }

    #[Test]
    public function itRejectsAMissingOriginWhenAnAllowlistIsConfigured(): void
    {
        $this->expectException(OriginNotAllowed::class);

        $this->authorizer->assertAllowed($this->siteWith(['example.com']), null);
    }

    #[Test]
    public function itIgnoresEmptyAllowlistEntries(): void
    {
        $this->expectException(OriginNotAllowed::class);

        $this->authorizer->assertAllowed($this->siteWith(['']), 'https://example.com');
    }

    /**
     * @param list<string> $domains
     */
    private function siteWith(array $domains): Site
    {
        return EventFixtures::site([
            'allowedDomains' => $domains,
        ]);
    }
}
