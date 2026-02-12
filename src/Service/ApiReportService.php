<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

final class ApiReportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function summarizeHoursByClient(string $dateFrom, string $dateTo): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT
                c.id AS client_id,
                c.name AS client_name,
                SUM(te.hours) AS total_hours
             FROM time_entries te
             INNER JOIN clients c ON c.id = te.client_id
             WHERE te.date BETWEEN :date_from AND :date_to
             GROUP BY c.id, c.name
             ORDER BY c.name ASC',
            [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]
        );
    }
}
