<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app): void {
    $container = $app->getContainer();
    $twig = $container->get(Twig::class);
    $guard = new Guard($app->getResponseFactory());

    $app->add(TwigMiddleware::create($app, $twig));
    $app->add($container->get(AuthMiddleware::class));
    $app->add(function (Request $request, RequestHandlerInterface $handler) use ($guard, $twig) {
        $nameKey = $guard->getTokenNameKey();
        $valueKey = $guard->getTokenValueKey();

        $twig->getEnvironment()->addGlobal('csrf', [
            'keys' => [
                'name' => $nameKey,
                'value' => $valueKey,
            ],
            'name' => $request->getAttribute($nameKey),
            'value' => $request->getAttribute($valueKey),
        ]);

        return $handler->handle($request);
    });
    $app->add($guard);
};
