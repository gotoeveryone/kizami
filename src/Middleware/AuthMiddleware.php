<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $path = $request->getUri()->getPath();

        if (in_array($path, ['/login', '/auth/google', '/auth/google/callback'], true)) {
            return $handler->handle($request);
        }

        if (!$this->authService->isLoggedIn()) {
            $location = $this->authService->isLogoutPending() ? '/login' : '/auth/google';

            return (new SlimResponse(302))->withHeader('Location', $location);
        }

        return $handler->handle($request);
    }
}
