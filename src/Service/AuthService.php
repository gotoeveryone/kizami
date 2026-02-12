<?php

declare(strict_types=1);

namespace App\Service;

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
        if (
            hash_equals((string) $auth['admin_username'], $username)
            && hash_equals((string) $auth['admin_password'], $password)
        ) {
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
        $count = (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM api_keys WHERE key_hash = :hash AND is_active = 1',
            ['hash' => $hash]
        );

        return $count > 0;
    }
}
