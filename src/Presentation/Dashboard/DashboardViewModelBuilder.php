<?php

declare(strict_types=1);

namespace App\Presentation\Dashboard;

use App\Service\TimeEntrySummaryService;
use DateTimeImmutable;
use DateTimeInterface;

final class DashboardViewModelBuilder
{
    public function __construct(
        private readonly TimeEntrySummaryService $timeEntrySummaryService,
    ) {
    }

    public function build(DateTimeImmutable $baseDate): array
    {
        $months = $this->buildRecentMonths($baseDate, 4);
        $monthKeys = array_map(static fn (DateTimeInterface $month): string => $month->format('Y-m'), $months);
        $monthRange = [
            'from' => end($months)->format('Y-m-01'),
            'to' => $months[0]->format('Y-m-t'),
        ];
        $clientRows = $this->buildClientRows(
            $this->timeEntrySummaryService->summarizeHoursByClientByMonth($monthRange['from'], $monthRange['to']),
            $monthKeys,
        );
        $columnTotals = $this->buildColumnTotals(
            $this->timeEntrySummaryService->summarizeTotalHoursByMonth($monthRange['from'], $monthRange['to']),
            $monthKeys,
        );

        $weeks = $this->buildRecentWeeks($baseDate, 5);
        $weekKeys = array_map(static fn (DateTimeInterface $week): string => $week->format('Y-m-d'), $weeks);
        $weekRange = [
            'from' => end($weeks)->format('Y-m-d'),
            'to' => $weeks[0]->modify('+6 days')->format('Y-m-d'),
        ];
        $weekSummaryClientRows = $this->buildWeekSummaryClientRows(
            $this->timeEntrySummaryService->summarizeHoursByClientByWeek($weekRange['from'], $weekRange['to']),
            $weekKeys,
        );
        $weekSummaryColumnTotals = $this->buildWeekSummaryColumnTotals(
            $this->timeEntrySummaryService->summarizeTotalHoursByWeek($weekRange['from'], $weekRange['to']),
            $weekKeys,
        );

        $weekDates = $this->buildCurrentWeekDates($baseDate);
        $weekDateKeys = array_map(static fn (DateTimeInterface $date): string => $date->format('Y-m-d'), $weekDates);
        $dateRange = [
            'from' => $weekDateKeys[0],
            'to' => $weekDateKeys[array_key_last($weekDateKeys)],
        ];
        $weeklyClientRows = $this->buildWeeklyClientRows(
            $this->timeEntrySummaryService->summarizeHoursByClientByDate($dateRange['from'], $dateRange['to']),
            $weekDateKeys,
        );
        $weeklyColumnTotals = $this->buildWeeklyColumnTotals(
            $this->timeEntrySummaryService->summarizeTotalHoursByDate($dateRange['from'], $dateRange['to']),
            $weekDateKeys,
        );

        return [
            'months' => $this->formatMonths($months),
            'clientRows' => array_values($clientRows),
            'columnTotals' => $columnTotals,
            'weekDates' => $this->formatWeekDates($weekDates, $baseDate),
            'weeklyClientRows' => $weeklyClientRows,
            'weeklyColumnTotals' => $weeklyColumnTotals,
            'weeklyGrandTotal' => round(array_sum($weeklyColumnTotals), 2),
            'weekSummaries' => $this->formatWeekSummaries($weeks),
            'weekSummaryClientRows' => $weekSummaryClientRows,
            'weekSummaryColumnTotals' => $weekSummaryColumnTotals,
        ];
    }

    /** @return list<DateTimeImmutable> */
    private function buildRecentMonths(DateTimeImmutable $baseDate, int $count): array
    {
        $base = $baseDate->modify('first day of this month');
        $months = [];
        for ($offset = 0; $offset < $count; $offset++) {
            $months[] = $base->modify(sprintf('-%d month', $offset));
        }

        return $months;
    }

    /** @return list<DateTimeImmutable> */
    private function buildRecentWeeks(DateTimeImmutable $baseDate, int $count): array
    {
        $base = $baseDate->modify('monday this week');
        $weeks = [];
        for ($offset = 0; $offset < $count; $offset++) {
            $weeks[] = $base->modify(sprintf('-%d week', $offset));
        }

        return $weeks;
    }

    /** @return list<DateTimeImmutable> */
    private function buildCurrentWeekDates(DateTimeImmutable $baseDate): array
    {
        $start = $baseDate->modify('monday this week');
        $dates = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $dates[] = $start->modify(sprintf('+%d day', $offset));
        }

