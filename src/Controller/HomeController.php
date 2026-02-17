<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ApiReportService;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class HomeController
{
    public function __construct(
        private readonly ApiReportService $apiReportService,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $month = $this->resolveMonth((string) ($query['month'] ?? ''));
        $dateFrom = $month->format('Y-m-01');
        $dateTo = $month->format('Y-m-t');
        $rows = $this->apiReportService->summarizeHoursByClient($dateFrom, $dateTo);
        $monthlyTotal = array_reduce(
            $rows,
            static fn (float $carry, array $row): float => $carry + (float) $row['total_hours'],
            0.0,
        );

        return Twig::fromRequest($request)->render($response, 'dashboard.html.twig', [
            'title' => 'Kizami',
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->format('Y年n月'),
            'monthlyTotal' => round($monthlyTotal, 2),
            'clientSummaries' => $rows,
        ]);
    }

    private function resolveMonth(string $month): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m', $month);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        return new DateTimeImmutable('first day of this month');
    }
}
