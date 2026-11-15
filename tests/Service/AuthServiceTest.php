<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\AuthService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private const SESSION_KEY = 'kizami_google_sub';

    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION[self::SESSION_KEY], $_SESSION['kizami_logout_pending']);
    }

    #[Test]
    public function googleSubShouldSetSessionOnSuccess(): void
    {
        $service = $this->createService();

        $success = $service->loginWithGoogleSub('allowed-sub');

        self::assertTrue($success);
        self::assertTrue($service->isLoggedIn());
        $service->logout();
        self::assertFalse($service->isLoggedIn());
        self::assertTrue($service->isLogoutPending());
        self::assertTrue($service->loginWithGoogleSub('allowed-sub'));
        self::assertFalse($service->isLogoutPending());
    }

    #[Test]
    public function googleSubShouldRejectOtherAccount(): void
    {
        $service = $this->createService();

        $success = $service->loginWithGoogleSub('other-sub');

        self::assertFalse($success);
        self::assertFalse($service->isLoggedIn());
    }

    #[Test]
    public function googleSubShouldFailWhenAllowedSubIsNotConfigured(): void
    {
        $service = $this->createService(allowedSub: '');

        $success = $service->loginWithGoogleSub('allowed-sub');

        self::assertFalse($success);
        self::assertFalse($service->isLoggedIn());
    }

    #[Test]
    public function legacyPasswordSessionShouldNotAuthenticate(): void
    {
        $_SESSION['kizami_user'] = 'admin';

        try {
            self::assertFalse($this->createService()->isLoggedIn());
        } finally {
            unset($_SESSION['kizami_user']);
        }
    }

    private function createService(string $allowedSub = 'allowed-sub'): AuthService
    {
        return new AuthService([
            'auth' => [
                'session_key' => self::SESSION_KEY,
                'google_allowed_sub' => $allowedSub,
            ],
        ]);
    }
}
