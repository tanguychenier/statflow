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

use App\Identity\Application\Command\LoginCommand;
use App\Identity\Application\DTO\AuthenticationResult;
use App\Identity\Application\Service\TeamClaimsAssembler;
use App\Identity\Domain\Exception\InvalidCredentialsException;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\AccessTokenIssuer;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\RefreshTokenStore;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Shared\Domain\Clock\Clock;

/**
 * Authenticates email + password and starts a session: issues an access token
 * carrying the user's team claims and a rotating refresh token. Failures are
 * reported uniformly to prevent user enumeration (error catalog:
 * invalid-credentials). On success the password is transparently rehashed if the
 * stored cost is outdated.
 */
final readonly class LoginHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private AccessTokenIssuer $accessTokenIssuer,
        private RefreshTokenStore $refreshTokenStore,
        private TeamClaimsAssembler $claimsAssembler,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(LoginCommand $command): AuthenticationResult
    {
        $email = EmailAddress::fromString($command->email);
        // Verify-only wrapping: a wrong-length password is invalid credentials,
        // not a policy violation, so we never reveal which field was wrong.
        $password = PlainPassword::forVerification($command->password);

        $user = $this->users->findByEmail($email);
        $hash = $user?->passwordHash();

        if ($user === null || $hash === null || !$this->passwordHasher->verify($password, $hash)) {
            throw new InvalidCredentialsException();
        }

        $now = $this->clock->now();
        $this->rehashIfNeeded($user, $password, $hash, $now);
        $user->recordLogin($now);
        $this->users->save($user);

        $claims = $this->claimsAssembler->forUser($user->id());
        $accessToken = $this->accessTokenIssuer->issue($user->id(), $claims);
        $refreshToken = $this->refreshTokenStore->issue($user->id());

        $this->auditLogger->record(
            action: 'user.logged_in',
            context: $command->auditContext,
            resourceType: 'user',
            resourceId: $user->id()->getValue(),
        );

        return new AuthenticationResult($accessToken, $refreshToken);
    }

    private function rehashIfNeeded(
        User $user,
        PlainPassword $password,
        HashedPassword $hash,
        \DateTimeImmutable $now,
    ): void {
        if ($this->passwordHasher->needsRehash($hash)) {
            $user->changePassword($this->passwordHasher->hash($password), $now);
        }
    }
}
