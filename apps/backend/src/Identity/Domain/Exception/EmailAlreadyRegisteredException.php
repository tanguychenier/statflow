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

final class EmailAlreadyRegisteredException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::Conflict;
    }

    public static function forEmail(EmailAddress $email): self
    {
        return new self(sprintf('An account with the email "%s" already exists.', $email->getValue()));
    }
}
