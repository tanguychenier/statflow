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

use App\Identity\Application\Command\ChangePasswordCommand;
use App\Identity\Application\Command\UpdateUserProfileCommand;
use App\Identity\Application\Handler\ChangePasswordHandler;
use App\Identity\Application\Handler\GetUserProfileHandler;
use App\Identity\Application\Handler\UpdateUserProfileHandler;
use App\Identity\Application\Query\GetUserProfileQuery;
use App\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use App\Identity\Domain\Exception\InvalidCredentialsException;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\ValueObject\AuditContext;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Shared\Domain\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Identity\Support\InMemoryRefreshTokenStore;
use App\Tests\Identity\Support\InMemoryUserRepository;
use App\Tests\Identity\Support\PlaintextPasswordHasher;
use App\Tests\Identity\Support\RecordingAuditLogger;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetUserProfileHandler::class)]
#[CoversClass(UpdateUserProfileHandler::class)]
#[CoversClass(ChangePasswordHandler::class)]
final class UserProfileHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;

    private PlaintextPasswordHasher $hasher;

    private InMemoryRefreshTokenStore $refreshTokens;

    private RecordingAuditLogger $audit;

    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->hasher = new PlaintextPasswordHasher();
        $this->refreshTokens = new InMemoryRefreshTokenStore();
        $this->audit = new RecordingAuditLogger();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-05-29T10:00:00+00:00'));
    }

    #[Test]
    public function itReturnsTheCurrentProfile(): void
    {
        $user = $this->givenUser('alice@example.com');

        $view = (new GetUserProfileHandler($this->users))(new GetUserProfileQuery($user->id()->getValue()));

        self::assertSame('alice@example.com', $view->email);
    }

    #[Test]
    public function gettingAMissingUserFails(): void
    {
        $this->expectException(UserNotFoundException::class);

        (new GetUserProfileHandler($this->users))(new GetUserProfileQuery(Uuid::generate()->getValue()));
    }

    #[Test]
    public function itUpdatesNameAndEmail(): void
    {
        $user = $this->givenUser('alice@example.com');
        $handler = new UpdateUserProfileHandler($this->users, $this->audit, $this->clock);

        $view = $handler(new UpdateUserProfileCommand($user->id()->getValue(), 'Alice Smith', 'asmith@example.com', AuditContext::system()));

        self::assertSame('Alice Smith', $view->name);
        self::assertSame('asmith@example.com', $view->email);
        self::assertTrue($this->audit->hasAction('user.profile_updated'));
    }

    #[Test]
    public function changingEmailToOneInUseFails(): void
    {
        $this->givenUser('taken@example.com');
        $user = $this->givenUser('alice@example.com');
        $handler = new UpdateUserProfileHandler($this->users, $this->audit, $this->clock);

        $this->expectException(EmailAlreadyRegisteredException::class);

        $handler(new UpdateUserProfileCommand($user->id()->getValue(), null, 'taken@example.com', AuditContext::system()));
    }

    #[Test]
    public function anEmptyUpdateDoesNotAudit(): void
    {
        $user = $this->givenUser('alice@example.com');
        $handler = new UpdateUserProfileHandler($this->users, $this->audit, $this->clock);

        $handler(new UpdateUserProfileCommand($user->id()->getValue(), null, null, AuditContext::system()));

        self::assertSame([], $this->audit->entries);
    }

    #[Test]
    public function itChangesThePasswordAndRevokesSessions(): void
    {
        $user = $this->givenUser('alice@example.com');
        $this->refreshTokens->issue($user->id());
        $handler = new ChangePasswordHandler($this->users, $this->hasher, $this->refreshTokens, $this->audit, $this->clock);

        $handler(new ChangePasswordCommand($user->id()->getValue(), 'originalpass12', 'newsecret1234', AuditContext::system()));

        $hash = $user->passwordHash();
        self::assertNotNull($hash);
        self::assertTrue($this->hasher->verify(PlainPassword::fromString('newsecret1234'), $hash));
        self::assertSame(0, $this->refreshTokens->count());
        self::assertTrue($this->audit->hasAction('user.password_changed'));
    }

    #[Test]
    public function changingPasswordWithWrongCurrentFails(): void
    {
        $user = $this->givenUser('alice@example.com');
        $handler = new ChangePasswordHandler($this->users, $this->hasher, $this->refreshTokens, $this->audit, $this->clock);

        $this->expectException(InvalidCredentialsException::class);

        $handler(new ChangePasswordCommand($user->id()->getValue(), 'wrongcurrent1', 'newsecret1234', AuditContext::system()));
    }

    private function givenUser(string $email): User
    {
        $user = User::register(
            Uuid::generate(),
            EmailAddress::fromString($email),
            'Alice',
            $this->hasher->hash(PlainPassword::fromString('originalpass12')),
            $this->clock->now(),
        );
        $this->users->save($user);

        return $user;
    }
}
