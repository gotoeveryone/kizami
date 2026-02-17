<?php

declare(strict_types=1);

use App\Controller\ApiReportController;
use App\Controller\AuthController;
use App\Controller\ClientsController;
use App\Controller\HomeController;
use App\Controller\TimeEntriesController;
use App\Controller\WorkCategoriesController;
use Slim\App;

return function (App $app): void {
    $app->get('/login', AuthController::class . ':showLogin');
    $app->post('/login', AuthController::class . ':login');
    $app->post('/logout', AuthController::class . ':logout');

    $app->get('/', HomeController::class . ':index');
    $app->get('/time-entries', TimeEntriesController::class . ':index');
    $app->post('/time-entries', TimeEntriesController::class . ':store');
    $app->get('/time-entries/{id}/edit', TimeEntriesController::class . ':edit');
    $app->post('/time-entries/{id}', TimeEntriesController::class . ':update');
    $app->post('/time-entries/{id}/delete', TimeEntriesController::class . ':delete');

    $app->get('/clients', ClientsController::class . ':index');
    $app->post('/clients', ClientsController::class . ':store');
    $app->get('/clients/{id}/edit', ClientsController::class . ':edit');
    $app->post('/clients/{id}', ClientsController::class . ':update');
    $app->post('/clients/{id}/hide', ClientsController::class . ':hide');
    $app->post('/clients/{id}/show', ClientsController::class . ':show');

    $app->get('/work-categories', WorkCategoriesController::class . ':index');
    $app->post('/work-categories', WorkCategoriesController::class . ':store');
    $app->get('/work-categories/{id}/edit', WorkCategoriesController::class . ':edit');
    $app->post('/work-categories/{id}', WorkCategoriesController::class . ':update');
    $app->post('/work-categories/{id}/delete', WorkCategoriesController::class . ':delete');

    $app->get('/api/v1/reports/hours', ApiReportController::class . ':summarize');
};
