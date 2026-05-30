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

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\AuthenticationRequiredException;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\DependencyUnavailableException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Domain\Exception\FieldError;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\PermissionDeniedException;
use App\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DomainException::class)]
#[CoversClass(ValidationException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(ConflictException::class)]
#[CoversClass(PermissionDeniedException::class)]
#[CoversClass(AuthenticationRequiredException::class)]
#[CoversClass(DependencyUnavailableException::class)]
final class DomainExceptionTest extends TestCase
{
    #[Test]
    public function domainExceptionsAreThrowableRuntimeExceptions(): void
    {
        self::assertInstanceOf(RuntimeException::class, new NotFoundException());
    }

    #[Test]
    public function validationExceptionMapsTo422AndCarriesFieldErrors(): void
    {
        $errors = [new FieldError('pathname', 'required', 'pathname is required.')];
        $exception = ValidationException::withErrors($errors);

        self::assertSame(ErrorType::ValidationFailed, $exception->errorType());
        self::assertSame(422, $exception->getStatusCode());
        self::assertSame('Validation Failed', $exception->getTitle());
        self::assertSame('https://statflow.io/errors/validation-failed', $exception->getType());
        self::assertSame($errors, $exception->getErrors());
    }

    #[Test]
    public function validationExceptionForFieldBuildsASingleError(): void
    {
        $exception = ValidationException::forField('limit', 'out_of_range', 'limit must be between 1 and 100.');

        self::assertCount(1, $exception->getErrors());
        self::assertSame('limit', $exception->getErrors()[0]->field);
        self::assertSame('out_of_range', $exception->getErrors()[0]->code);
        self::assertStringContainsString('limit', $exception->getMessage());
    }

    #[Test]
    public function notFoundExceptionDescribesTheResource(): void
    {
        $exception = NotFoundException::of('Site', 'abc');

        self::assertSame(404, $exception->getStatusCode());
        self::assertSame('Site "abc" was not found.', $exception->getMessage());
        self::assertSame([], $exception->getErrors());
    }

    #[Test]
    public function conflictExceptionDescribesTheAttribute(): void
    {
        $exception = ConflictException::of('domain', 'example.com');

        self::assertSame(409, $exception->getStatusCode());
        self::assertStringContainsString('example.com', $exception->getMessage());
    }

    #[Test]
    public function permissionDeniedNamesTheRequiredPermission(): void
    {
        $exception = PermissionDeniedException::requiring('sites:write');

        self::assertSame(403, $exception->getStatusCode());
        self::assertStringContainsString('sites:write', $exception->getMessage());
    }

    #[Test]
    public function authenticationRequiredMapsTo401(): void
    {
        self::assertSame(401, (new AuthenticationRequiredException())->getStatusCode());
    }

    #[Test]
    public function dependencyUnavailableMapsTo503AndPreservesCause(): void
    {
        $cause = new RuntimeException('connection refused');
        $exception = DependencyUnavailableException::named('clickhouse', $cause);

        self::assertSame(503, $exception->getStatusCode());
        self::assertStringContainsString('clickhouse', $exception->getMessage());
        self::assertSame($cause, $exception->getPrevious());
    }
}
