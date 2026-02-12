<?php

declare(strict_types=1);

use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app): void {
    $twig = $app->getContainer()->get(Twig::class);
    $app->add(TwigMiddleware::create($app, $twig));
};
