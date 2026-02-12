<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

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
$app->addErrorMiddleware($displayErrorDetails, true, true);

$app->run();
