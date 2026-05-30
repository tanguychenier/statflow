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

namespace App\Tests\Unit\Shared\Infrastructure\Validation;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Infrastructure\Validation\ConstraintViolationConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[CoversClass(ConstraintViolationConverter::class)]
final class ConstraintViolationConverterTest extends TestCase
{
    #[Test]
    public function itStripsArrayAccessorNoiseFromPropertyPaths(): void
    {
        $violations = new ConstraintViolationList([
            $this->violation('data_retention_days must be positive.', '[data_retention_days]', 'min_value'),
        ]);

        $errors = (new ConstraintViolationConverter())->toFieldErrors($violations);

        self::assertCount(1, $errors);
        self::assertSame('data_retention_days', $errors[0]->field);
        self::assertSame('min_value', $errors[0]->code);
        self::assertSame('data_retention_days must be positive.', $errors[0]->message);
    }

    #[Test]
    public function itDefaultsTheCodeWhenSymfonyExposesAUuidCode(): void
    {
        $violations = new ConstraintViolationList([
            $this->violation('pathname is required.', 'pathname', 'c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
        ]);

        $errors = (new ConstraintViolationConverter())->toFieldErrors($violations);

        self::assertSame('invalid_value', $errors[0]->code);
    }

    #[Test]
    public function itDefaultsTheCodeWhenNoneIsPresent(): void
    {
        $violations = new ConstraintViolationList([
            $this->violation('invalid', 'field', null),
        ]);

        $errors = (new ConstraintViolationConverter())->toFieldErrors($violations);

        self::assertSame('invalid_value', $errors[0]->code);
    }

    #[Test]
    public function itBuildsAValidationException(): void
    {
        $violations = new ConstraintViolationList([
            $this->violation('pathname is required.', 'pathname', 'required'),
            $this->violation('hostname is required.', 'hostname', 'required'),
        ]);

        $exception = (new ConstraintViolationConverter())->toException($violations);

        self::assertInstanceOf(ValidationException::class, $exception);
        self::assertCount(2, $exception->getErrors());
        self::assertSame(422, $exception->getStatusCode());
    }

    private function violation(string $message, string $propertyPath, ?string $code): ConstraintViolation
    {
        return new ConstraintViolation($message, $message, [], null, $propertyPath, null, null, $code);
    }
}
