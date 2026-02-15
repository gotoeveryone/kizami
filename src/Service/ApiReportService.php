<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\TimeEntry;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final class ApiReportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function summarizeHoursByClient(string $dateFrom, string $dateTo): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('c.id AS client_id', 'c.name AS client_name', 'SUM(te.hours) AS total_hours')
            ->from(TimeEntry::class, 'te')
            ->join('te.client', 'c')
            ->where('te.date BETWEEN :date_from AND :date_to')
            ->setParameter('date_from', $this->parseDate($dateFrom))
            ->setParameter('date_to', $this->parseDate($dateTo))
            ->groupBy('c.id, c.name')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'client_id' => (int) $row['client_id'],
            'client_name' => (string) $row['client_name'],
            'total_hours' => (float) $row['total_hours'],
        ], $rows);
    }

    private function parseDate(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('日付形式が不正です。');
        }

        return $parsed;
    }
}
