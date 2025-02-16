<?php declare(strict_types=1);

session_start();

/** @var DI\Container $container */

use Movary\HttpController\ErrorController;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Psr\Log\LoggerInterface;

$container = require(__DIR__ . '/../bootstrap.php');
$httpRequest = $container->get(Request::class);

try {
    $dispatcher = FastRoute\simpleDispatcher(
        require(__DIR__ . '/../settings/routes.php')
    );

    $uri = $_SERVER['REQUEST_URI'];

    // Strip query string (?foo=bar) and decode URI
    if (false !== $pos = strpos($uri, '?')) {
        $uri = substr($uri, 0, $pos);
    }
    $uri = rawurldecode($uri);

    $routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $uri);
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            $response = Response::createNotFound();
            break;
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $response = Response::createMethodNotAllowed();
            break;
        case FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $httpRequest->addRouteParameters($routeInfo[2]);

            $response = $container->call($handler, [$httpRequest]);
            break;
        default:
            throw new LogicException('Unhandled dispatcher status :' . $routeInfo[0]);
    }

    if ($response->getStatusCode()->getCode() === 404) {
        $response = $container->get(ErrorController::class)->renderNotFound($httpRequest);
    }
} catch (Throwable $t) {
    $container->get(LoggerInterface::class)->emergency($t->getMessage(), ['exception' => $t]);

    $response = $container->get(ErrorController::class)->renderInternalServerError();
}

header((string)$response->getStatusCode());
foreach ($response->getHeaders() as $header) {
    header((string)$header);
}

echo $response->getBody();

exit(0);
