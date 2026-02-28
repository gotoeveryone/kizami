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
        $weekDates = $this->buildCurrentWeekDates();
        $weekRange = $this->buildDateRangeForDates($weekDates);
        $weeklyClientSummaries = $this->timeEntrySummaryService->summarizeHoursByClientByDate(
            $weekRange['from'],
            $weekRange['to'],
        );
        $weeklyTotalSummaries = $this->timeEntrySummaryService->summarizeTotalHoursByDate(
            $weekRange['from'],
            $weekRange['to'],
        );
        $weekDateKeys = array_map(static fn (DateTimeInterface $date): string => $date->format('Y-m-d'), $weekDates);
        $weeklyClientRows = $this->buildWeeklyClientRows($weeklyClientSummaries, $weekDateKeys);
        $weeklyColumnTotals = $this->buildWeeklyColumnTotals($weeklyTotalSummaries, $weekDateKeys);
        $weeklyGrandTotal = round(array_sum($weeklyColumnTotals), 2);

        return Twig::fromRequest($request)->render($response, 'dashboard.html.twig', [
            'months' => $this->formatMonths($months),
            'clientRows' => array_values($rowsByClient),
            'columnTotals' => $columnTotals,
            'weekDates' => $this->formatWeekDates($weekDates),
            'weeklyClientRows' => $weeklyClientRows,
            'weeklyColumnTotals' => $weeklyColumnTotals,
            'weeklyGrandTotal' => $weeklyGrandTotal,
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

    private function buildDateRangeForDates(array $dates): array
    {
        return [
            'from' => $dates[0]->format('Y-m-d'),
            'to' => $dates[array_key_last($dates)]->format('Y-m-d'),
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

    private function buildCurrentWeekDates(): array
    {
        $dates = [];
        $start = new DateTimeImmutable('monday this week');

        for ($offset = 0; $offset < 7; $offset++) {
            $dates[] = $start->modify(sprintf('+%d day', $offset));
        }

        return $dates;
    }

    private function buildWeeklyClientRows(array $weeklyClientSummaries, array $weekDateKeys): array
    {
        $rowsByClient = [];
        foreach ($weeklyClientSummaries as $summary) {
            $dateKey = (string) $summary['date_key'];
            if (!in_array($dateKey, $weekDateKeys, true)) {
                continue;
            }

            $clientId = (int) $summary['client_id'];
            if (!isset($rowsByClient[$clientId])) {
                $rowsByClient[$clientId] = [
                    'client_name' => (string) $summary['client_name'],
                    'hours_by_date' => [],
                    'weekly_total_hours' => 0.0,
                ];
            }

            $hours = round((float) $summary['total_hours'], 2);
            $rowsByClient[$clientId]['hours_by_date'][$dateKey] = $hours;
            $rowsByClient[$clientId]['weekly_total_hours'] += $hours;
        }

        foreach ($rowsByClient as &$row) {
            foreach ($weekDateKeys as $dateKey) {
                $row['hours_by_date'][$dateKey] = round((float) ($row['hours_by_date'][$dateKey] ?? 0.0), 2);
            }
            $row['weekly_total_hours'] = round((float) $row['weekly_total_hours'], 2);
        }
        unset($row);

        uasort($rowsByClient, static function (array $left, array $right): int {
            $leftHours = (float) $left['weekly_total_hours'];
            $rightHours = (float) $right['weekly_total_hours'];
            if ($leftHours === $rightHours) {
                return strcmp((string) $left['client_name'], (string) $right['client_name']);
            }

            return $rightHours <=> $leftHours;
        });

        return array_values($rowsByClient);
    }

    private function buildWeeklyColumnTotals(array $weeklyTotalSummaries, array $weekDateKeys): array
    {
        $columnTotals = array_fill_keys($weekDateKeys, 0.0);
        foreach ($weeklyTotalSummaries as $summary) {
            $dateKey = (string) $summary['date_key'];
            if (!array_key_exists($dateKey, $columnTotals)) {
                continue;
            }
            $columnTotals[$dateKey] = round((float) $summary['total_hours'], 2);
        }

        return $columnTotals;
    }

    private function formatWeekDates(array $weekDates): array
    {
        $todayKey = (new DateTimeImmutable('today'))->format('Y-m-d');

        return array_map(static function (DateTimeInterface $date) use ($todayKey): array {
            $dateKey = $date->format('Y-m-d');
            $dayOfWeek = (int) $date->format('N');

            return [
                'key' => $dateKey,
                'label' => sprintf('%s (%s)', $date->format('n/j'), self::weekdayLabel($date)),
                'is_today' => $dateKey === $todayKey,
                'is_saturday' => $dayOfWeek === 6,
                'is_sunday' => $dayOfWeek === 7,
            ];
        }, $weekDates);
    }

    private static function weekdayLabel(DateTimeInterface $date): string
    {
        return match ((int) $date->format('N')) {
            1 => '月',
            2 => '火',
            3 => '水',
            4 => '木',
            5 => '金',
            6 => '土',
            default => '日',
        };
    }
}
