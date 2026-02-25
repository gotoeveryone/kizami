<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TimeEntrySummaryService;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class HomeController
{
    public function __construct(
        private readonly TimeEntrySummaryService $timeEntrySummaryService,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $months = $this->buildRecentMonths(4);
        $dateRange = $this->buildDateRange($months);
        $clientSummaries = $this->timeEntrySummaryService->summarizeHoursByClientByMonth(
            $dateRange['from'],
            $dateRange['to'],
        );
        $totalSummaries = $this->timeEntrySummaryService->summarizeTotalHoursByMonth(
            $dateRange['from'],
            $dateRange['to'],
        );
        $monthKeys = array_map(static fn (DateTimeInterface $month): string => $month->format('Y-m'), $months);
        $rowsByClient = $this->buildClientRows($clientSummaries, $monthKeys);
        $columnTotals = $this->buildColumnTotals($totalSummaries, $monthKeys);

        return Twig::fromRequest($request)->render($response, 'dashboard.html.twig', [
            'months' => $this->formatMonths($months),
            'clientRows' => array_values($rowsByClient),
            'columnTotals' => $columnTotals,
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

    private function buildDateRange(array $months): array
    {
        $oldestMonth = $months[array_key_last($months)];

        return [
            'from' => $oldestMonth->format('Y-m-01'),
            'to' => $months[0]->format('Y-m-t'),
        ];
    }

    private function buildClientRows(array $clientSummaries, array $monthKeys): array
    {
        $rowsByClient = [];

        foreach ($clientSummaries as $summary) {
            $monthKey = (string) $summary['month_key'];
            if (!in_array($monthKey, $monthKeys, true)) {
                continue;
            }

            $clientId = (int) $summary['client_id'];
            if (!isset($rowsByClient[$clientId])) {
                $rowsByClient[$clientId] = [
                    'client_name' => (string) $summary['client_name'],
                    'hours_by_month' => [],
                ];
            }

            $hours = (float) $summary['total_hours'];
            $rowsByClient[$clientId]['hours_by_month'][$monthKey] = $hours;
        }

        foreach ($rowsByClient as &$row) {
            foreach ($monthKeys as $monthKey) {
                $row['hours_by_month'][$monthKey] = round((float) ($row['hours_by_month'][$monthKey] ?? 0.0), 2);
            }
        }
        unset($row);

        $currentMonthKey = $monthKeys[0];
        uasort($rowsByClient, static function (array $left, array $right) use ($currentMonthKey): int {
            $leftHours = (float) ($left['hours_by_month'][$currentMonthKey] ?? 0.0);
            $rightHours = (float) ($right['hours_by_month'][$currentMonthKey] ?? 0.0);

            if ($leftHours === $rightHours) {
                return strcmp((string) $left['client_name'], (string) $right['client_name']);
            }

            return $rightHours <=> $leftHours;
        });

        return $rowsByClient;
    }

    private function buildColumnTotals(array $totalSummaries, array $monthKeys): array
    {
        $columnTotals = array_fill_keys($monthKeys, 0.0);
        foreach ($totalSummaries as $summary) {
            $monthKey = (string) $summary['month_key'];
            if (!array_key_exists($monthKey, $columnTotals)) {
                continue;
            }
            $columnTotals[$monthKey] = round((float) $summary['total_hours'], 2);
        }

        return $columnTotals;
    }

    private function formatMonths(array $months): array
    {
        return array_map(
            static fn (DateTimeInterface $month): array => [
                'key' => $month->format('Y-m'),
                'label' => $month->format('Y年n月'),
            ],
            $months,
        );
    }
}
