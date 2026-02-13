<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;

final class ClientService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function listAll(bool $includeHidden = false): array
    {
        $queryBuilder = $this->entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (!$includeHidden) {
            $queryBuilder
                ->where('c.isVisible = :is_visible')
                ->setParameter('is_visible', true);
        }

        $clients = $queryBuilder->getQuery()->getResult();

        return array_map(static fn (Client $client): array => [
            'id' => $client->getId(),
            'name' => $client->getName(),
            'sort_order' => $client->getSortOrder(),
            'is_visible' => $client->isVisible(),
        ], $clients);
    }

    public function listForSelect(): array
    {
        $clients = $this->entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->select('c')
            ->where('c.isVisible = :is_visible')
            ->setParameter('is_visible', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (Client $client): array => [
            'id' => $client->getId(),
            'name' => $client->getName(),
        ], $clients);
    }

    public function findById(int $id): ?array
    {
        $client = $this->entityManager->find(Client::class, $id);
        if (!$client instanceof Client) {
            return null;
        }

        return [
            'id' => $client->getId(),
            'name' => $client->getName(),
            'sort_order' => $client->getSortOrder(),
            'is_visible' => $client->isVisible(),
        ];
    }

    public function has(int $id): bool
    {
        return $this->entityManager->find(Client::class, $id) instanceof Client;
    }

    public function normalizeInput(array $data): array
    {
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'sort_order' => trim((string) ($data['sort_order'] ?? '0')),
            'is_visible' => ($data['is_visible'] ?? '') === '1' ? '1' : '0',
        ];
    }

    public function validate(array $client): array
    {
        $errors = [];
        if (($client['name'] ?? '') === '') {
            $errors[] = 'クライアント名は必須です。';
        }
        if (!preg_match('/^-?\d+$/', (string) ($client['sort_order'] ?? ''))) {
            $errors[] = '表示順は整数で入力してください。';
        }
        if (!in_array((string) ($client['is_visible'] ?? '0'), ['0', '1'], true)) {
            $errors[] = '表示設定が不正です。';
        }

        return $errors;
    }

    public function create(array $client): void
    {
        $entity = new Client(
            (string) $client['name'],
            (int) $client['sort_order']
        );
        $entity->setIsVisible(((string) $client['is_visible']) === '1');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(int $id, array $client): void
    {
        $entity = $this->entityManager->find(Client::class, $id);
        if (!$entity instanceof Client) {
            return;
        }

        $entity->setName((string) $client['name']);
        $entity->setSortOrder((int) $client['sort_order']);
        $entity->setIsVisible(((string) $client['is_visible']) === '1');
        $this->entityManager->flush();
    }

    public function isVisible(int $id): bool
    {
        $entity = $this->entityManager->find(Client::class, $id);

        return $entity instanceof Client && $entity->isVisible();
    }

    public function hide(int $id): void
    {
        $entity = $this->entityManager->find(Client::class, $id);
        if (!$entity instanceof Client) {
            return;
        }

        $entity->setIsVisible(false);
        $this->entityManager->flush();
    }

    public function show(int $id): void
    {
        $entity = $this->entityManager->find(Client::class, $id);
        if (!$entity instanceof Client) {
            return;
        }

        $entity->setIsVisible(true);
        $this->entityManager->flush();
    }
}
