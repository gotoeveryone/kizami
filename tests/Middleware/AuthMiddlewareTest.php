<?php

declare(strict_types=1);

namespace Tests\Middleware;

use App\Middleware\AuthMiddleware;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuthMiddlewareTest extends TestCase
{
    #[Test]
    public function protectedPageStartsGoogleLoginWhenNotLoggedOut(): void
    {
        $_SESSION = [];
        $authService = new AuthService($this->createMock(EntityManagerInterface::class), [
            'auth' => ['session_key' => 'kizami_google_sub'],
        ]);
        $middleware = new AuthMiddleware($authService);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $handler);

        self::assertSame('/auth/google', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function protectedPageRedirectsToLoginPageAfterLogout(): void
    {
        $_SESSION = ['kizami_logout_pending' => true];
        $authService = new AuthService($this->createMock(EntityManagerInterface::class), [
            'auth' => ['session_key' => 'kizami_google_sub'],
        ]);
        $middleware = new AuthMiddleware($authService);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $handler);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        $_SESSION = [];
    }
}
