<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\TimeEntry;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final class TimeEntrySummaryService
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
            ->groupBy('c.id, c.name, c.sortOrder')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'client_id' => (int) $row['client_id'],
            'client_name' => (string) $row['client_name'],
            'total_hours' => (float) $row['total_hours'],
        ], $rows);
    }

    public function summarizeHoursByClientByMonth(string $dateFrom, string $dateTo): array
    {
        $queryBuilder = $this->entityManager->getConnection()->createQueryBuilder();

        $rows = $queryBuilder
            ->select(
                'c.id AS client_id',
                'c.name AS client_name',
                "DATE_FORMAT(te.date, '%Y-%m') AS month_key",
                'SUM(te.hours) AS total_hours',
            )
            ->from('time_entries', 'te')
            ->innerJoin('te', 'clients', 'c', 'c.id = te.client_id')
            ->where('te.date BETWEEN :date_from AND :date_to')
            ->setParameter('date_from', $dateFrom)
            ->setParameter('date_to', $dateTo)
            ->groupBy('c.id')
            ->addGroupBy('c.name')
            ->addGroupBy('c.sort_order')
            ->addGroupBy('month_key')
            ->orderBy('c.sort_order', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('month_key', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'client_id' => (int) $row['client_id'],
            'client_name' => (string) $row['client_name'],
            'month_key' => (string) $row['month_key'],
            'total_hours' => (float) $row['total_hours'],
        ], $rows);
    }

    public function summarizeTotalHoursByMonth(string $dateFrom, string $dateTo): array
    {
        $queryBuilder = $this->entityManager->getConnection()->createQueryBuilder();

        $rows = $queryBuilder
            ->select(
                "DATE_FORMAT(te.date, '%Y-%m') AS month_key",
                'SUM(te.hours) AS total_hours',
            )
            ->from('time_entries', 'te')
            ->where('te.date BETWEEN :date_from AND :date_to')
            ->setParameter('date_from', $dateFrom)
            ->setParameter('date_to', $dateTo)
            ->groupBy('month_key')
            ->orderBy('month_key', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'month_key' => (string) $row['month_key'],
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
