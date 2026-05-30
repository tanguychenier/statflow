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

use App\Identity\Application\Command\CreateApiKeyCommand;
use App\Identity\Application\Command\RevokeApiKeyCommand;
use App\Identity\Application\Handler\CreateApiKeyHandler;
use App\Identity\Application\Handler\ListApiKeysHandler;
use App\Identity\Application\Handler\RevokeApiKeyHandler;
use App\Identity\Application\Query\ListApiKeysQuery;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Exception\ApiKeyNotFoundException;
use App\Identity\Domain\Exception\InvalidApiKeyScopeException;
use App\Identity\Domain\Exception\PermissionDeniedException;
use App\Identity\Domain\Model\Team;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Service\ApiKeyFactory;
use App\Identity\Domain\ValueObject\AuditContext;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Identity\Domain\ValueObject\TeamSlug;
use App\Shared\Domain\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Identity\Support\InMemoryApiKeyRepository;
use App\Tests\Identity\Support\InMemoryTeamMembershipRepository;
use App\Tests\Identity\Support\InMemoryTeamRepository;
use App\Tests\Identity\Support\RecordingAuditLogger;
use App\Tests\Identity\Support\SequenceTokenGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateApiKeyHandler::class)]
#[CoversClass(RevokeApiKeyHandler::class)]
#[CoversClass(ListApiKeysHandler::class)]
final class ApiKeyHandlerTest extends TestCase
{
    private InMemoryApiKeyRepository $apiKeys;

    private InMemoryTeamMembershipRepository $memberships;

    private InMemoryTeamRepository $teams;

    private TeamAccessGuard $guard;

    private ApiKeyFactory $factory;

    private RecordingAuditLogger $audit;

    private FixedClock $clock;

    private Team $team;

    private Uuid $ownerId;

    protected function setUp(): void
    {
        $this->apiKeys = new InMemoryApiKeyRepository();
        $this->memberships = new InMemoryTeamMembershipRepository();
        $this->teams = new InMemoryTeamRepository($this->memberships);
        $this->guard = new TeamAccessGuard($this->teams, $this->memberships);
        $this->factory = new ApiKeyFactory(new SequenceTokenGenerator('key'));
        $this->audit = new RecordingAuditLogger();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-05-29T10:00:00+00:00'));

        $this->ownerId = Uuid::generate();
        $this->team = Team::createShared(Uuid::generate(), 'Acme', TeamSlug::fromString('acme'), $this->ownerId, $this->clock->now());
        $this->teams->save($this->team);
        $this->memberships->save(TeamMembership::founder(Uuid::generate(), $this->team->id(), $this->ownerId, $this->clock->now()));
    }

    #[Test]
    public function itCreatesAKeyAndReturnsTheRawValueOnce(): void
    {
        $handler = $this->createHandler();

        $view = $handler(new CreateApiKeyCommand(
            $this->ownerId->getValue(),
            $this->team->id()->getValue(),
            'CI',
            ['analytics:read', 'reports:read'],
            [],
            null,
            AuditContext::system(),
        ));

        self::assertNotNull($view->rawKey);
        self::assertStringStartsWith('sfk_live_', $view->rawKey);
        self::assertStringStartsWith('sfk_live_', $view->keyPrefix);
        self::assertSame(12, strlen($view->keyPrefix));
        self::assertSame(['analytics:read', 'reports:read'], $view->scopes);
        self::assertTrue($this->audit->hasAction('api_key.created'));
    }

    #[Test]
    public function theStoredKeyOnlyHoldsTheHash(): void
    {
        $handler = $this->createHandler();
        $view = $handler(new CreateApiKeyCommand($this->ownerId->getValue(), $this->team->id()->getValue(), 'CI', ['analytics:read'], [], null, AuditContext::system()));

        $stored = $this->apiKeys->findById(Uuid::fromString($view->id));
        self::assertNotNull($stored);
        self::assertSame(ApiKeyFactory::hash((string) $view->rawKey), $stored->keyHash());
    }

    #[Test]
    public function itRejectsAnEmptyScopeList(): void
    {
        $handler = $this->createHandler();

        $this->expectException(InvalidApiKeyScopeException::class);

        $handler(new CreateApiKeyCommand($this->ownerId->getValue(), $this->team->id()->getValue(), 'CI', [], [], null, AuditContext::system()));
    }

    #[Test]
    public function itRejectsAnUnknownScope(): void
    {
        $handler = $this->createHandler();

        $this->expectException(InvalidApiKeyScopeException::class);

        $handler(new CreateApiKeyCommand($this->ownerId->getValue(), $this->team->id()->getValue(), 'CI', ['admin:all'], [], null, AuditContext::system()));
    }

    #[Test]
    public function aViewerCannotCreateAKey(): void
    {
        $viewerId = Uuid::generate();
        $viewer = TeamMembership::invite(Uuid::generate(), $this->team->id(), $viewerId, TeamRole::Viewer, $this->ownerId, $this->clock->now());
        $viewer->accept($this->clock->now());
        $this->memberships->save($viewer);
        $handler = $this->createHandler();

        $this->expectException(PermissionDeniedException::class);

        $handler(new CreateApiKeyCommand($viewerId->getValue(), $this->team->id()->getValue(), 'CI', ['analytics:read'], [], null, AuditContext::system()));
    }

    #[Test]
    public function itRevokesAKey(): void
    {
        $created = $this->createHandler()(new CreateApiKeyCommand($this->ownerId->getValue(), $this->team->id()->getValue(), 'CI', ['analytics:read'], [], null, AuditContext::system()));
        $revoke = new RevokeApiKeyHandler($this->guard, $this->apiKeys, $this->audit, $this->clock);

        $revoke(new RevokeApiKeyCommand($this->ownerId->getValue(), $created->id, AuditContext::system()));

        self::assertTrue($this->apiKeys->findById(Uuid::fromString($created->id))?->isRevoked());
        self::assertTrue($this->audit->hasAction('api_key.revoked'));
    }

    #[Test]
    public function revokingAMissingKeyFails(): void
    {
        $revoke = new RevokeApiKeyHandler($this->guard, $this->apiKeys, $this->audit, $this->clock);

        $this->expectException(ApiKeyNotFoundException::class);

        $revoke(new RevokeApiKeyCommand($this->ownerId->getValue(), Uuid::generate()->getValue(), AuditContext::system()));
    }

    #[Test]
    public function listingReturnsActiveKeysWithoutRawValues(): void
    {
        $this->createHandler()(new CreateApiKeyCommand($this->ownerId->getValue(), $this->team->id()->getValue(), 'CI', ['analytics:read'], [], null, AuditContext::system()));
        $list = new ListApiKeysHandler($this->guard, $this->apiKeys);

        $views = $list(new ListApiKeysQuery($this->ownerId->getValue(), $this->team->id()->getValue()));

        self::assertCount(1, $views);
        self::assertNull($views[0]->rawKey);
    }

    private function createHandler(): CreateApiKeyHandler
    {
        return new CreateApiKeyHandler($this->guard, $this->apiKeys, $this->factory, $this->audit, $this->clock);
    }
}
