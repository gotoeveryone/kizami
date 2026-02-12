<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ApiReportService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiReportServiceTest extends TestCase
{
    #[Test]
    public function summarizeHoursByClientShouldPassDateRangeToQuery(): void
    {
        $expectedRows = [
            [
                'client_id' => 1,
                'client_name' => 'Acme',
                'total_hours' => '3.50',
            ],
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::stringContains('WHERE te.date BETWEEN :date_from AND :date_to'),
                [
                    'date_from' => '2026-02-01',
                    'date_to' => '2026-02-28',
                ]
            )
            ->willReturn($expectedRows);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $service = new ApiReportService($entityManager);

        $rows = $service->summarizeHoursByClient('2026-02-01', '2026-02-28');

        self::assertSame($expectedRows, $rows);
    }
}
