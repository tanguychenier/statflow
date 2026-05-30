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

use App\Identity\Application\Command\LoginCommand;
use App\Identity\Application\Handler\LoginHandler;
use App\Identity\Application\Service\TeamClaimsAssembler;
use App\Identity\Domain\Exception\InvalidCredentialsException;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\ValueObject\AuditContext;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Shared\Domain\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Identity\Support\FakeAccessTokenIssuer;
use App\Tests\Identity\Support\InMemoryRefreshTokenStore;
use App\Tests\Identity\Support\InMemoryTeamMembershipRepository;
use App\Tests\Identity\Support\InMemoryUserRepository;
use App\Tests\Identity\Support\PlaintextPasswordHasher;
use App\Tests\Identity\Support\RecordingAuditLogger;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginHandler::class)]
final class LoginHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;

    private InMemoryTeamMembershipRepository $memberships;

    private PlaintextPasswordHasher $hasher;

    private FakeAccessTokenIssuer $issuer;

    private InMemoryRefreshTokenStore $refreshTokens;

    private RecordingAuditLogger $audit;

    private FixedClock $clock;

    private LoginHandler $handler;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->memberships = new InMemoryTeamMembershipRepository();
        $this->hasher = new PlaintextPasswordHasher();
        $this->issuer = new FakeAccessTokenIssuer();
        $this->refreshTokens = new InMemoryRefreshTokenStore();
        $this->audit = new RecordingAuditLogger();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-05-29T10:00:00+00:00'));

        $this->handler = new LoginHandler(
            $this->users,
            $this->hasher,
            $this->issuer,
            $this->refreshTokens,
            new TeamClaimsAssembler($this->memberships),
            $this->audit,
            $this->clock,
        );
    }

    #[Test]
    public function itIssuesTokensOnValidCredentials(): void
    {
        $user = $this->givenUser('alice@example.com', 'correcthorse12');
        $this->givenAcceptedMembership($user->id());

        $result = ($this->handler)(new LoginCommand('alice@example.com', 'correcthorse12', AuditContext::system()));

        self::assertStringContainsString('jwt-for-', $result->accessToken->jwt);
        self::assertSame(900, $result->accessToken->expiresInSeconds);
        self::assertNotSame('', $result->refreshToken->value);
        self::assertCount(1, $this->issuer->lastTeams);
        self::assertTrue($this->audit->hasAction('user.logged_in'));
        self::assertNotNull($user->lastLoginAt());
    }

    #[Test]
    public function itRejectsAWrongPassword(): void
    {
        $this->givenUser('alice@example.com', 'correcthorse12');

        $this->expectException(InvalidCredentialsException::class);

        ($this->handler)(new LoginCommand('alice@example.com', 'wrongpassword1', AuditContext::system()));
    }

    #[Test]
    public function itRejectsAnUnknownEmailWithTheSameError(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        ($this->handler)(new LoginCommand('nobody@example.com', 'correcthorse12', AuditContext::system()));
    }

    #[Test]
    public function aShortPasswordIsTreatedAsInvalidCredentialsNotValidation(): void
    {
        $this->givenUser('alice@example.com', 'correcthorse12');

        $this->expectException(InvalidCredentialsException::class);

        ($this->handler)(new LoginCommand('alice@example.com', 'short', AuditContext::system()));
    }

    #[Test]
    public function itRehashesThePasswordWhenTheStoredHashIsOutdated(): void
    {
        $user = $this->givenUser('alice@example.com', 'correcthorse12');
        $this->hasher->rehashNeeded = true;
        $originalHash = $user->passwordHash()?->getValue();

        ($this->handler)(new LoginCommand('alice@example.com', 'correcthorse12', AuditContext::system()));

        self::assertSame($originalHash, $user->passwordHash()?->getValue());
        $currentHash = $user->passwordHash();
        self::assertNotNull($currentHash);
        self::assertTrue($this->hasher->verify(
            PlainPassword::fromString('correcthorse12'),
            $currentHash,
        ));
    }

    private function givenUser(string $email, string $password): User
    {
        $user = User::register(
            Uuid::generate(),
            EmailAddress::fromString($email),
            'Alice',
            $this->hasher->hash(PlainPassword::fromString($password)),
            $this->clock->now(),
        );
        $this->users->save($user);

        return $user;
    }

    private function givenAcceptedMembership(Uuid $userId): void
    {
        $this->memberships->save(TeamMembership::founder(Uuid::generate(), Uuid::generate(), $userId, $this->clock->now()));
    }
}
