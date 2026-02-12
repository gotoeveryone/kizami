<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final class WorkCategoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function listAll(): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, name, sort_order, created, modified FROM work_categories ORDER BY sort_order ASC, id ASC'
        );
    }

    public function listForSelect(): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, name FROM work_categories ORDER BY sort_order ASC, id ASC'
        );
    }

    public function findById(int $id): ?array
    {
        $category = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id, name, sort_order FROM work_categories WHERE id = :id',
            ['id' => $id]
        );

        return $category === false ? null : $category;
    }

    public function has(int $id): bool
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM work_categories WHERE id = :id',
            ['id' => $id]
        ) > 0;
    }

    public function normalizeInput(array $data): array
    {
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'sort_order' => trim((string) ($data['sort_order'] ?? '0')),
        ];
    }

    public function validate(array $category): array
    {
        $errors = [];
        if (($category['name'] ?? '') === '') {
            $errors[] = '作業分類名は必須です。';
        }
        if (!preg_match('/^-?\d+$/', (string) ($category['sort_order'] ?? ''))) {
            $errors[] = '表示順は整数で入力してください。';
        }

        return $errors;
    }

    public function create(array $category): void
    {
        $now = date('Y-m-d H:i:s');
        $this->entityManager->getConnection()->insert('work_categories', [
            'name' => $category['name'],
            'sort_order' => (int) $category['sort_order'],
            'created' => $now,
            'modified' => $now,
        ]);
    }

    public function update(int $id, array $category): void
    {
        $this->entityManager->getConnection()->update('work_categories', [
            'name' => $category['name'],
            'sort_order' => (int) $category['sort_order'],
            'modified' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        try {
            $this->entityManager->getConnection()->delete('work_categories', ['id' => $id]);
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
