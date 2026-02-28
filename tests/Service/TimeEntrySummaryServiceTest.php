<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TimeEntrySummaryService;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimeEntrySummaryServiceTest extends TestCase
{
    #[Test]
    public function summarizeHoursByClientShouldPassDateRangeToQuery(): void
    {
        $queryRows = [
            [
                'client_id' => 1,
                'client_name' => 'Acme',
                'total_hours' => '3.50',
            ],
        ];

        $query = $this->createMock(Query::class);
        $query->expects(self::once())
            ->method('getArrayResult')
            ->willReturn($queryRows);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $capturedParameters = [];
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use (&$capturedParameters, $queryBuilder): QueryBuilder {
                $capturedParameters[$name] = $value;

                return $queryBuilder;
            }
        );
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

        $service = new TimeEntrySummaryService($entityManager);

        $rows = $service->summarizeHoursByClient('2026-02-01', '2026-02-28');

        self::assertSame([
            [
                'client_id' => 1,
                'client_name' => 'Acme',
                'total_hours' => 3.5,
            ],
        ], $rows);

        self::assertArrayHasKey('date_from', $capturedParameters);
        self::assertArrayHasKey('date_to', $capturedParameters);
        self::assertInstanceOf(DateTimeImmutable::class, $capturedParameters['date_from']);
        self::assertInstanceOf(DateTimeImmutable::class, $capturedParameters['date_to']);
        self::assertSame('2026-02-01 00:00:00', $capturedParameters['date_from']->format('Y-m-d H:i:s'));
        self::assertSame('2026-02-28 00:00:00', $capturedParameters['date_to']->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function summarizeHoursByClientByMonthShouldUseSingleQueryBuilderQuery(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'client_id' => 1,
                    'client_name' => 'Acme',
                    'month_key' => '2026-02',
                    'total_hours' => '12.50',
                ],
                [
                    'client_id' => 1,
                    'client_name' => 'Acme',
                    'month_key' => '2026-01',
                    'total_hours' => '8.00',
                ],
            ]);

        $queryBuilder = $this->createMock(DbalQueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('addGroupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $service = new TimeEntrySummaryService($entityManager);

        $rows = $service->summarizeHoursByClientByMonth('2025-11-01', '2026-02-28');

        self::assertSame([
            [
                'client_id' => 1,
                'client_name' => 'Acme',
                'month_key' => '2026-02',
                'total_hours' => 12.5,
            ],
            [
                'client_id' => 1,
                'client_name' => 'Acme',
                'month_key' => '2026-01',
                'total_hours' => 8.0,
            ],
        ], $rows);
    }

    #[Test]
    public function summarizeTotalHoursByMonthShouldUseSingleQueryBuilderQuery(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'month_key' => '2026-02',
                    'total_hours' => '20.50',
                ],
                [
                    'month_key' => '2026-01',
                    'total_hours' => '10.25',
                ],
            ]);

        $queryBuilder = $this->createMock(DbalQueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $service = new TimeEntrySummaryService($entityManager);

        $rows = $service->summarizeTotalHoursByMonth('2025-11-01', '2026-02-28');

        self::assertSame([
            [
                'month_key' => '2026-02',
                'total_hours' => 20.5,
            ],
            [
                'month_key' => '2026-01',
                'total_hours' => 10.25,
            ],
        ], $rows);
    }

    #[Test]
    public function summarizeHoursByClientByDateShouldUseSingleQueryBuilderQuery(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'client_id' => 1,
                    'client_name' => 'Acme',
                    'date_key' => '2026-02-23',
                    'total_hours' => '3.50',
                ],
                [
                    'client_id' => 1,
                    'client_name' => 'Acme',
                    'date_key' => '2026-02-24',
                    'total_hours' => '4.25',
                ],
            ]);

        $queryBuilder = $this->createMock(DbalQueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('addGroupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $service = new TimeEntrySummaryService($entityManager);

        $rows = $service->summarizeHoursByClientByDate('2026-02-23', '2026-03-01');

        self::assertSame([
            [
                'client_id' => 1,
                'client_name' => 'Acme',
                'date_key' => '2026-02-23',
                'total_hours' => 3.5,
            ],
            [
                'client_id' => 1,
                'client_name' => 'Acme',
                'date_key' => '2026-02-24',
                'total_hours' => 4.25,
            ],
        ], $rows);
    }

    #[Test]
    public function summarizeTotalHoursByDateShouldUseSingleQueryBuilderQuery(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'work_date' => '2026-02-23',
                    'total_hours' => '7.00',
                ],
                [
                    'work_date' => '2026-02-24',
                    'total_hours' => '4.25',
                ],
            ]);

        $queryBuilder = $this->createMock(DbalQueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $service = new TimeEntrySummaryService($entityManager);

        $rows = $service->summarizeTotalHoursByDate('2026-02-23', '2026-03-01');

        self::assertSame([
            [
                'date_key' => '2026-02-23',
                'total_hours' => 7.0,
            ],
            [
                'date_key' => '2026-02-24',
                'total_hours' => 4.25,
            ],
        ], $rows);
    }
}
