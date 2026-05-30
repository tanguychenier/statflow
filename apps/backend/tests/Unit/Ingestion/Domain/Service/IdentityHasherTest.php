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

use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Service\IdentityHasher;
use App\Tests\Ingestion\Support\FixedSaltProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentityHasher::class)]
final class IdentityHasherTest extends TestCase
{
    private const SITE_A = 'site-a';

    private const DATE = '2025-06-15';

    private const WINDOW = 29166630;

    private IdentityHasher $hasher;

    private FixedSaltProvider $salt;

    private RequestContext $context;

    protected function setUp(): void
    {
        $this->salt = new FixedSaltProvider();
        $this->hasher = new IdentityHasher($this->salt);
        $this->context = new RequestContext('203.0.113.5', 'Mozilla/5.0', 'fr-FR', null);
    }

    #[Test]
    public function itProducesA64CharLowercaseHexVisitorId(): void
    {
        $identity = $this->hasher->derive(self::SITE_A, $this->context, self::DATE, self::WINDOW);

        self::assertSame(64, strlen($identity->visitorId));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $identity->visitorId);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $identity->sessionId);
    }

    #[Test]
    public function visitorIdMatchesTheAdr0008Formula(): void
    {
        $salt = $this->salt->saltForDate(self::DATE);
        $data = "203.0.113.5\nMozilla/5.0\nfr-FR";
        $expected = hash_hmac('sha256', $data, $salt . self::SITE_A);

        $identity = $this->hasher->derive(self::SITE_A, $this->context, self::DATE, self::WINDOW);

        self::assertSame($expected, $identity->visitorId);
    }

    #[Test]
    public function sessionIdMatchesTheAdr0008Formula(): void
    {
        $salt = $this->salt->saltForDate(self::DATE);
        $data = "203.0.113.5\nMozilla/5.0\nfr-FR\n" . self::WINDOW;
        $expected = hash_hmac('sha256', $data, $salt . self::SITE_A . 'session');

        $identity = $this->hasher->derive(self::SITE_A, $this->context, self::DATE, self::WINDOW);

        self::assertSame($expected, $identity->sessionId);
    }

    #[Test]
    public function itIsolatesAcrossSitesViaTheSiteIdInTheKey(): void
    {
        $a = $this->hasher->derive('site-a', $this->context, self::DATE, self::WINDOW);
        $b = $this->hasher->derive('site-b', $this->context, self::DATE, self::WINDOW);

        self::assertNotSame($a->visitorId, $b->visitorId);
        self::assertNotSame($a->sessionId, $b->sessionId);
    }

    #[Test]
    public function visitorAndSessionIdsAreNotCorrelatable(): void
    {
        $identity = $this->hasher->derive(self::SITE_A, $this->context, self::DATE, self::WINDOW);

        self::assertNotSame($identity->visitorId, $identity->sessionId);
    }

    #[Test]
    public function itIsDeterministicWithinADay(): void
    {
        $first = $this->hasher->derive(self::SITE_A, $this->context, self::DATE, self::WINDOW);
        $second = $this->hasher->derive(self::SITE_A, $this->context, self::DATE, self::WINDOW);

        self::assertSame($first->visitorId, $second->visitorId);
    }

    #[Test]
    public function itRotatesAcrossDays(): void
    {
        $monday = $this->hasher->derive(self::SITE_A, $this->context, '2025-06-15', self::WINDOW);
        $tuesday = $this->hasher->derive(self::SITE_A, $this->context, '2025-06-16', self::WINDOW);

        self::assertNotSame($monday->visitorId, $tuesday->visitorId);
    }

    #[Test]
    public function differentSessionWindowsYieldDifferentSessionsButSameVisitor(): void
    {
        $first = $this->hasher->derive(self::SITE_A, $this->context, self::DATE, 100);
        $second = $this->hasher->derive(self::SITE_A, $this->context, self::DATE, 200);

        self::assertSame($first->visitorId, $second->visitorId);
        self::assertNotSame($first->sessionId, $second->sessionId);
    }
}
