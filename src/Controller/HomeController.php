<?php

declare(strict_types=1);

namespace App\Controller;

use App\Presentation\Dashboard\DashboardViewModelBuilder;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class HomeController
{
    public function __construct(
        private readonly DashboardViewModelBuilder $dashboardViewModelBuilder,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render(
            $response,
            'dashboard.html.twig',
            $this->dashboardViewModelBuilder->build(new DateTimeImmutable('today')),
        );
    }
}
