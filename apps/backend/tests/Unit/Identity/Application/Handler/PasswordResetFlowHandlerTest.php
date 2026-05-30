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

namespace App\Tests\Unit\Identity\Application\Handler;

use App\Identity\Application\Command\RequestPasswordResetCommand;
use App\Identity\Application\Command\ResetPasswordCommand;
use App\Identity\Application\Handler\RequestPasswordResetHandler;
use App\Identity\Application\Handler\ResetPasswordHandler;
use App\Identity\Domain\Exception\InvalidResetTokenException;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Service\PasswordResetTokenHasher;
use App\Identity\Domain\ValueObject\AuditContext;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Shared\Domain\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Identity\Support\InMemoryPasswordResetTokenRepository;
use App\Tests\Identity\Support\InMemoryRefreshTokenStore;
use App\Tests\Identity\Support\InMemoryUserRepository;
use App\Tests\Identity\Support\PlaintextPasswordHasher;
use App\Tests\Identity\Support\RecordingAuditLogger;
use App\Tests\Identity\Support\RecordingIdentityMailer;
use App\Tests\Identity\Support\SequenceTokenGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestPasswordResetHandler::class)]
#[CoversClass(ResetPasswordHandler::class)]
final class PasswordResetFlowHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;

    private InMemoryPasswordResetTokenRepository $tokens;

    private PlaintextPasswordHasher $hasher;

    private InMemoryRefreshTokenStore $refreshTokens;

    private RecordingIdentityMailer $mailer;

    private RecordingAuditLogger $audit;

    private FixedClock $clock;

    private RequestPasswordResetHandler $request;

    private ResetPasswordHandler $reset;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->tokens = new InMemoryPasswordResetTokenRepository();
        $this->hasher = new PlaintextPasswordHasher();
        $this->refreshTokens = new InMemoryRefreshTokenStore();
        $this->mailer = new RecordingIdentityMailer();
        $this->audit = new RecordingAuditLogger();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-05-29T10:00:00+00:00'));

        $this->request = new RequestPasswordResetHandler(
            $this->users,
            $this->tokens,
            new SequenceTokenGenerator('reset'),
            $this->mailer,
            $this->clock,
        );
        $this->reset = new ResetPasswordHandler(
            $this->users,
            $this->tokens,
            $this->hasher,
            $this->refreshTokens,
            $this->audit,
            $this->clock,
        );
    }

    #[Test]
    public function requestingAResetEmailsTheLinkForAKnownUser(): void
    {
        $this->givenUser('alice@example.com');

        ($this->request)(new RequestPasswordResetCommand('alice@example.com'));

        self::assertCount(1, $this->mailer->resetLinks);
        self::assertSame('alice@example.com', $this->mailer->resetLinks[0]['recipient']);
    }

    #[Test]
    public function requestingAResetForAnUnknownEmailIsSilent(): void
    {
        ($this->request)(new RequestPasswordResetCommand('nobody@example.com'));

        self::assertSame([], $this->mailer->resetLinks);
    }

    #[Test]
    public function aValidTokenSetsTheNewPasswordAndRevokesSessions(): void
    {
        $user = $this->givenUser('alice@example.com');
        $this->refreshTokens->issue($user->id());

        ($this->request)(new RequestPasswordResetCommand('alice@example.com'));
        $rawToken = $this->mailer->resetLinks[0]['token'];

        ($this->reset)(new ResetPasswordCommand($rawToken, 'brandnewpass12', AuditContext::system()));

        $updatedHash = $user->passwordHash();
        self::assertNotNull($updatedHash);
        self::assertTrue($this->hasher->verify(PlainPassword::fromString('brandnewpass12'), $updatedHash));
        self::assertSame(0, $this->refreshTokens->count());
        self::assertTrue($this->audit->hasAction('user.password_reset'));

        // The token is single-use: a second attempt fails.
        $this->expectException(InvalidResetTokenException::class);
        ($this->reset)(new ResetPasswordCommand($rawToken, 'anotherpass123', AuditContext::system()));
    }

    #[Test]
    public function anUnknownTokenIsRejected(): void
    {
        $this->expectException(InvalidResetTokenException::class);

        ($this->reset)(new ResetPasswordCommand('does-not-exist', 'brandnewpass12', AuditContext::system()));
    }

    #[Test]
    public function anExpiredTokenIsRejected(): void
    {
        $this->givenUser('alice@example.com');
        ($this->request)(new RequestPasswordResetCommand('alice@example.com'));
        $rawToken = $this->mailer->resetLinks[0]['token'];

        $this->clock->advance('+2 hours');

        $this->expectException(InvalidResetTokenException::class);
        ($this->reset)(new ResetPasswordCommand($rawToken, 'brandnewpass12', AuditContext::system()));
    }

    #[Test]
    public function requestingANewResetInvalidatesThePreviousToken(): void
    {
        $this->givenUser('alice@example.com');

        ($this->request)(new RequestPasswordResetCommand('alice@example.com'));
        $firstToken = $this->mailer->resetLinks[0]['token'];

        ($this->request)(new RequestPasswordResetCommand('alice@example.com'));

        $stale = $this->tokens->findByTokenHash(PasswordResetTokenHasher::hash($firstToken));
        self::assertNotNull($stale);
        self::assertTrue($stale->isConsumed());
    }

    private function givenUser(string $email): User
    {
        $user = User::register(
            Uuid::generate(),
            EmailAddress::fromString($email),
            'Alice',
            $this->hasher->hash(PlainPassword::fromString('originalpass12')),
            $this->clock->now(),
        );
        $this->users->save($user);

        return $user;
    }
}
