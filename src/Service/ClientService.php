<?php

declare(strict_types=1);

namespace App\Service;

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
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, name, sort_order, created, modified FROM clients ORDER BY sort_order ASC, id ASC'
        );
    }

    public function listForSelect(): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, name FROM clients ORDER BY sort_order ASC, id ASC'
        );
    }

    public function findById(int $id): ?array
    {
        $client = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id, name, sort_order FROM clients WHERE id = :id',
            ['id' => $id]
        );

        return $client === false ? null : $client;
    }

    public function has(int $id): bool
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM clients WHERE id = :id',
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
        $now = date('Y-m-d H:i:s');
        $this->entityManager->getConnection()->insert('clients', [
            'name' => $client['name'],
            'sort_order' => (int) $client['sort_order'],
            'created' => $now,
            'modified' => $now,
        ]);
    }

    public function update(int $id, array $client): void
    {
        $this->entityManager->getConnection()->update('clients', [
            'name' => $client['name'],
            'sort_order' => (int) $client['sort_order'],
            'modified' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        try {
            $this->entityManager->getConnection()->delete('clients', ['id' => $id]);
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
