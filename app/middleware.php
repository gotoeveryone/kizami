<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app): void {
    $container = $app->getContainer();
    $twig = $container->get(Twig::class);
    $app->add(TwigMiddleware::create($app, $twig));
    $app->add($container->get(AuthMiddleware::class));
};
