<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ApiReportService;
use DateTimeImmutable;
use DateTimeInterface;
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
        $months = $this->buildRecentMonths(4);
        $rowsByClient = [];
        $columnTotals = [];
        $grandTotal = 0.0;

        foreach ($months as $month) {
            $monthKey = $month->format('Y-m');
            $columnTotals[$monthKey] = 0.0;
            $summaries = $this->apiReportService->summarizeHoursByClient(
                $month->format('Y-m-01'),
                $month->format('Y-m-t'),
            );

            foreach ($summaries as $summary) {
                $clientId = (int) $summary['client_id'];
                if (!isset($rowsByClient[$clientId])) {
                    $rowsByClient[$clientId] = [
                        'client_name' => (string) $summary['client_name'],
                        'hours_by_month' => [],
                        'total_hours' => 0.0,
                    ];
                }
                $hours = (float) $summary['total_hours'];
                $rowsByClient[$clientId]['hours_by_month'][$monthKey] = $hours;
                $rowsByClient[$clientId]['total_hours'] += $hours;
                $columnTotals[$monthKey] += $hours;
                $grandTotal += $hours;
            }
        }

        foreach ($rowsByClient as &$row) {
            foreach ($months as $month) {
                $monthKey = $month->format('Y-m');
                $row['hours_by_month'][$monthKey] = round((float) ($row['hours_by_month'][$monthKey] ?? 0.0), 2);
            }
            $row['total_hours'] = round((float) $row['total_hours'], 2);
        }
        unset($row);

        $currentMonthKey = $months[0]->format('Y-m');
        uasort($rowsByClient, static function (array $left, array $right) use ($currentMonthKey): int {
            $leftHours = (float) ($left['hours_by_month'][$currentMonthKey] ?? 0.0);
            $rightHours = (float) ($right['hours_by_month'][$currentMonthKey] ?? 0.0);

            if ($leftHours === $rightHours) {
                return strcmp((string) $left['client_name'], (string) $right['client_name']);
            }

            return $rightHours <=> $leftHours;
        });

        foreach ($columnTotals as $monthKey => $total) {
            $columnTotals[$monthKey] = round($total, 2);
        }
        $grandTotal = round($grandTotal, 2);

        return Twig::fromRequest($request)->render($response, 'dashboard.html.twig', [
            'title' => 'Kizami',
            'months' => array_map(
                static fn (DateTimeInterface $month): array => [
                    'key' => $month->format('Y-m'),
                    'label' => $month->format('Y年n月'),
                ],
                $months,
            ),
            'clientRows' => array_values($rowsByClient),
            'columnTotals' => $columnTotals,
            'grandTotal' => $grandTotal,
        ]);
    }

    private function buildRecentMonths(int $count): array
    {
        $months = [];
        $base = new DateTimeImmutable('first day of this month');
        for ($offset = 0; $offset < $count; $offset++) {
            $months[] = $base->modify(sprintf('-%d month', $offset));
        }

        return $months;
    }
}
