<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final class ClientService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function listAll(): array
    {
        $clients = $this->entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (Client $client): array => [
            'id' => $client->getId(),
            'name' => $client->getName(),
            'sort_order' => $client->getSortOrder(),
        ], $clients);
    }

    public function listForSelect(): array
    {
        $clients = $this->entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->select('c')
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

        return $errors;
    }

    public function create(array $client): void
    {
        $entity = new Client(
            (string) $client['name'],
            (int) $client['sort_order']
        );
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
        $this->entityManager->flush();
    }

    public function delete(int $id): bool
    {
        $entity = $this->entityManager->find(Client::class, $id);
        if (!$entity instanceof Client) {
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
