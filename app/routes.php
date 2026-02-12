<?php

declare(strict_types=1);

use App\Controller\ClientsController;
use App\Controller\TimeEntriesController;
use Slim\App;

return function (App $app): void {
    $app->get('/', TimeEntriesController::class . ':index');
    $app->post('/time-entries', TimeEntriesController::class . ':store');
    $app->get('/time-entries/{id}/edit', TimeEntriesController::class . ':edit');
    $app->post('/time-entries/{id}', TimeEntriesController::class . ':update');
    $app->post('/time-entries/{id}/delete', TimeEntriesController::class . ':delete');

    $app->get('/clients', ClientsController::class . ':index');
    $app->post('/clients', ClientsController::class . ':store');
    $app->get('/clients/{id}/edit', ClientsController::class . ':edit');
    $app->post('/clients/{id}', ClientsController::class . ':update');
    $app->post('/clients/{id}/delete', ClientsController::class . ':delete');
};
