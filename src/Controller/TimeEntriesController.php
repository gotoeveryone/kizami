<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TimeEntriesController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->renderHome($request, $response);
    }

    public function store(Request $request, Response $response): Response
    {
        $conn = $this->entityManager->getConnection();
        $data = (array) $request->getParsedBody();
        $old = [
            'date' => trim((string) ($data['date'] ?? '')),
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'work_category_id' => trim((string) ($data['work_category_id'] ?? '')),
            'start_time' => trim((string) ($data['start_time'] ?? '')),
            'end_time' => trim((string) ($data['end_time'] ?? '')),
            'comment' => trim((string) ($data['comment'] ?? '')),
        ];

        $errors = $this->validateTimeEntry($old);
        $hours = null;
        if ($errors === []) {
            try {
                $hours = $this->calculateHours($old['start_time'], $old['end_time']);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return $this->renderHome($request, $response, $errors, $old, 422);
        }

        $now = date('Y-m-d H:i:s');
        $conn->insert('time_entries', [
            'client_id' => (int) $old['client_id'],
            'work_category_id' => (int) $old['work_category_id'],
            'date' => $old['date'],
            'start_time' => $old['start_time'] . ':00',
            'end_time' => $old['end_time'] . ':00',
            'hours' => $hours,
            'comment' => $old['comment'] !== '' ? $old['comment'] : null,
            'created' => $now,
            'modified' => $now,
        ]);

        return $response->withHeader('Location', '/?saved=1')->withStatus(302);
    }

    public function edit(Request $request, Response $response): Response
    {
        $conn = $this->entityManager->getConnection();
        $id = (int) ($request->getAttribute('id') ?? 0);

        $entry = $conn->fetchAssociative(
            'SELECT id, client_id, work_category_id, date, start_time, end_time, comment
             FROM time_entries
             WHERE id = :id',
            ['id' => $id]
        );
        if ($entry === false) {
            $response->getBody()->write('time entry not found');

            return $response->withStatus(404);
        }

        $clients = $conn->fetchAllAssociative('SELECT id, name FROM clients ORDER BY sort_order ASC, id ASC');
        $workCategories = $conn->fetchAllAssociative('SELECT id, name FROM work_categories ORDER BY sort_order ASC, id ASC');

        return Twig::fromRequest($request)->render($response, 'time_entry_edit.html.twig', [
            'title' => '稼働時間を編集',
            'entry' => [
                'id' => $entry['id'],
                'client_id' => (string) $entry['client_id'],
                'work_category_id' => (string) $entry['work_category_id'],
                'date' => $entry['date'],
                'start_time' => substr((string) $entry['start_time'], 0, 5),
                'end_time' => substr((string) $entry['end_time'], 0, 5),
                'comment' => (string) ($entry['comment'] ?? ''),
            ],
            'clients' => $clients,
            'workCategories' => $workCategories,
            'timeOptions' => $this->buildTimeOptions(),
            'errors' => [],
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $conn = $this->entityManager->getConnection();
        $id = (int) ($request->getAttribute('id') ?? 0);

        $entryExists = (int) $conn->fetchOne('SELECT COUNT(*) FROM time_entries WHERE id = :id', ['id' => $id]) > 0;
        if (!$entryExists) {
            $response->getBody()->write('time entry not found');

            return $response->withStatus(404);
        }

        $data = (array) $request->getParsedBody();
        $entry = [
            'id' => $id,
            'date' => trim((string) ($data['date'] ?? '')),
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'work_category_id' => trim((string) ($data['work_category_id'] ?? '')),
            'start_time' => trim((string) ($data['start_time'] ?? '')),
            'end_time' => trim((string) ($data['end_time'] ?? '')),
            'comment' => trim((string) ($data['comment'] ?? '')),
        ];

        $errors = $this->validateTimeEntry($entry);
        $hours = null;
        if ($errors === []) {
            try {
                $hours = $this->calculateHours($entry['start_time'], $entry['end_time']);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            $clients = $conn->fetchAllAssociative('SELECT id, name FROM clients ORDER BY sort_order ASC, id ASC');
            $workCategories = $conn->fetchAllAssociative('SELECT id, name FROM work_categories ORDER BY sort_order ASC, id ASC');

            return Twig::fromRequest($request)->render($response->withStatus(422), 'time_entry_edit.html.twig', [
                'title' => '稼働時間を編集',
                'entry' => $entry,
                'clients' => $clients,
                'workCategories' => $workCategories,
                'timeOptions' => $this->buildTimeOptions(),
                'errors' => $errors,
            ]);
        }

        $conn->update('time_entries', [
            'client_id' => (int) $entry['client_id'],
            'work_category_id' => (int) $entry['work_category_id'],
            'date' => $entry['date'],
            'start_time' => $entry['start_time'] . ':00',
            'end_time' => $entry['end_time'] . ':00',
            'hours' => $hours,
            'comment' => $entry['comment'] !== '' ? $entry['comment'] : null,
            'modified' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        return $response->withHeader('Location', '/?updated=1')->withStatus(302);
    }

    public function delete(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $this->entityManager->getConnection()->delete('time_entries', ['id' => $id]);

        return $response->withHeader('Location', '/?deleted=1')->withStatus(302);
    }

    private function renderHome(
        Request $request,
        Response $response,
        array $errors = [],
        ?array $old = null,
        int $status = 200,
    ): Response {
        $conn = $this->entityManager->getConnection();
        $clients = $conn->fetchAllAssociative('SELECT id, name FROM clients ORDER BY sort_order ASC, id ASC');
        $workCategories = $conn->fetchAllAssociative('SELECT id, name FROM work_categories ORDER BY sort_order ASC, id ASC');
        $timeEntries = $conn->fetchAllAssociative(
            'SELECT
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
             ORDER BY te.date DESC, te.start_time DESC, te.id DESC
             LIMIT 200'
        );
        $query = $request->getQueryParams();

        return Twig::fromRequest($request)->render($response->withStatus($status), 'home.html.twig', [
            'title' => 'Kizami',
            'clients' => $clients,
            'workCategories' => $workCategories,
            'timeEntries' => $timeEntries,
            'timeOptions' => $this->buildTimeOptions(),
            'errors' => $errors,
            'old' => $old ?? $this->defaultOld(),
            'saved' => isset($query['saved']),
            'updated' => isset($query['updated']),
            'deleted' => isset($query['deleted']),
        ]);
    }

    private function defaultOld(): array
    {
        return [
            'date' => date('Y-m-d'),
            'client_id' => '',
            'work_category_id' => '',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'comment' => '',
        ];
    }

    private function validateTimeEntry(array $entry): array
    {
        $errors = [];
        if ($entry['date'] === '') {
            $errors[] = '日付は必須です。';
        }
        if ($entry['client_id'] === '') {
            $errors[] = 'クライアントは必須です。';
        }
        if ($entry['work_category_id'] === '') {
            $errors[] = '作業分類は必須です。';
        }
        if (!$this->isQuarterTime($entry['start_time'])) {
            $errors[] = '開始時刻は15分刻みで指定してください。';
        }
        if (!$this->isQuarterTime($entry['end_time'])) {
            $errors[] = '終了時刻は15分刻みで指定してください。';
        }

        return $errors;
    }

    private function isQuarterTime(string $time): bool
    {
        return (bool) preg_match('/^\d{2}:(00|15|30|45)$/', $time);
    }

    private function calculateHours(string $start, string $end): float
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

    private function buildTimeOptions(): array
    {
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            foreach ([0, 15, 30, 45] as $minute) {
                $options[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return $options;
    }
}
