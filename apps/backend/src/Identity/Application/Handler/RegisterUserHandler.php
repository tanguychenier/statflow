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

namespace App\Identity\Application\Handler;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\DTO\UserView;
use App\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use App\Identity\Domain\Model\Team;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\IdentityMailer;
use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\Port\TeamRepository;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\Service\SlugAllocator;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Registers a new account: validates the email and password policy, creates the
 * user (unverified, bcrypt hash), provisions a personal team with the user as
 * founding owner, dispatches a verification email, and records an audit entry.
 */
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users,
        private TeamRepository $teams,
        private TeamMembershipRepository $memberships,
        private PasswordHasher $passwordHasher,
        private SlugAllocator $slugAllocator,
        private IdentityMailer $mailer,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(RegisterUserCommand $command): UserView
    {
        $email = EmailAddress::fromString($command->email);
        $password = PlainPassword::fromString($command->password);

        if ($this->users->existsByEmail($email)) {
            throw EmailAlreadyRegisteredException::forEmail($email);
        }

        $now = $this->clock->now();
        $user = User::register(
            Uuid::generate(),
            $email,
            $command->name,
            $this->passwordHasher->hash($password),
            $now,
        );
        $this->users->save($user);

        $this->provisionPersonalTeam($user, $now);

        $this->mailer->sendEmailVerification($email, $user->name());

        $this->auditLogger->record(
            action: 'user.registered',
            context: $command->auditContext,
            resourceType: 'user',
            resourceId: $user->id()->getValue(),
            payload: [
                'email' => $email->getValue(),
            ],
        );

        return UserView::fromEntity($user);
    }

    private function provisionPersonalTeam(User $user, \DateTimeImmutable $now): void
    {
        $teamName = sprintf("%s's Team", $user->name());
        $slug = $this->slugAllocator->allocate($teamName);

        $team = Team::createPersonal(Uuid::generate(), $teamName, $slug, $user->id(), $now);
        $this->teams->save($team);

        $membership = TeamMembership::founder(Uuid::generate(), $team->id(), $user->id(), $now);
        $this->memberships->save($membership);
    }
}
