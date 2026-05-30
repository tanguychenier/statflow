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

namespace App\Tests\Unit\Identity\Application\Handler;

use App\Identity\Application\Command\LogoutCommand;
use App\Identity\Application\Command\RefreshSessionCommand;
use App\Identity\Application\Handler\LogoutHandler;
use App\Identity\Application\Handler\RefreshSessionHandler;
use App\Identity\Application\Service\TeamClaimsAssembler;
use App\Identity\Domain\Exception\InvalidRefreshTokenException;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\ValueObject\AuditContext;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Shared\Domain\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Identity\Support\FakeAccessTokenIssuer;
use App\Tests\Identity\Support\InMemoryRefreshTokenStore;
use App\Tests\Identity\Support\InMemoryTeamMembershipRepository;
use App\Tests\Identity\Support\InMemoryUserRepository;
use App\Tests\Identity\Support\RecordingAuditLogger;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshSessionHandler::class)]
#[CoversClass(LogoutHandler::class)]
final class RefreshAndLogoutHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;

    private InMemoryRefreshTokenStore $refreshTokens;

    private FakeAccessTokenIssuer $issuer;

    private RefreshSessionHandler $refreshHandler;

    private LogoutHandler $logoutHandler;

    private RecordingAuditLogger $audit;

    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->refreshTokens = new InMemoryRefreshTokenStore();
        $this->issuer = new FakeAccessTokenIssuer();
        $this->audit = new RecordingAuditLogger();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-05-29T10:00:00+00:00'));

        $this->refreshHandler = new RefreshSessionHandler(
            $this->refreshTokens,
            $this->issuer,
            new TeamClaimsAssembler(new InMemoryTeamMembershipRepository()),
            $this->users,
        );
        $this->logoutHandler = new LogoutHandler($this->refreshTokens, $this->audit);
    }

    #[Test]
    public function itRotatesTheRefreshTokenAndIssuesANewAccessToken(): void
    {
        $user = $this->givenUser();
        $issued = $this->refreshTokens->issue($user->id());

        $result = ($this->refreshHandler)(new RefreshSessionCommand($issued->value));

        self::assertStringContainsString('jwt-for-', $result->accessToken->jwt);
        self::assertNotSame($issued->value, $result->refreshToken->value);
        // The presented token is single-use: it no longer resolves.
        self::assertNull($this->refreshTokens->resolveUserId($issued->value));
    }

    #[Test]
    public function itRejectsAMissingCookie(): void
    {
        $this->expectException(InvalidRefreshTokenException::class);

        ($this->refreshHandler)(new RefreshSessionCommand(null));
    }

    #[Test]
    public function itRejectsAnUnknownToken(): void
    {
        $this->expectException(InvalidRefreshTokenException::class);

        ($this->refreshHandler)(new RefreshSessionCommand('rt-unknown'));
    }

    #[Test]
    public function itRevokesAndRejectsWhenTheUserNoLongerExists(): void
    {
        $orphanId = Uuid::generate();
        $token = $this->refreshTokens->issue($orphanId);

        try {
            ($this->refreshHandler)(new RefreshSessionCommand($token->value));
            self::fail('Expected InvalidRefreshTokenException.');
        } catch (InvalidRefreshTokenException) {
            self::assertNull($this->refreshTokens->resolveUserId($token->value));
        }
    }

    #[Test]
    public function logoutRevokesTheTokenAndAudits(): void
    {
        $user = $this->givenUser();
        $token = $this->refreshTokens->issue($user->id());

        ($this->logoutHandler)(new LogoutCommand($token->value, AuditContext::system()));

        self::assertNull($this->refreshTokens->resolveUserId($token->value));
        self::assertTrue($this->audit->hasAction('user.logged_out'));
    }

    #[Test]
    public function logoutWithoutATokenStillSucceeds(): void
    {
        ($this->logoutHandler)(new LogoutCommand(null, AuditContext::system()));

        self::assertTrue($this->audit->hasAction('user.logged_out'));
    }

    private function givenUser(): User
    {
        $user = User::register(
            Uuid::generate(),
            EmailAddress::fromString('alice@example.com'),
            'Alice',
            HashedPassword::fromHash('hash'),
            $this->clock->now(),
        );
        $this->users->save($user);

        return $user;
    }
}
