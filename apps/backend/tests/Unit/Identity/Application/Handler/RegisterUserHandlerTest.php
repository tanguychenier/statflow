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

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use App\Identity\Domain\Exception\WeakPasswordException;
use App\Identity\Domain\Service\SlugAllocator;
use App\Identity\Domain\ValueObject\AuditContext;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\Clock\FixedClock;
use App\Tests\Identity\Support\InMemoryTeamMembershipRepository;
use App\Tests\Identity\Support\InMemoryTeamRepository;
use App\Tests\Identity\Support\InMemoryUserRepository;
use App\Tests\Identity\Support\PlaintextPasswordHasher;
use App\Tests\Identity\Support\RecordingAuditLogger;
use App\Tests\Identity\Support\RecordingIdentityMailer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterUserHandler::class)]
final class RegisterUserHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;

    private InMemoryTeamRepository $teams;

    private InMemoryTeamMembershipRepository $memberships;

    private RecordingIdentityMailer $mailer;

    private RecordingAuditLogger $audit;

    private RegisterUserHandler $handler;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->memberships = new InMemoryTeamMembershipRepository();
        $this->teams = new InMemoryTeamRepository($this->memberships);
        $this->mailer = new RecordingIdentityMailer();
        $this->audit = new RecordingAuditLogger();

        $this->handler = new RegisterUserHandler(
            $this->users,
            $this->teams,
            $this->memberships,
            new PlaintextPasswordHasher(),
            new SlugAllocator($this->teams),
            $this->mailer,
            $this->audit,
            new FixedClock(new DateTimeImmutable('2026-05-29T10:00:00+00:00')),
        );
    }

    #[Test]
    public function itRegistersAUserWithAPersonalTeamAndOwnerMembership(): void
    {
        $view = ($this->handler)(new RegisterUserCommand('Alice@Example.com', 'correcthorse12', 'Alice', AuditContext::system()));

        self::assertSame('alice@example.com', $view->email);
        self::assertSame('Alice', $view->name);
        self::assertFalse($view->emailVerified);

        $user = $this->users->findByEmail(EmailAddress::fromString('alice@example.com'));
        self::assertNotNull($user);

        $userMemberships = $this->memberships->findByUser($user->id());
        self::assertCount(1, $userMemberships);
        self::assertSame(TeamRole::Owner, $userMemberships[0]->role());
        self::assertFalse($userMemberships[0]->isPending());

        $teams = $this->teams->findTeamsForUser($user->id());
        self::assertCount(1, $teams);
        self::assertTrue($teams[0]->isPersonal());
    }

    #[Test]
    public function itDispatchesAVerificationEmailAndAuditsTheRegistration(): void
    {
        ($this->handler)(new RegisterUserCommand('bob@example.com', 'correcthorse12', 'Bob', AuditContext::system()));

        self::assertCount(1, $this->mailer->verifications);
        self::assertSame('bob@example.com', $this->mailer->verifications[0]['recipient']);
        self::assertTrue($this->audit->hasAction('user.registered'));
    }

    #[Test]
    public function itRejectsADuplicateEmail(): void
    {
        ($this->handler)(new RegisterUserCommand('dup@example.com', 'correcthorse12', 'First', AuditContext::system()));

        $this->expectException(EmailAlreadyRegisteredException::class);

        ($this->handler)(new RegisterUserCommand('dup@example.com', 'correcthorse12', 'Second', AuditContext::system()));
    }

    #[Test]
    public function itRejectsAWeakPasswordBeforePersisting(): void
    {
        try {
            ($this->handler)(new RegisterUserCommand('weak@example.com', 'short', 'Weak', AuditContext::system()));
            self::fail('Expected WeakPasswordException.');
        } catch (WeakPasswordException) {
            self::assertNull($this->users->findByEmail(EmailAddress::fromString('weak@example.com')));
        }
    }

    #[Test]
    public function personalTeamSlugsAreMadeUniqueOnCollision(): void
    {
        ($this->handler)(new RegisterUserCommand('a@example.com', 'correcthorse12', 'Sam', AuditContext::system()));
        ($this->handler)(new RegisterUserCommand('b@example.com', 'correcthorse12', 'Sam', AuditContext::system()));

        $userA = $this->users->findByEmail(EmailAddress::fromString('a@example.com'));
        $userB = $this->users->findByEmail(EmailAddress::fromString('b@example.com'));
        self::assertNotNull($userA);
        self::assertNotNull($userB);
        $first = $this->teams->findTeamsForUser($userA->id())[0];
        $second = $this->teams->findTeamsForUser($userB->id())[0];

        self::assertNotSame($first->slug()->getValue(), $second->slug()->getValue());
    }
}
