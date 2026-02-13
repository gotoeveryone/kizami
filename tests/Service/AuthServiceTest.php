<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
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
        $service = $this->createService(adminUsername: '', adminPasswordHash: '');

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
        $service = $this->createService(apiKeyCount: 1);

        self::assertTrue($service->validateApiKey('dev-key'));
    }

    #[Test]
    public function validateApiKeyShouldReturnFalseWhenNoActiveKeyExists(): void
    {
        $service = $this->createService(apiKeyCount: 0);

        self::assertFalse($service->validateApiKey('dev-key'));
    }

    private function createService(
        int $apiKeyCount = 0,
        string $adminUsername = 'admin',
        string $adminPasswordHash = '',
    ): AuthService {
        if ($adminPasswordHash === '') {
            $adminPasswordHash = password_hash('password', PASSWORD_DEFAULT);
        }

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn($apiKeyCount);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

        return new AuthService(
            $entityManager,
            [
                'auth' => [
                    'session_key' => self::SESSION_KEY,
                    'admin_username' => $adminUsername,
                    'admin_password_hash' => $adminPasswordHash,
                ],
            ]
        );
    }
}
