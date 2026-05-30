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

namespace App\Identity\Domain\Model;

use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * An authenticated human user — maps to the `users` table (postgres-schema.sql
 * §1). Persisted fields are primitives so the persistence adapter hydrates
 * without value-object factories; the rich behaviour is exposed through
 * value-object accessors. Soft deletion frees the (partial-unique) email for
 * reuse. Persistence mapping is declared in the Infrastructure layer (ADR-0004).
 */
class User
{
    private readonly string $id;

    private string $email;

    private ?string $avatarUrl = null;

    private ?string $passwordHash = null;

    private bool $emailVerified = false;

    private ?DateTimeImmutable $lastLoginAt = null;

    private string $timezone = 'UTC';

    private string $locale = 'en';

    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $deletedAt = null;

    private function __construct(
        Uuid $id,
        EmailAddress $email,
        private string $name,
        private readonly DateTimeImmutable $createdAt
    ) {
        $this->id = $id->getValue();
        $this->email = $email->getValue();
        $this->updatedAt = $this->createdAt;
    }

    public static function register(
        Uuid $id,
        EmailAddress $email,
        string $name,
        HashedPassword $passwordHash,
        DateTimeImmutable $now,
    ): self {
        $user = new self($id, $email, $name, $now);
        $user->passwordHash = $passwordHash->getValue();

        return $user;
    }

    public function id(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function email(): EmailAddress
    {
        return EmailAddress::fromString($this->email);
    }

    public function changeEmail(EmailAddress $email, DateTimeImmutable $now): void
    {
        if ($this->email === $email->getValue()) {
            return;
        }

        $this->email = $email->getValue();
        $this->emailVerified = false;
        $this->touch($now);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name, DateTimeImmutable $now): void
    {
        if ($this->name === $name) {
            return;
        }

        $this->name = $name;
        $this->touch($now);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function changeAvatarUrl(?string $avatarUrl, DateTimeImmutable $now): void
    {
        $this->avatarUrl = $avatarUrl;
        $this->touch($now);
    }

    public function passwordHash(): ?HashedPassword
    {
        return $this->passwordHash === null ? null : HashedPassword::fromHash($this->passwordHash);
    }

    public function changePassword(HashedPassword $passwordHash, DateTimeImmutable $now): void
    {
        $this->passwordHash = $passwordHash->getValue();
        $this->touch($now);
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function verifyEmail(DateTimeImmutable $now): void
    {
        if ($this->emailVerified) {
            return;
        }

        $this->emailVerified = true;
        $this->touch($now);
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function recordLogin(DateTimeImmutable $now): void
    {
        $this->lastLoginAt = $now;
        $this->touch($now);
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function changeTimezone(string $timezone, DateTimeImmutable $now): void
    {
        $this->timezone = $timezone;
        $this->touch($now);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function changeLocale(string $locale, DateTimeImmutable $now): void
    {
        $this->locale = $locale;
        $this->touch($now);
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        if ($this->deletedAt !== null) {
            return;
        }

        $this->deletedAt = $now;
        $this->touch($now);
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
