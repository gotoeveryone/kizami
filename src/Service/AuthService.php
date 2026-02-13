<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;

final class AuthService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly array $settings,
    ) {
    }

    public function attemptLogin(string $username, string $password): bool
    {
        $auth = $this->settings['auth'];
        $configuredUsername = trim((string) ($auth['admin_username'] ?? ''));
        $configuredPasswordHash = trim((string) ($auth['admin_password_hash'] ?? ''));

        if ($configuredUsername === '' || $configuredPasswordHash === '') {
            return false;
        }

        if (hash_equals($configuredUsername, $username) && password_verify($password, $configuredPasswordHash)) {
            $_SESSION[(string) $auth['session_key']] = $username;

            return true;
        }

        return false;
    }

    public function logout(): void
    {
        $sessionKey = (string) $this->settings['auth']['session_key'];
        unset($_SESSION[$sessionKey]);
    }

    public function isLoggedIn(): bool
    {
        $sessionKey = (string) $this->settings['auth']['session_key'];

        return isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] !== '';
    }

    public function validateApiKey(string $rawKey): bool
    {
        if ($rawKey === '') {
            return false;
        }

        $hash = hash('sha256', $rawKey);
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(ak.id)')
            ->from(ApiKey::class, 'ak')
            ->where('ak.keyHash = :hash')
            ->andWhere('ak.isActive = :is_active')
            ->setParameter('hash', $hash)
            ->setParameter('is_active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
