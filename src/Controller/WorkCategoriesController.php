<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\WorkCategoryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class WorkCategoriesController
{
    public function __construct(
        private readonly WorkCategoryService $workCategoryService,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->renderCategories($request, $response);
    }

    public function store(Request $request, Response $response): Response
    {
        $old = $this->workCategoryService->normalizeInput((array) $request->getParsedBody());
        $errors = $this->workCategoryService->validate($old);
        if ($errors !== []) {
            return $this->renderCategories($request, $response, $errors, $old, 422);
        }

        $this->workCategoryService->create($old);

        return $response->withHeader('Location', '/work-categories?saved=1')->withStatus(302);
    }

    public function edit(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $category = $this->workCategoryService->findById($id);
        if ($category === null) {
            $response->getBody()->write('work category not found');

            return $response->withStatus(404);
        }

        return Twig::fromRequest($request)->render($response, 'work_category_edit.html.twig', [
            'title' => '作業分類編集',
            'workCategory' => [
                'id' => (string) $category['id'],
                'name' => (string) $category['name'],
                'sort_order' => (string) $category['sort_order'],
            ],
            'errors' => [],
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        if (!$this->workCategoryService->has($id)) {
            $response->getBody()->write('work category not found');

            return $response->withStatus(404);
        }

        $category = $this->workCategoryService->normalizeInput((array) $request->getParsedBody());
        $category['id'] = (string) $id;
        $errors = $this->workCategoryService->validate($category);
        if ($errors !== []) {
            return Twig::fromRequest($request)->render($response->withStatus(422), 'work_category_edit.html.twig', [
                'title' => '作業分類編集',
                'workCategory' => $category,
                'errors' => $errors,
            ]);
        }

        $this->workCategoryService->update($id, $category);

        return $response->withHeader('Location', '/work-categories?updated=1')->withStatus(302);
    }

    public function delete(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $deleted = $this->workCategoryService->delete($id);
        if (!$deleted) {
            return $this->renderCategories(
                $request,
                $response,
                ['この作業分類は稼働時間に紐づいているため削除できません。'],
                null,
                409
            );
        }

        return $response->withHeader('Location', '/work-categories?deleted=1')->withStatus(302);
    }

    private function renderCategories(
        Request $request,
        Response $response,
        array $errors = [],
        ?array $old = null,
        int $status = 200,
    ): Response {
        $workCategories = $this->workCategoryService->listAll();
        $query = $request->getQueryParams();

        return Twig::fromRequest($request)->render($response->withStatus($status), 'work_categories.html.twig', [
            'title' => '作業分類管理',
            'workCategories' => $workCategories,
            'errors' => $errors,
            'old' => $old ?? ['name' => '', 'sort_order' => '0'],
            'saved' => isset($query['saved']),
            'updated' => isset($query['updated']),
            'deleted' => isset($query['deleted']),
        ]);
    }
}
