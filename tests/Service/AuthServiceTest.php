<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\AuthService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private const SESSION_KEY = 'kizami_test_user';

    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION[self::SESSION_KEY]);
    }

    #[Test]
    public function attemptLoginShouldSetSessionOnSuccess(): void
    {
        $service = $this->createService();

        $success = $service->attemptLogin('admin', 'password');

        self::assertTrue($success);
        self::assertTrue($service->isLoggedIn());
        $service->logout();
    }

    #[Test]
    public function attemptLoginShouldFailWithWrongPassword(): void
    {
        $service = $this->createService();

        $success = $service->attemptLogin('admin', 'invalid');

        self::assertFalse($success);
        self::assertFalse($service->isLoggedIn());
    }

    #[Test]
    public function attemptLoginShouldFailWhenCredentialsAreNotConfigured(): void
    {
        $service = $this->createService(adminUsername: '', adminPassword: '');

        $success = $service->attemptLogin('admin', 'password');

        self::assertFalse($success);
        self::assertFalse($service->isLoggedIn());
    }

    #[Test]
    public function validateApiKeyShouldReturnFalseWhenInputIsEmpty(): void
    {
        $service = $this->createService();

        self::assertFalse($service->validateApiKey(''));
    }

    #[Test]
    public function validateApiKeyShouldReturnTrueWhenActiveKeyExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                self::stringContains('SELECT COUNT(*) FROM api_keys'),
                ['hash' => hash('sha256', 'dev-key')]
            )
            ->willReturn(1);

        $service = $this->createService(connection: $connection);

        self::assertTrue($service->validateApiKey('dev-key'));
    }

    #[Test]
    public function validateApiKeyShouldReturnFalseWhenNoActiveKeyExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->willReturn(0);

        $service = $this->createService(connection: $connection);

        self::assertFalse($service->validateApiKey('dev-key'));
    }

    private function createService(
        ?Connection $connection = null,
        string $adminUsername = 'admin',
        string $adminPassword = 'password',
    ): AuthService {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')
            ->willReturn($connection ?? $this->createMock(Connection::class));

        return new AuthService(
            $entityManager,
            [
                'auth' => [
                    'session_key' => self::SESSION_KEY,
                    'admin_username' => $adminUsername,
                    'admin_password' => $adminPassword,
                ],
            ]
        );
    }
}
