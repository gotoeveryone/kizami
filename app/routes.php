<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Views\Twig;

return function (App $app): void {
    $app->get('/', function (Request $request, Response $response) {
        $view = Twig::fromRequest($request);

        return $view->render($response, 'home.html.twig', [
            'title' => 'Kizami',
        ]);
    });
};
