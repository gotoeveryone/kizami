<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Throwable;

final class ClientsController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->renderClients($request, $response);
    }

    public function store(Request $request, Response $response): Response
    {
        $conn = $this->entityManager->getConnection();
        $data = (array) $request->getParsedBody();

        $old = [
            'name' => trim((string) ($data['name'] ?? '')),
            'sort_order' => trim((string) ($data['sort_order'] ?? '0')),
        ];

        $errors = $this->validateClient($old);
        if ($errors !== []) {
            return $this->renderClients($request, $response, $errors, $old, 422);
        }

        $now = date('Y-m-d H:i:s');
        $conn->insert('clients', [
            'name' => $old['name'],
            'sort_order' => (int) $old['sort_order'],
            'created' => $now,
            'modified' => $now,
        ]);

        return $response->withHeader('Location', '/clients?saved=1')->withStatus(302);
    }

    public function edit(Request $request, Response $response): Response
    {
        $conn = $this->entityManager->getConnection();
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
    }

    public function update(Request $request, Response $response): Response
    {
        $conn = $this->entityManager->getConnection();
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

        $errors = $this->validateClient($client);
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

        return $response->withHeader('Location', '/clients?updated=1')->withStatus(302);
    }

    public function delete(Request $request, Response $response): Response
    {
        $conn = $this->entityManager->getConnection();
        $id = (int) ($request->getAttribute('id') ?? 0);

        try {
            $conn->delete('clients', ['id' => $id]);
        } catch (Throwable) {
            return $this->renderClients(
                $request,
                $response,
                ['このクライアントは稼働時間に紐づいているため削除できません。'],
                null,
                409
            );
        }

        return $response->withHeader('Location', '/clients?deleted=1')->withStatus(302);
    }

    private function renderClients(
        Request $request,
        Response $response,
        array $errors = [],
        ?array $old = null,
        int $status = 200,
    ): Response {
        $clients = $this->entityManager->getConnection()->fetchAllAssociative(
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
    }

    private function validateClient(array $client): array
    {
        $errors = [];
        if ($client['name'] === '') {
            $errors[] = 'クライアント名は必須です。';
        }
        if (!preg_match('/^-?\d+$/', $client['sort_order'])) {
            $errors[] = '表示順は整数で入力してください。';
        }

        return $errors;
    }
}
