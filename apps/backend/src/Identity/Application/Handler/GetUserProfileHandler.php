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

use App\Identity\Application\DTO\UserView;
use App\Identity\Application\Query\GetUserProfileQuery;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Port\UserRepository;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class GetUserProfileHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(GetUserProfileQuery $query): UserView
    {
        $userId = Uuid::fromString($query->userId);
        $user = $this->users->findById($userId);

        if ($user === null || $user->isDeleted()) {
            throw UserNotFoundException::withId($userId);
        }

        return UserView::fromEntity($user);
    }
}