        return $dates;
    }

    private function buildClientRows(array $summaries, array $keys): array
    {
        $rows = [];
        foreach ($summaries as $summary) {
            $key = (string) $summary['month_key'];
            if (!in_array($key, $keys, true)) {
                continue;
            }
            $clientId = (int) $summary['client_id'];
            $rows[$clientId] ??= ['client_name' => (string) $summary['client_name'], 'hours_by_month' => []];
            $rows[$clientId]['hours_by_month'][$key] = (float) $summary['total_hours'];
        }

        $this->fillAndSortRows($rows, $keys, 'hours_by_month');

        return $rows;
    }

    private function buildWeekSummaryClientRows(array $summaries, array $keys): array
    {
        $rows = [];
        foreach ($summaries as $summary) {
            $key = (string) $summary['week_key'];
            if (!in_array($key, $keys, true)) {
                continue;
            }
            $clientId = (int) $summary['client_id'];
            $rows[$clientId] ??= ['client_name' => (string) $summary['client_name'], 'hours_by_week' => []];
            $rows[$clientId]['hours_by_week'][$key] = (float) $summary['total_hours'];
        }

        $this->fillAndSortRows($rows, $keys, 'hours_by_week');

        return array_values($rows);
    }

    private function buildWeeklyClientRows(array $summaries, array $keys): array
    {
        $rows = [];
        foreach ($summaries as $summary) {
            $key = (string) $summary['date_key'];
            if (!in_array($key, $keys, true)) {
                continue;
            }
            $clientId = (int) $summary['client_id'];
            $rows[$clientId] ??= [
                'client_name' => (string) $summary['client_name'],
                'hours_by_date' => [],
                'weekly_total_hours' => 0.0,
            ];
            $hours = round((float) $summary['total_hours'], 2);
            $rows[$clientId]['hours_by_date'][$key] = $hours;
            $rows[$clientId]['weekly_total_hours'] += $hours;
        }

        foreach ($rows as &$row) {
            foreach ($keys as $key) {
                $row['hours_by_date'][$key] = round((float) ($row['hours_by_date'][$key] ?? 0.0), 2);
            }
            $row['weekly_total_hours'] = round((float) $row['weekly_total_hours'], 2);
        }
        unset($row);
        uasort($rows, static function (array $left, array $right): int {
            if ($left['weekly_total_hours'] === $right['weekly_total_hours']) {
                return strcmp($left['client_name'], $right['client_name']);
            }

            return $right['weekly_total_hours'] <=> $left['weekly_total_hours'];
        });

        return array_values($rows);
    }

    private function fillAndSortRows(array &$rows, array $keys, string $hoursKey): void
    {
        foreach ($rows as &$row) {
            foreach ($keys as $key) {
                $row[$hoursKey][$key] = round((float) ($row[$hoursKey][$key] ?? 0.0), 2);
            }
        }
        unset($row);
        $currentKey = $keys[0];
        uasort($rows, static function (array $left, array $right) use ($hoursKey, $currentKey): int {
            $leftHours = $left[$hoursKey][$currentKey];
            $rightHours = $right[$hoursKey][$currentKey];
            if ($leftHours === $rightHours) {
                return strcmp($left['client_name'], $right['client_name']);
            }

            return $rightHours <=> $leftHours;
        });
    }

    private function buildColumnTotals(array $summaries, array $keys): array
    {
        return $this->buildTotals($summaries, $keys, 'month_key');
    }

    private function buildWeekSummaryColumnTotals(array $summaries, array $keys): array
    {
        return $this->buildTotals($summaries, $keys, 'week_key');
    }

    private function buildWeeklyColumnTotals(array $summaries, array $keys): array
    {
        return $this->buildTotals($summaries, $keys, 'date_key');
    }

    private function buildTotals(array $summaries, array $keys, string $keyName): array
    {
        $totals = array_fill_keys($keys, 0.0);
        foreach ($summaries as $summary) {
            $key = (string) $summary[$keyName];
            if (array_key_exists($key, $totals)) {
                $totals[$key] = round((float) $summary['total_hours'], 2);
            }
        }

        return $totals;
    }

    private function formatMonths(array $months): array
    {
        return array_map(static fn (DateTimeInterface $month): array => [
            'key' => $month->format('Y-m'),
            'label' => $month->format('Y年n月'),
        ], $months);
    }

    private function formatWeekSummaries(array $weeks): array
    {
        return array_map(static fn (DateTimeInterface $week): array => [
            'key' => $week->format('Y-m-d'),
            'label' => sprintf('%s - %s', $week->format('n/j'), $week->modify('+6 days')->format('n/j')),
        ], $weeks);
    }

    private function formatWeekDates(array $dates, DateTimeImmutable $baseDate): array
    {
        $todayKey = $baseDate->format('Y-m-d');

        return array_map(static function (DateTimeInterface $date) use ($todayKey): array {
            $dayOfWeek = (int) $date->format('N');

            return [
                'key' => $date->format('Y-m-d'),
                'label' => sprintf('%s (%s)', $date->format('n/j'), self::weekdayLabel($date)),
                'is_today' => $date->format('Y-m-d') === $todayKey,
                'is_saturday' => $dayOfWeek === 6,
                'is_sunday' => $dayOfWeek === 7,
            ];
        }, $dates);
    }

    private static function weekdayLabel(DateTimeInterface $date): string
    {
        return match ((int) $date->format('N')) {
            1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土', default => '日',
        };
    }
}
