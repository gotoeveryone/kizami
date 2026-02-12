<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    #[Test]
    public function attemptLoginShouldSetSessionOnSuccess(): void
    {
        $service = new AuthService(
            $this->createMock(EntityManagerInterface::class),
            [
                'auth' => [
                    'session_key' => 'kizami_test_user',
                    'admin_username' => 'admin',
                    'admin_password' => 'password',
                ],
            ]
        );

        $success = $service->attemptLogin('admin', 'password');

        self::assertTrue($success);
        self::assertTrue($service->isLoggedIn());
        $service->logout();
    }

    #[Test]
    public function attemptLoginShouldFailWithWrongPassword(): void
    {
        $service = new AuthService(
            $this->createMock(EntityManagerInterface::class),
            [
                'auth' => [
                    'session_key' => 'kizami_test_user',
                    'admin_username' => 'admin',
                    'admin_password' => 'password',
                ],
            ]
        );

        $success = $service->attemptLogin('admin', 'invalid');

        self::assertFalse($success);
        self::assertFalse($service->isLoggedIn());
    }
}
