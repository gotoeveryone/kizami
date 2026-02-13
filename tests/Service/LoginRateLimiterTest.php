<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\LoginRateLimiter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoginRateLimiterTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/kizami_login_rate_limiter_test_' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->storagePath)) {
            unlink($this->storagePath);
        }
    }

    #[Test]
    public function shouldBlockAfterConfiguredFailures(): void
    {
        $limiter = new LoginRateLimiter($this->storagePath, 3, 300, 60);

        $limiter->registerFailure('login:127.0.0.1');
        $limiter->registerFailure('login:127.0.0.1');
        self::assertFalse($limiter->isBlocked('login:127.0.0.1'));

        $limiter->registerFailure('login:127.0.0.1');
        self::assertTrue($limiter->isBlocked('login:127.0.0.1'));
        self::assertGreaterThan(0, $limiter->getRetryAfterSeconds('login:127.0.0.1'));
    }

    #[Test]
    public function shouldClearAttempts(): void
    {
        $limiter = new LoginRateLimiter($this->storagePath, 1, 300, 60);

        $limiter->registerFailure('login:127.0.0.1');
        self::assertTrue($limiter->isBlocked('login:127.0.0.1'));

        $limiter->clear('login:127.0.0.1');
        self::assertFalse($limiter->isBlocked('login:127.0.0.1'));
        self::assertSame(0, $limiter->getRetryAfterSeconds('login:127.0.0.1'));
    }
}
