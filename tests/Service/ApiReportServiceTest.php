<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ApiReportService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiReportServiceTest extends TestCase
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
        $queryBuilder->method('getQuery')->willReturn($query);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

        $service = new ApiReportService($entityManager);

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
}
