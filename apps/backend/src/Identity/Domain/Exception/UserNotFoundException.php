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

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Domain\ValueObject\Uuid;

final class UserNotFoundException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::NotFound;
    }

    public static function withId(Uuid $id): self
    {
        return new self(sprintf('User "%s" was not found.', $id->getValue()));
    }

    public static function withEmail(EmailAddress $email): self
    {
        return new self(sprintf('No user with the email "%s" was found.', $email->getValue()));
    }
}
