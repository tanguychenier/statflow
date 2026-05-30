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

namespace App\Sites\Infrastructure\Http\Controller;

use App\Sites\Application\Command\CreateSiteCommand;
use App\Sites\Application\Dto\PaginatedSites;
use App\Sites\Application\Dto\SiteView;
use App\Sites\Application\Query\ListSitesQuery;
use App\Sites\Domain\Port\SiteListCriteria;
use App\Sites\Infrastructure\Http\ActingUserResolver;
use App\Sites\Infrastructure\Http\BusDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Collection endpoints for sites: list (GET) and create (POST).
 *
 * @see openapi listSites, createSite
 */
final readonly class SiteCollectionController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
    ) {
    }

    #[Route('/api/v1/sites', name: 'api_v1_sites_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', SiteListCriteria::DEFAULT_LIMIT);

        $query = new ListSitesQuery(
            actingUserId: $this->actingUser->userId(),
            teamId: $this->nullableString($request->query->get('team_id')),
            search: $this->nullableString($request->query->get('search')),
            cursor: $this->nullableString($request->query->get('cursor')),
            limit: $limit,
        );

        /** @var PaginatedSites $result */
        $result = $this->handle($this->queryBus, $query);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites', name: 'api_v1_sites_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new CreateSiteCommand(
            actingUserId: $this->actingUser->userId(),
            teamId: $this->requireString($body, 'team_id'),
            name: $this->requireString($body, 'name'),
            domain: $this->requireString($body, 'domain'),
            timezone: $this->optionalString($body, 'timezone', 'UTC'),
        );

        /** @var SiteView $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_CREATED);
    }
}
