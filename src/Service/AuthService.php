<?php

declare(strict_types=1);

namespace App\Service;

final class AuthService
{
    public function __construct(
        private readonly array $settings,
    ) {
    }

    public function loginWithGoogleSub(string $sub): bool
    {
        $auth = $this->settings['auth'];
        $allowedSub = trim((string) ($auth['google_allowed_sub'] ?? ''));

        if ($sub === '' || $allowedSub === '' || !hash_equals($allowedSub, $sub)) {
            return false;
        }

        $_SESSION[(string) $auth['session_key']] = $sub;
        unset($_SESSION['kizami_logout_pending']);

        return true;
    }

    public function logout(): void
    {
        $sessionKey = (string) $this->settings['auth']['session_key'];
        unset($_SESSION[$sessionKey]);
        $_SESSION['kizami_logout_pending'] = true;
    }

    public function isLogoutPending(): bool
    {
        return ($_SESSION['kizami_logout_pending'] ?? false) === true;
    }

    public function isLoggedIn(): bool
    {
        $sessionKey = (string) $this->settings['auth']['session_key'];

        return isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] !== '';
    }
}
