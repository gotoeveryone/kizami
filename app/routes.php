<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Views\Twig;

return function (App $app): void {
    $container = $app->getContainer();
    if ($container === null) {
        throw new RuntimeException('Container is not available.');
    }
    $buildTimeOptions = static function (): array {
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            foreach ([0, 15, 30, 45] as $minute) {
                $options[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return $options;
    };

    $isQuarterTime = static function (string $time): bool {
        return (bool) preg_match('/^\d{2}:(00|15|30|45)$/', $time);
    };

    $calculateHours = static function (string $start, string $end): float {
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
    };

    $defaultOld = static function (): array {
        return [
            'date' => date('Y-m-d'),
            'client_id' => '',
            'work_category_id' => '',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'comment' => '',
        ];
    };

    $loadMasterAndEntries = static function (EntityManagerInterface $em): array {
        $conn = $em->getConnection();

        $clients = $conn->fetchAllAssociative(
            'SELECT id, name FROM clients ORDER BY sort_order ASC, id ASC'
        );
        $workCategories = $conn->fetchAllAssociative(
            'SELECT id, name FROM work_categories ORDER BY sort_order ASC, id ASC'
        );
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

        return [$clients, $workCategories, $timeEntries];
    };

    $renderHome = static function (
        Request $request,
        Response $response,
        EntityManagerInterface $em,
        array $errors = [],
        ?array $old = null,
        int $status = 200
    ) use ($loadMasterAndEntries, $buildTimeOptions, $defaultOld): Response {
        [$clients, $workCategories, $timeEntries] = $loadMasterAndEntries($em);
        $query = $request->getQueryParams();

        return Twig::fromRequest($request)->render($response->withStatus($status), 'home.html.twig', [
            'title' => 'Kizami',
            'clients' => $clients,
            'workCategories' => $workCategories,
            'timeEntries' => $timeEntries,
            'timeOptions' => $buildTimeOptions(),
            'errors' => $errors,
            'old' => $old ?? $defaultOld(),
            'saved' => isset($query['saved']),
            'updated' => isset($query['updated']),
            'deleted' => isset($query['deleted']),
        ]);
    };

    $renderClients = static function (
        Request $request,
        Response $response,
        EntityManagerInterface $em,
        array $errors = [],
        ?array $old = null,
        int $status = 200
    ): Response {
        $clients = $em->getConnection()->fetchAllAssociative(
            'SELECT id, name, sort_order, created, modified FROM clients ORDER BY sort_order ASC, id ASC'
        );
        $query = $request->getQueryParams();

        return Twig::fromRequest($request)->render($response->withStatus($status), 'clients.html.twig', [
            'title' => 'クライアント管理',
            'clients' => $clients,
            'errors' => $errors,
            'old' => $old ?? ['name' => '', 'sort_order' => '0'],
            'saved' => isset($query['saved']),
            'updated' => isset($query['updated']),
            'deleted' => isset($query['deleted']),
        ]);
    };

    $app->get('/', function (Request $request, Response $response) use ($container, $renderHome) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        return $renderHome($request, $response, $em);
    });

    $app->post('/time-entries', function (Request $request, Response $response) use ($calculateHours, $container, $renderHome, $isQuarterTime) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $conn = $em->getConnection();

        $data = (array) $request->getParsedBody();
        $old = [
            'date' => trim((string) ($data['date'] ?? '')),
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'work_category_id' => trim((string) ($data['work_category_id'] ?? '')),
            'start_time' => trim((string) ($data['start_time'] ?? '')),
            'end_time' => trim((string) ($data['end_time'] ?? '')),
            'comment' => trim((string) ($data['comment'] ?? '')),
        ];

        $errors = [];
        if ($old['date'] === '') {
            $errors[] = '日付は必須です。';
        }
        if ($old['client_id'] === '') {
            $errors[] = 'クライアントは必須です。';
        }
        if ($old['work_category_id'] === '') {
            $errors[] = '作業分類は必須です。';
        }
        if (!$isQuarterTime($old['start_time'])) {
            $errors[] = '開始時刻は15分刻みで指定してください。';
        }
        if (!$isQuarterTime($old['end_time'])) {
            $errors[] = '終了時刻は15分刻みで指定してください。';
        }

        $hours = null;
        if ($errors === []) {
            try {
                $hours = $calculateHours($old['start_time'], $old['end_time']);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return $renderHome($request, $response, $em, $errors, $old, 422);
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

        return $response
            ->withHeader('Location', '/?saved=1')
            ->withStatus(302);
    });

    $app->get('/time-entries/{id}/edit', function (Request $request, Response $response) use ($container, $buildTimeOptions) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
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
            'timeOptions' => $buildTimeOptions(),
            'errors' => [],
        ]);
    });

    $app->post('/time-entries/{id}', function (Request $request, Response $response) use ($container, $calculateHours, $buildTimeOptions, $isQuarterTime) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
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
        if (!$isQuarterTime($entry['start_time'])) {
            $errors[] = '開始時刻は15分刻みで指定してください。';
        }
        if (!$isQuarterTime($entry['end_time'])) {
            $errors[] = '終了時刻は15分刻みで指定してください。';
        }

        $hours = null;
        if ($errors === []) {
            try {
                $hours = $calculateHours($entry['start_time'], $entry['end_time']);
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
                'timeOptions' => $buildTimeOptions(),
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

        return $response
            ->withHeader('Location', '/?updated=1')
            ->withStatus(302);
    });

    $app->post('/time-entries/{id}/delete', function (Request $request, Response $response) use ($container) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $id = (int) ($request->getAttribute('id') ?? 0);

        $em->getConnection()->delete('time_entries', ['id' => $id]);

        return $response
            ->withHeader('Location', '/?deleted=1')
            ->withStatus(302);
    });

    $app->get('/clients', function (Request $request, Response $response) use ($container, $renderClients) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        return $renderClients($request, $response, $em);
    });

    $app->post('/clients', function (Request $request, Response $response) use ($container, $renderClients) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $data = (array) $request->getParsedBody();

        $old = [
            'name' => trim((string) ($data['name'] ?? '')),
            'sort_order' => trim((string) ($data['sort_order'] ?? '0')),
        ];
        $errors = [];
        if ($old['name'] === '') {
            $errors[] = 'クライアント名は必須です。';
        }
        if (!preg_match('/^-?\d+$/', $old['sort_order'])) {
            $errors[] = '表示順は整数で入力してください。';
        }

        if ($errors !== []) {
            return $renderClients($request, $response, $em, $errors, $old, 422);
        }

        $now = date('Y-m-d H:i:s');
        $conn->insert('clients', [
            'name' => $old['name'],
            'sort_order' => (int) $old['sort_order'],
            'created' => $now,
            'modified' => $now,
        ]);

        return $response
            ->withHeader('Location', '/clients?saved=1')
            ->withStatus(302);
    });

    $app->get('/clients/{id}/edit', function (Request $request, Response $response) use ($container) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $id = (int) ($request->getAttribute('id') ?? 0);

        $client = $conn->fetchAssociative(
            'SELECT id, name, sort_order FROM clients WHERE id = :id',
            ['id' => $id]
        );
        if ($client === false) {
            $response->getBody()->write('client not found');

            return $response->withStatus(404);
        }

        return Twig::fromRequest($request)->render($response, 'client_edit.html.twig', [
            'title' => 'クライアント編集',
            'client' => [
                'id' => (string) $client['id'],
                'name' => (string) $client['name'],
                'sort_order' => (string) $client['sort_order'],
            ],
            'errors' => [],
        ]);
    });

    $app->post('/clients/{id}', function (Request $request, Response $response) use ($container) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $id = (int) ($request->getAttribute('id') ?? 0);

        $exists = (int) $conn->fetchOne('SELECT COUNT(*) FROM clients WHERE id = :id', ['id' => $id]) > 0;
        if (!$exists) {
            $response->getBody()->write('client not found');

            return $response->withStatus(404);
        }

        $data = (array) $request->getParsedBody();
        $client = [
            'id' => (string) $id,
            'name' => trim((string) ($data['name'] ?? '')),
            'sort_order' => trim((string) ($data['sort_order'] ?? '0')),
        ];
        $errors = [];
        if ($client['name'] === '') {
            $errors[] = 'クライアント名は必須です。';
        }
        if (!preg_match('/^-?\d+$/', $client['sort_order'])) {
            $errors[] = '表示順は整数で入力してください。';
        }

        if ($errors !== []) {
            return Twig::fromRequest($request)->render($response->withStatus(422), 'client_edit.html.twig', [
                'title' => 'クライアント編集',
                'client' => $client,
                'errors' => $errors,
            ]);
        }

        $conn->update('clients', [
            'name' => $client['name'],
            'sort_order' => (int) $client['sort_order'],
            'modified' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        return $response
            ->withHeader('Location', '/clients?updated=1')
            ->withStatus(302);
    });

    $app->post('/clients/{id}/delete', function (Request $request, Response $response) use ($container, $renderClients) {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $id = (int) ($request->getAttribute('id') ?? 0);

        try {
            $conn->delete('clients', ['id' => $id]);
        } catch (Throwable $e) {
            return $renderClients(
                $request,
                $response,
                $em,
                ['このクライアントは稼働時間に紐づいているため削除できません。'],
                null,
                409
            );
        }

        return $response
            ->withHeader('Location', '/clients?deleted=1')
            ->withStatus(302);
    });
};
