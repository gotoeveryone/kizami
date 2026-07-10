<?php

declare(strict_types=1);

namespace Tests\Presentation\Dashboard;

use App\Presentation\Dashboard\DashboardViewModelBuilder;
use App\Service\TimeEntrySummaryService;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DashboardViewModelBuilderTest extends TestCase
{
    #[Test]
    public function buildShouldCreateFourMonthsAndSortRowsByHoursThenName(): void
    {
        $viewModel = $this->build([
            [
                ['client_id' => 1, 'client_name' => 'Beta', 'month_key' => '2026-03', 'total_hours' => '4.0'],
                ['client_id' => 2, 'client_name' => 'Alpha', 'month_key' => '2026-03', 'total_hours' => '4.0'],
                ['client_id' => 3, 'client_name' => 'Gamma', 'month_key' => '2026-03', 'total_hours' => '2.345'],
            ],
            [['month_key' => '2026-03', 'total_hours' => '10.345']],
            [], [], [], [],
        ]);

        self::assertSame(['2026-03', '2026-02', '2026-01', '2025-12'], array_column($viewModel['months'], 'key'));
        self::assertSame(['Alpha', 'Beta', 'Gamma'], array_column($viewModel['clientRows'], 'client_name'));
        self::assertSame(4.0, $viewModel['clientRows'][0]['hours_by_month']['2026-03']);
        self::assertSame(0.0, $viewModel['clientRows'][0]['hours_by_month']['2026-02']);
        self::assertSame(10.35, $viewModel['columnTotals']['2026-03']);
        self::assertSame(4.0, $viewModel['clientRows'][1]['hours_by_month']['2026-03']);
        self::assertSame(2.35, $viewModel['clientRows'][2]['hours_by_month']['2026-03']);
    }

    #[Test]
    public function buildShouldCreateFiveWeeksAcrossMonthBoundaryAndSortWeeklyRows(): void
    {
        $viewModel = $this->build([
            [], [],
            [
                ['client_id' => 1, 'client_name' => 'Beta', 'week_start' => '2026-03-02', 'total_hours' => '5.0'],
                ['client_id' => 2, 'client_name' => 'Alpha', 'week_start' => '2026-03-02', 'total_hours' => '5.0'],
                ['client_id' => 3, 'client_name' => 'Gamma', 'week_start' => '2026-03-02', 'total_hours' => '3.0'],
                ['client_id' => 1, 'client_name' => 'Beta', 'week_start' => '2026-02-23', 'total_hours' => '1.25'],
            ],
            [
                ['week_start' => '2026-03-02', 'total_hours' => '13.0'],
                ['week_start' => '2026-02-23', 'total_hours' => '1.25'],
            ],
            [], [],
        ]);

        self::assertSame(
            ['2026-03-02', '2026-02-23', '2026-02-16', '2026-02-09', '2026-02-02'],
            array_column($viewModel['weekSummaries'], 'key'),
        );
        self::assertSame(
            ['Alpha', 'Beta', 'Gamma'],
            array_column($viewModel['weekSummaryClientRows'], 'client_name'),
        );
        self::assertSame(5.0, $viewModel['weekSummaryClientRows'][0]['hours_by_week']['2026-03-02']);
        self::assertSame(0.0, $viewModel['weekSummaryClientRows'][0]['hours_by_week']['2026-02-23']);
        self::assertSame(5.0, $viewModel['weekSummaryClientRows'][1]['hours_by_week']['2026-03-02']);
        self::assertSame(13.0, $viewModel['weekSummaryColumnTotals']['2026-03-02']);
    }

    #[Test]
    public function buildShouldCreateCurrentWeekAndCalculateDailyRowsAndTotals(): void
    {
        $viewModel = $this->build([
            [], [], [], [],
            [
                ['client_id' => 1, 'client_name' => 'Beta', 'date_key' => '2026-03-02', 'total_hours' => '1.25'],
                ['client_id' => 1, 'client_name' => 'Beta', 'date_key' => '2026-03-04', 'total_hours' => '2.75'],
                ['client_id' => 2, 'client_name' => 'Alpha', 'date_key' => '2026-03-04', 'total_hours' => '4.0'],
                ['client_id' => 3, 'client_name' => 'Gamma', 'date_key' => '2026-03-05', 'total_hours' => '2.5'],
            ],
            [
                ['work_date' => '2026-03-02', 'total_hours' => '1.25'],
                ['work_date' => '2026-03-04', 'total_hours' => '6.75'],
                ['work_date' => '2026-03-05', 'total_hours' => '2.5'],
            ],
        ]);

        self::assertSame(
            ['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08'],
            array_column($viewModel['weekDates'], 'key'),
        );
        self::assertSame(
            ['Alpha', 'Beta', 'Gamma'],
            array_column($viewModel['weeklyClientRows'], 'client_name'),
        );
        self::assertSame(4.0, $viewModel['weeklyClientRows'][0]['weekly_total_hours']);
        self::assertSame(4.0, $viewModel['weeklyClientRows'][1]['weekly_total_hours']);
        self::assertSame(2.5, $viewModel['weeklyClientRows'][2]['weekly_total_hours']);
        self::assertSame(0.0, $viewModel['weeklyClientRows'][0]['hours_by_date']['2026-03-03']);
        self::assertSame(10.5, $viewModel['weeklyGrandTotal']);
        self::assertSame(6.75, $viewModel['weeklyColumnTotals']['2026-03-04']);
        self::assertTrue($viewModel['weekDates'][2]['is_today']);
        self::assertTrue($viewModel['weekDates'][5]['is_saturday']);
        self::assertTrue($viewModel['weekDates'][6]['is_sunday']);
    }

    #[Test]
    public function buildShouldPassExpectedRangesToAllSummaryQueries(): void
    {
        $queryParameters = [];
        $this->build([[], [], [], [], [], []], $queryParameters);

        self::assertSame([
            ['date_from' => '2025-12-01', 'date_to' => '2026-03-31'],
            ['date_from' => '2025-12-01', 'date_to' => '2026-03-31'],
            ['date_from' => '2026-02-02', 'date_to' => '2026-03-08'],
            ['date_from' => '2026-02-02', 'date_to' => '2026-03-08'],
            ['date_from' => '2026-03-02', 'date_to' => '2026-03-08'],
            ['date_from' => '2026-03-02', 'date_to' => '2026-03-08'],
        ], $queryParameters);
    }

    #[Test]
    public function buildShouldReturnEveryDashboardTemplateInput(): void
    {
        self::assertSame([
            'months', 'clientRows', 'columnTotals', 'weekDates', 'weeklyClientRows',
            'weeklyColumnTotals', 'weeklyGrandTotal', 'weekSummaries', 'weekSummaryClientRows',
            'weekSummaryColumnTotals',
        ], array_keys($this->build([[], [], [], [], [], []])));
    }

    private function build(array $queryRows, ?array &$queryParameters = null): array
    {
        $results = [];
        foreach ($queryRows as $rows) {
            $result = $this->createMock(Result::class);
            $result->method('fetchAllAssociative')->willReturn($rows);
            $results[] = $result;
        }

        $queryBuilder = $this->createMock(QueryBuilder::class);
        foreach (['select', 'from', 'innerJoin', 'where', 'setParameter', 'groupBy', 'addGroupBy', 'orderBy', 'addOrderBy'] as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }
        $capturedParameters = [];
        $currentParameters = [];
        $queryBuilder->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use (&$currentParameters, $queryBuilder): QueryBuilder {
                $currentParameters[$name] = $value;

                return $queryBuilder;
            },
        );
        $queryBuilder->method('executeQuery')->willReturnCallback(
            function () use (&$capturedParameters, &$currentParameters, &$results): Result {
                $capturedParameters[] = $currentParameters;
                $currentParameters = [];

                return array_shift($results);
            },
        );
        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $viewModel = (new DashboardViewModelBuilder(new TimeEntrySummaryService($entityManager)))->build(
            new DateTimeImmutable('2026-03-04 18:30:00', new DateTimeZone('Asia/Tokyo')),
        );
        $queryParameters = array_map(
            static fn (array $parameters): array => array_map(
                static fn (mixed $value): string => $value instanceof DateTimeImmutable ? $value->format('Y-m-d') : (string) $value,
                $parameters,
            ),
            $capturedParameters,
        );

        return $viewModel;
    }
}
