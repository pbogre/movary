<?php declare(strict_types=1);

namespace Movary\HttpController;

use Movary\Api\Tmdb\TmdbApi;
use Movary\Domain\Movie\History\MovieHistoryApi;
use Movary\Domain\Movie\MovieApi;
use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\Service\UserPageAuthorizationChecker;
use Movary\Domain\User\UserApi;
use Movary\Service\PaginationElementsCalculator;
use Movary\Service\Tmdb\SyncMovie;
use Movary\Util\Json;
use Movary\ValueObject\Date;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Movary\ValueObject\PersonalRating;
use RuntimeException;
use Twig\Environment;

class HistoryController
{
    private const DEFAULT_LIMIT = 24;

    public function __construct(
        private readonly Environment $twig,
        private readonly MovieHistoryApi $movieHistoryApi,
        private readonly TmdbApi $tmdbApi,
        private readonly MovieApi $movieApi,
        private readonly UserApi $userApi,
        private readonly SyncMovie $tmdbMovieSyncService,
        private readonly Authentication $authenticationService,
        private readonly UserPageAuthorizationChecker $userPageAuthorizationChecker,
        private readonly PaginationElementsCalculator $paginationElementsCalculator,
    ) {
    }

    public function createHistoryEntry(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticated() === false) {
            return Response::createForbidden();
        }

        $userId = $this->authenticationService->getCurrentUserId();

        if ($this->userApi->fetchUser($userId)->getName() !== $request->getRouteParameters()['username']) {
            return Response::createForbidden();
        }

        $requestBody = Json::decode($request->getBody());

        $movieId = (int)$request->getRouteParameters()['id'];
        $watchDate = Date::createFromStringAndFormat($requestBody['watchDate'], $requestBody['dateFormat']);
        $plays = (int)$requestBody['plays'];

        $this->movieApi->replaceHistoryForMovieByDate($movieId, $userId, $watchDate, $plays);

        return Response::create(StatusCode::createNoContent());
    }

    public function deleteHistoryEntry(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticated() === false) {
            return Response::createForbidden();
        }

        $userId = $this->authenticationService->getCurrentUserId();

        if ($this->userApi->fetchUser($userId)->getName() !== $request->getRouteParameters()['username']) {
            return Response::createForbidden();
        }

        $requestBody = Json::decode($request->getBody());

        $movieId = (int)$request->getRouteParameters()['id'];
        $date = Date::createFromStringAndFormat($requestBody['date'], $requestBody['dateFormat']);

        $this->movieApi->deleteHistoryByIdAndDate($movieId, $userId, $date);

        return Response::create(StatusCode::createNoContent());
    }

    public function logMovie(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticated() === false) {
            return Response::createSeeOther('/');
        }

        $userId = $this->authenticationService->getCurrentUserId();

        $requestData = Json::decode($request->getBody());

        if (isset($requestData['watchDate'], $requestData['tmdbId'], $requestData['personalRating']) === false) {
            throw new RuntimeException('Missing parameters');
        }

        $watchDate = Date::createFromStringAndFormat($requestData['watchDate'], $requestData['dateFormat']);
        $tmdbId = (int)$requestData['tmdbId'];
        $personalRating = $requestData['personalRating'] === 0 ? null : PersonalRating::create((int)$requestData['personalRating']);

        $movie = $this->movieApi->findByTmdbId($tmdbId);

        if ($movie === null) {
            $movie = $this->tmdbMovieSyncService->syncMovie($tmdbId);
        }

        $this->movieApi->updateUserRating($movie->getId(), $userId, $personalRating);
        $this->movieApi->increaseHistoryPlaysForMovieOnDate($movie->getId(), $userId, $watchDate);

        return Response::create(StatusCode::createOk());
    }

    public function renderHistory(Request $request) : Response
    {
        $userId = $this->userPageAuthorizationChecker->findUserIdIfCurrentVisitorIsAllowedToSeeUser((string)$request->getRouteParameters()['username']);
        if ($userId === null) {
            return Response::createNotFound();
        }

        $searchTerm = $request->getGetParameters()['s'] ?? null;
        $page = $request->getGetParameters()['p'] ?? 1;
        $limit = self::DEFAULT_LIMIT;

        $historyPaginated = $this->movieHistoryApi->fetchHistoryPaginated($userId, $limit, (int)$page, $searchTerm);
        $historyCount = $this->movieHistoryApi->fetchHistoryCount($userId, $searchTerm);

        $paginationElements = $this->paginationElementsCalculator->createPaginationElements($historyCount, $limit, (int)$page);

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('page/history.html.twig', [
                'users' => $this->userPageAuthorizationChecker->fetchAllVisibleUsernamesForCurrentVisitor(),
                'historyEntries' => $historyPaginated,
                'paginationElements' => $paginationElements,
                'searchTerm' => $searchTerm,
            ]),
        );
    }

    public function renderLogMoviePage(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticated() === false) {
            return Response::createSeeOther('/');
        }

        $searchTerm = $request->getGetParameters()['s'] ?? null;

        $movies = [];
        if ($searchTerm !== null) {
            $movies = $this->tmdbApi->searchMovie($searchTerm);
        }

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('page/log-movie.html.twig', [
                'movies' => $movies,
                'searchTerm' => $searchTerm,
            ]),
        );
    }

    public function updateHistoryEntry(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticated() === false) {
            return Response::createForbidden();
        }

        $userId = $this->authenticationService->getCurrentUserId();

        if ($this->userApi->fetchUser($userId)->getName() !== $request->getRouteParameters()['username']) {
            return Response::createForbidden();
        }

        $requestBody = Json::decode($request->getBody());

        $movieId = (int)$request->getRouteParameters()['id'];
        $watchDate = Date::createFromStringAndFormat($requestBody['watchDate'], $requestBody['dateFormat']);
        $plays = (int)$requestBody['plays'];

        $this->movieApi->increaseHistoryPlaysForMovieOnDate($movieId, $userId, $watchDate, $plays);

        return Response::create(StatusCode::createNoContent());
    }
}
