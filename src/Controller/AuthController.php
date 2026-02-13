<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Service\LoginRateLimiter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly LoginRateLimiter $loginRateLimiter,
    ) {
    }

    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        return Twig::fromRequest($request)->render($response, 'login.html.twig', [
            'title' => 'ログイン',
            'error' => null,
            'hideTopNav' => true,
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $rateLimitKey = $this->buildRateLimitKey($request);

        if ($this->loginRateLimiter->isBlocked($rateLimitKey)) {
            $retryAfter = $this->loginRateLimiter->getRetryAfterSeconds($rateLimitKey);

            return Twig::fromRequest($request)->render($response->withStatus(429), 'login.html.twig', [
                'title' => 'ログイン',
                'error' => sprintf('ログイン試行回数が上限に達しました。%d秒後に再試行してください。', $retryAfter),
                'hideTopNav' => true,
            ]);
        }

        if ($this->authService->attemptLogin($username, $password)) {
            $this->loginRateLimiter->clear($rateLimitKey);
            session_regenerate_id(true);

            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $this->loginRateLimiter->registerFailure($rateLimitKey);

        return Twig::fromRequest($request)->render($response->withStatus(401), 'login.html.twig', [
            'title' => 'ログイン',
            'error' => 'ログイン情報が正しくありません。',
            'hideTopNav' => true,
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->authService->logout();
        session_regenerate_id(true);

        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    private function buildRateLimitKey(Request $request): string
    {
        $ip = trim((string) (($request->getServerParams()['REMOTE_ADDR'] ?? '') ?: 'unknown'));

        return 'login:' . $ip;
    }
}
