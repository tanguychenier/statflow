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

use App\Identity\Domain\Port\ApiKeyRepository;
use App\Identity\Domain\Service\ApiKeyFactory;
use App\Identity\Domain\ValueObject\ApiKeyScope;
use App\Identity\Infrastructure\Token\Es256AccessTokenVerifier;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Infrastructure\Http\ProblemDetailsFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Stateless `Authorization: Bearer` authenticator covering the two dashboard/API
 * surfaces of ADR-0009: a short-lived ES256 JWT (dashboard SPA), or a secret
 * programmatic API key prefixed `sfk_` (integrations). The bearer value's prefix
 * selects the path; both resolve to an {@see AuthenticatedUser} whose identifier
 * is the user's UUID. Tracker keys (`stk_`) are never accepted here — ingestion
 * uses the request body, not this header.
 */
final class BearerTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly Es256AccessTokenVerifier $tokenVerifier,
        private readonly ApiKeyRepository $apiKeys,
        private readonly Clock $clock,
        private readonly ProblemDetailsFactory $problemDetails,
    ) {
    }

    public function supports(Request $request): bool
    {
        return str_starts_with((string) $request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $bearer = substr((string) $request->headers->get('Authorization', ''), 7);

        if ($bearer === '') {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        if (str_starts_with($bearer, ApiKeyFactory::LIVE_PREFIX) || str_starts_with($bearer, ApiKeyFactory::TEST_PREFIX)) {
            return $this->authenticateApiKey($bearer);
        }

        return $this->authenticateJwt($bearer);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return $this->problemDetails->fromErrorType(
            ErrorType::AuthenticationRequired,
            detail: $exception->getMessageKey(),
            instance: $request->getRequestUri(),
        );
    }

    private function authenticateJwt(string $jwt): SelfValidatingPassport
    {
        $claims = $this->tokenVerifier->verify($jwt);

        if ($claims === null || !isset($claims['sub']) || !is_string($claims['sub']) || $claims['sub'] === '') {
            throw new CustomUserMessageAuthenticationException('The access token is invalid or expired.');
        }

        $userId = $claims['sub'];
        $teams = $this->normaliseTeams($claims['teams'] ?? []);

        return new SelfValidatingPassport(
            new UserBadge($userId, static fn (): AuthenticatedUser => new AuthenticatedUser($userId, $teams)),
        );
    }

    private function authenticateApiKey(string $rawKey): SelfValidatingPassport
    {
        $apiKey = $this->apiKeys->findByHash(ApiKeyFactory::hash($rawKey));

        if ($apiKey === null || !$apiKey->isActive($this->clock->now())) {
            throw new CustomUserMessageAuthenticationException('The API key is invalid, revoked, or expired.');
        }

        $createdBy = $apiKey->createdBy();
        $identifier = $createdBy?->getValue() ?? $apiKey->id()->getValue();
        $scopes = array_map(static fn (ApiKeyScope $scope): string => $scope->value, $apiKey->scopes());
        $keyId = $apiKey->id()->getValue();

        return new SelfValidatingPassport(
            new UserBadge(
                $identifier,
                static fn (): AuthenticatedUser => new AuthenticatedUser($identifier, [], $scopes, $keyId),
            ),
        );
    }

    /**
     * @return list<array{team_id: string, role: string}>
     */
    private function normaliseTeams(mixed $teams): array
    {
        if (!is_array($teams)) {
            return [];
        }

        $normalised = [];
        foreach ($teams as $team) {
            if (is_array($team) && isset($team['team_id'], $team['role']) && is_string($team['team_id']) && is_string($team['role'])) {
                $normalised[] = [
                    'team_id' => $team['team_id'],
                    'role' => $team['role'],
                ];
            }
        }

        return $normalised;
    }
}
