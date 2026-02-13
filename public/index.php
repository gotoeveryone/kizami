<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// PHP-DI コンテナ構築
$containerBuilder = new ContainerBuilder();

if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);

$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);

$container = $containerBuilder->build();

// Slim アプリ作成 (PHP-DI Bridge)
$app = Bridge::create($container);

// ミドルウェア登録
$middleware = require __DIR__ . '/../app/middleware.php';
$middleware($app);

// ルート登録
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

// エラーハンドリング
$displayErrorDetails = (bool) ($container->get('settings')['slim']['displayErrorDetails'] ?? false);
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
) use ($container, $app): Response {
    if (str_starts_with($request->getUri()->getPath(), '/api/')) {
        $response = $app->getResponseFactory()->createResponse(404);
        $response->getBody()->write(json_encode(['error' => 'Not Found'], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    $response = $app->getResponseFactory()->createResponse(404);

    return $container->get(Twig::class)->render($response, 'errors/404.html.twig', [
        'title' => '404 Not Found',
        'hideTopNav' => false,
    ]);
});

$app->run();
