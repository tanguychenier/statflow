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

use App\Identity\Application\Command\UpdateUserProfileCommand;
use App\Identity\Application\DTO\UserView;
use App\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Applies a partial profile update (PATCH /users/me). Changing the email rechecks
 * uniqueness and resets the verified flag; null fields are left untouched.
 */
final readonly class UpdateUserProfileHandler
{
    public function __construct(
        private UserRepository $users,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateUserProfileCommand $command): UserView
    {
        $userId = Uuid::fromString($command->userId);
        $user = $this->users->findById($userId);

        if ($user === null || $user->isDeleted()) {
            throw UserNotFoundException::withId($userId);
        }

        $now = $this->clock->now();
        $changed = [];

        if ($command->name !== null) {
            $user->rename($command->name, $now);
            $changed['name'] = $command->name;
        }

        if ($command->email !== null) {
            $email = EmailAddress::fromString($command->email);

            if (!$user->email()->equals($email) && $this->users->existsByEmail($email)) {
                throw EmailAlreadyRegisteredException::forEmail($email);
            }

            $user->changeEmail($email, $now);
            $changed['email'] = $email->getValue();
        }

        if ($changed !== []) {
            $this->users->save($user);
            $this->auditLogger->record(
                action: 'user.profile_updated',
                context: $command->auditContext,
                resourceType: 'user',
                resourceId: $userId->getValue(),
                payload: $changed,
            );
        }

        return UserView::fromEntity($user);
    }
}
