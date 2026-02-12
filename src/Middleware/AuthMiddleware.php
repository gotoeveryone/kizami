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

        if (str_starts_with($path, '/api/')) {
            $apiKey = $this->extractApiKey($request);
            if (!$this->authService->validateApiKey($apiKey)) {
                $response = new SlimResponse(401);
                $response->getBody()->write(json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json');
            }

            return $handler->handle($request);
        }

        if ($path === '/login' || $path === '/login/') {
            return $handler->handle($request);
        }

        if (!$this->authService->isLoggedIn()) {
            return (new SlimResponse(302))->withHeader('Location', '/login');
        }

        return $handler->handle($request);
    }

    private function extractApiKey(Request $request): string
    {
        $header = trim($request->getHeaderLine('Authorization'));
        if ($header === '') {
            return '';
        }

        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return $header;
    }
}
