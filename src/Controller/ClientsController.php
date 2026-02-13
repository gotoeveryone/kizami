<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ClientService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class ClientsController
{
    public function __construct(
        private readonly ClientService $clientService,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->renderClients($request, $response);
    }

    public function store(Request $request, Response $response): Response
    {
        $old = $this->clientService->normalizeInput((array) $request->getParsedBody());
        $errors = $this->clientService->validate($old);
        if ($errors !== []) {
            return $this->renderClients($request, $response, $errors, $old, 422);
        }

        $this->clientService->create($old);

        return $response->withHeader('Location', '/clients?saved=1')->withStatus(302);
    }

    public function edit(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $client = $this->clientService->findById($id);
        if ($client === null) {
            $response->getBody()->write('client not found');

            return $response->withStatus(404);
        }

        return Twig::fromRequest($request)->render($response, 'client_edit.html.twig', [
            'title' => 'クライアント編集',
            'client' => [
                'id' => (string) $client['id'],
                'name' => (string) $client['name'],
                'sort_order' => (string) $client['sort_order'],
                'is_visible' => (bool) $client['is_visible'],
            ],
            'errors' => [],
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $existingClient = $this->clientService->findById($id);
        if ($existingClient === null) {
            $response->getBody()->write('client not found');

            return $response->withStatus(404);
        }

        $client = $this->clientService->normalizeInput((array) $request->getParsedBody());
        $client['id'] = (string) $id;
        $client['is_visible'] = $existingClient['is_visible'] ? '1' : '0';
        $errors = $this->clientService->validate($client);
        if ($errors !== []) {
            return Twig::fromRequest($request)->render($response->withStatus(422), 'client_edit.html.twig', [
                'title' => 'クライアント編集',
                'client' => $client,
                'errors' => $errors,
            ]);
        }

        $this->clientService->update($id, $client);

        return $response->withHeader('Location', '/clients?updated=1')->withStatus(302);
    }

    public function hide(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $this->clientService->hide($id);

        return $response->withHeader('Location', '/clients?hidden=1')->withStatus(302);
    }

    public function show(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $this->clientService->show($id);

        return $response->withHeader('Location', '/clients?shown=1&show_hidden=1')->withStatus(302);
    }

    private function renderClients(
        Request $request,
        Response $response,
        array $errors = [],
        ?array $old = null,
        int $status = 200,
    ): Response {
        $query = $request->getQueryParams();
        $showHidden = (string) ($query['show_hidden'] ?? '') === '1';

        return Twig::fromRequest($request)->render($response->withStatus($status), 'clients.html.twig', [
            'title' => 'クライアント管理',
            'clients' => $this->clientService->listAll($showHidden),
            'errors' => $errors,
            'old' => $old ?? ['name' => '', 'sort_order' => '0', 'is_visible' => '1'],
            'saved' => isset($query['saved']),
            'updated' => isset($query['updated']),
            'hidden' => isset($query['hidden']),
            'shown' => isset($query['shown']),
            'showHidden' => $showHidden,
        ]);
    }
}
