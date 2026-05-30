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

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The security-layer view of an authenticated identity. The user identifier is
 * the user's UUID (ADR-0009), so any context can resolve "who is acting" from the
 * security token alone. Carries the JWT `teams` claim and the API-key scopes (when
 * authenticated by key) for downstream authorization voters.
 */
final readonly class AuthenticatedUser implements UserInterface
{
    /**
     * @param non-empty-string                           $userId
     * @param list<array{team_id: string, role: string}> $teams
     * @param list<string>                               $scopes
     */
    public function __construct(
        private string $userId,
        private array $teams = [],
        private array $scopes = [],
        private ?string $apiKeyId = null,
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->userId;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * @return list<array{team_id: string, role: string}>
     */
    public function teams(): array
    {
        return $this->teams;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function isApiKey(): bool
    {
        return $this->apiKeyId !== null;
    }

    public function apiKeyId(): ?string
    {
        return $this->apiKeyId;
    }

    public function eraseCredentials(): void
    {
        // No sensitive transient state is held on this object.
    }
}
