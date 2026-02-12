<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final class TimeEntryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function listForPeriod(string $dateFrom, string $dateTo, ?int $clientId = null): array
    {
        $where = 'te.date BETWEEN :date_from AND :date_to';
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        if ($clientId !== null) {
            $where .= ' AND te.client_id = :client_id';
            $params['client_id'] = $clientId;
        }

        return $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT
                te.id,
                te.date,
                te.start_time,
                te.end_time,
                te.hours,
                te.comment,
                c.name AS client_name,
                wc.name AS work_category_name
             FROM time_entries te
             INNER JOIN clients c ON c.id = te.client_id
             INNER JOIN work_categories wc ON wc.id = te.work_category_id
             WHERE {$where}
             ORDER BY te.date DESC, te.start_time DESC, te.id DESC",
            $params
        );
    }

    public function summarizeDailyForPeriod(string $dateFrom, string $dateTo, ?int $clientId = null): array
    {
        $where = 'te.date BETWEEN :date_from AND :date_to';
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        if ($clientId !== null) {
            $where .= ' AND te.client_id = :client_id';
            $params['client_id'] = $clientId;
        }

        return $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT te.date, SUM(te.hours) AS total_hours
             FROM time_entries te
             WHERE {$where}
             GROUP BY te.date
             ORDER BY te.date DESC",
            $params
        );
    }

    public function findById(int $id): ?array
    {
        $entry = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id, client_id, work_category_id, date, start_time, end_time, comment
             FROM time_entries
             WHERE id = :id',
            ['id' => $id]
        );

        return $entry === false ? null : $entry;
    }

    public function validate(array $entry): array
    {
        $errors = [];
        if (($entry['date'] ?? '') === '') {
            $errors[] = '日付は必須です。';
        }
        if (($entry['client_id'] ?? '') === '') {
            $errors[] = 'クライアントは必須です。';
        }
        if (($entry['work_category_id'] ?? '') === '') {
            $errors[] = '作業分類は必須です。';
        }
        if (!$this->isQuarterTime((string) ($entry['start_time'] ?? ''))) {
            $errors[] = '開始時刻は15分刻みで指定してください。';
        }
        if (!$this->isQuarterTime((string) ($entry['end_time'] ?? ''))) {
            $errors[] = '終了時刻は15分刻みで指定してください。';
        }

        return $errors;
    }

    public function calculateHours(string $start, string $end): float
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));

        $startMinutes = ($startHour * 60) + $startMinute;
        $endMinutes = ($endHour * 60) + $endMinute;
        if ($startMinutes === $endMinutes) {
            throw new InvalidArgumentException('開始時刻と終了時刻は同一にできません。');
        }
        if ($startMinutes > $endMinutes) {
            $endMinutes += 24 * 60;
        }

        return round(($endMinutes - $startMinutes) / 60, 2);
    }

    public function create(array $entry, float $hours): void
    {
        $now = date('Y-m-d H:i:s');
        $this->entityManager->getConnection()->insert('time_entries', [
            'client_id' => (int) $entry['client_id'],
            'work_category_id' => (int) $entry['work_category_id'],
            'date' => $entry['date'],
            'start_time' => $entry['start_time'] . ':00',
            'end_time' => $entry['end_time'] . ':00',
            'hours' => $hours,
            'comment' => $entry['comment'] !== '' ? $entry['comment'] : null,
            'created' => $now,
            'modified' => $now,
        ]);
    }

    public function update(int $id, array $entry, float $hours): void
    {
        $this->entityManager->getConnection()->update('time_entries', [
            'client_id' => (int) $entry['client_id'],
            'work_category_id' => (int) $entry['work_category_id'],
            'date' => $entry['date'],
            'start_time' => $entry['start_time'] . ':00',
            'end_time' => $entry['end_time'] . ':00',
            'hours' => $hours,
            'comment' => $entry['comment'] !== '' ? $entry['comment'] : null,
            'modified' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->entityManager->getConnection()->delete('time_entries', ['id' => $id]);
    }

    public function buildTimeOptions(): array
    {
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            foreach ([0, 15, 30, 45] as $minute) {
                $options[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return $options;
    }

    public function normalizeInput(array $data): array
    {
        return [
            'date' => trim((string) ($data['date'] ?? '')),
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'work_category_id' => trim((string) ($data['work_category_id'] ?? '')),
            'start_time' => trim((string) ($data['start_time'] ?? '')),
            'end_time' => trim((string) ($data['end_time'] ?? '')),
            'comment' => trim((string) ($data['comment'] ?? '')),
        ];
    }

    public function has(int $id): bool
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM time_entries WHERE id = :id',
            ['id' => $id]
        ) > 0;
    }

    private function isQuarterTime(string $time): bool
    {
        return (bool) preg_match('/^\d{2}:(00|15|30|45)$/', $time);
    }
}
