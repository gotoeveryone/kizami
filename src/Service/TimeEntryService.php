<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\Client;
use App\Domain\Entity\TimeEntry;
use App\Domain\Entity\WorkCategory;
use DateTimeImmutable;
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
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('te', 'c', 'wc')
            ->from(TimeEntry::class, 'te')
            ->join('te.client', 'c')
            ->join('te.workCategory', 'wc')
            ->where('te.date BETWEEN :date_from AND :date_to')
            ->setParameter('date_from', $this->parseDate($dateFrom))
            ->setParameter('date_to', $this->parseDate($dateTo))
            ->orderBy('te.date', 'DESC')
            ->addOrderBy('te.startTime', 'DESC')
            ->addOrderBy('te.id', 'DESC');

        if ($clientId !== null) {
            $queryBuilder
                ->andWhere('c.id = :client_id')
                ->setParameter('client_id', $clientId);
        }

        /** @var TimeEntry[] $entries */
        $entries = $queryBuilder->getQuery()->getResult();

        return array_map(static fn (TimeEntry $entry): array => [
            'id' => $entry->getId(),
            'date' => $entry->getDate()->format('Y-m-d'),
            'start_time' => $entry->getStartTime()->format('H:i'),
            'end_time' => $entry->getEndTime()->format('H:i'),
            'hours' => (float) $entry->getHours(),
            'comment' => $entry->getComment(),
            'client_name' => $entry->getClient()->getName(),
            'work_category_name' => $entry->getWorkCategory()->getName(),
        ], $entries);
    }

    public function summarizeDailyForPeriod(string $dateFrom, string $dateTo, ?int $clientId = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('te.date AS work_date', 'SUM(te.hours) AS total_hours')
            ->from(TimeEntry::class, 'te')
            ->join('te.client', 'c')
            ->where('te.date BETWEEN :date_from AND :date_to')
            ->setParameter('date_from', $this->parseDate($dateFrom))
            ->setParameter('date_to', $this->parseDate($dateTo))
            ->groupBy('te.date')
            ->orderBy('te.date', 'DESC');

        if ($clientId !== null) {
            $queryBuilder
                ->andWhere('c.id = :client_id')
                ->setParameter('client_id', $clientId);
        }

        $rows = $queryBuilder->getQuery()->getArrayResult();

        return array_map(static fn (array $row): array => [
            'date' => ($row['work_date'] instanceof DateTimeImmutable)
                ? $row['work_date']->format('Y-m-d')
                : (string) $row['work_date'],
            'total_hours' => (float) $row['total_hours'],
        ], $rows);
    }

    public function findById(int $id): ?array
    {
        $entry = $this->entityManager->find(TimeEntry::class, $id);
        if (!$entry instanceof TimeEntry) {
            return null;
        }

        return [
            'id' => $entry->getId(),
            'client_id' => $entry->getClient()->getId(),
            'work_category_id' => $entry->getWorkCategory()->getId(),
            'date' => $entry->getDate()->format('Y-m-d'),
            'start_time' => $entry->getStartTime()->format('H:i'),
            'end_time' => $entry->getEndTime()->format('H:i'),
            'comment' => $entry->getComment(),
        ];
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

        return $errors;
    }

    public function calculateHours(string $start, string $end): float
    {
        $timeEntry = new TimeEntry();
        $timeEntry->setStartTime($this->parseTime($start));
        $timeEntry->setEndTime($this->parseTime($end));

        return (float) $timeEntry->getHours();
    }

    public function create(array $entry): void
    {
        $client = $this->findVisibleClient((int) $entry['client_id']);

        $workCategory = $this->entityManager->find(WorkCategory::class, (int) $entry['work_category_id']);
        if (!$workCategory instanceof WorkCategory) {
            throw new InvalidArgumentException('作業分類が存在しません。');
        }

        $timeEntry = new TimeEntry();
        $timeEntry->setClient($client);
        $timeEntry->setWorkCategory($workCategory);
        $timeEntry->setDate($this->parseDate((string) $entry['date']));
        $timeEntry->setStartTime($this->parseTime((string) $entry['start_time']));
        $timeEntry->setEndTime($this->parseTime((string) $entry['end_time']));
        $timeEntry->setComment($entry['comment'] !== '' ? (string) $entry['comment'] : null);

        $this->entityManager->persist($timeEntry);
        $this->entityManager->flush();
    }

    public function update(int $id, array $entry): void
    {
        $timeEntry = $this->entityManager->find(TimeEntry::class, $id);
        if (!$timeEntry instanceof TimeEntry) {
            return;
        }

        if (!$timeEntry->getClient()->isVisible()) {
            throw new InvalidArgumentException('非表示クライアントなので編集できません。');
        }

        $client = $this->findVisibleClient((int) $entry['client_id']);

        $workCategory = $this->entityManager->find(WorkCategory::class, (int) $entry['work_category_id']);
        if (!$workCategory instanceof WorkCategory) {
            throw new InvalidArgumentException('作業分類が存在しません。');
        }

        $timeEntry->setClient($client);
        $timeEntry->setWorkCategory($workCategory);
        $timeEntry->setDate($this->parseDate((string) $entry['date']));
        $timeEntry->setStartTime($this->parseTime((string) $entry['start_time']));
        $timeEntry->setEndTime($this->parseTime((string) $entry['end_time']));
        $timeEntry->setComment($entry['comment'] !== '' ? (string) $entry['comment'] : null);

        $this->entityManager->flush();
    }

    public function delete(int $id): void
    {
        $timeEntry = $this->entityManager->find(TimeEntry::class, $id);
        if (!$timeEntry instanceof TimeEntry) {
            return;
        }

        $this->entityManager->remove($timeEntry);
        $this->entityManager->flush();
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
        return $this->entityManager->find(TimeEntry::class, $id) instanceof TimeEntry;
    }

    private function parseDate(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('日付形式が不正です。');
        }

        return $parsed;
    }

    private function parseTime(string $time): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('H:i', $time);
        if (!$parsed instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('時刻形式が不正です。');
        }

        return $parsed;
    }

    private function findVisibleClient(int $clientId): Client
    {
        $client = $this->entityManager->find(Client::class, $clientId);
        if (!$client instanceof Client) {
            throw new InvalidArgumentException('クライアントが存在しません。');
        }
        if (!$client->isVisible()) {
            throw new InvalidArgumentException('非表示クライアントなので編集できません。');
        }

        return $client;
    }
}
