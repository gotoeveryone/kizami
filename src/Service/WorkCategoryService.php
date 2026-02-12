<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\WorkCategory;
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
        $categories = $this->entityManager->getRepository(WorkCategory::class)
            ->createQueryBuilder('wc')
            ->orderBy('wc.sortOrder', 'ASC')
            ->addOrderBy('wc.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (WorkCategory $category): array => [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'sort_order' => $category->getSortOrder(),
        ], $categories);
    }

    public function listForSelect(): array
    {
        $categories = $this->entityManager->getRepository(WorkCategory::class)
            ->createQueryBuilder('wc')
            ->orderBy('wc.sortOrder', 'ASC')
            ->addOrderBy('wc.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (WorkCategory $category): array => [
            'id' => $category->getId(),
            'name' => $category->getName(),
        ], $categories);
    }

    public function findById(int $id): ?array
    {
        $category = $this->entityManager->find(WorkCategory::class, $id);
        if (!$category instanceof WorkCategory) {
            return null;
        }

        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'sort_order' => $category->getSortOrder(),
        ];
    }

    public function has(int $id): bool
    {
        return $this->entityManager->find(WorkCategory::class, $id) instanceof WorkCategory;
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
        $entity = new WorkCategory(
            (string) $category['name'],
            (int) $category['sort_order']
        );
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(int $id, array $category): void
    {
        $entity = $this->entityManager->find(WorkCategory::class, $id);
        if (!$entity instanceof WorkCategory) {
            return;
        }

        $entity->setName((string) $category['name']);
        $entity->setSortOrder((int) $category['sort_order']);
        $this->entityManager->flush();
    }

    public function delete(int $id): bool
    {
        $entity = $this->entityManager->find(WorkCategory::class, $id);
        if (!$entity instanceof WorkCategory) {
            return true;
        }

        try {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
