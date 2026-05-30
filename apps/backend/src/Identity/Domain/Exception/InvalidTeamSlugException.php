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

use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Domain\Exception\FieldError;

final class InvalidTeamSlugException extends IdentityException
{
    public function errorType(): ErrorType
    {
        return ErrorType::ValidationFailed;
    }

    public static function forValue(string $value): self
    {
        $message = sprintf(
            '"%s" is not a valid team slug. Use lowercase letters, digits, and single hyphens (1-63 characters).',
            $value,
        );

        return new self($message, [new FieldError('slug', 'invalid_format', $message)]);
    }
}
