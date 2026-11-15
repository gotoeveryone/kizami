<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use Google\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly array $settings,
    ) {
    }

    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $message = $_SESSION['kizami_login_message'] ?? null;
        $notice = $_SESSION['kizami_login_notice'] ?? null;
        unset($_SESSION['kizami_login_message'], $_SESSION['kizami_login_notice']);

        return Twig::fromRequest($request)->render($response, 'login.html.twig', [
            'error' => is_string($message) ? $message : null,
            'notice' => is_string($notice) ? $notice : null,
            'hideTopNav' => true,
        ]);
    }

    public function startGoogleLogin(Request $request, Response $response): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }
        $client = $this->createGoogleClient();
        if ($client === null) {
            return $this->loginFailed($response, 'Google ログインの設定が不足しています。');
        }
        $state = bin2hex(random_bytes(32));
        $_SESSION['kizami_google_oauth_state'] = $state;
        $_SESSION['kizami_google_oauth_started_at'] = time();
        $client->setState($state);

        return $response->withHeader('Location', $client->createAuthUrl())->withStatus(302);
    }

    public function googleCallback(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $expectedState = $_SESSION['kizami_google_oauth_state'] ?? null;
        $startedAt = $_SESSION['kizami_google_oauth_started_at'] ?? null;
        unset($_SESSION['kizami_google_oauth_state'], $_SESSION['kizami_google_oauth_started_at']);

        if (!is_string($expectedState) || !is_string($query['state'] ?? null)
            || !hash_equals($expectedState, $query['state'])
            || !is_int($startedAt) || time() - $startedAt > 600
            || isset($query['error']) || !is_string($query['code'] ?? null) || $query['code'] === '') {
            return $this->loginFailed($response, 'Google ログインに失敗しました。');
        }

        $client = $this->createGoogleClient();
        if ($client === null) {
            return $this->loginFailed($response, 'Google ログインの設定が不足しています。');
        }

        try {
            $token = $client->fetchAccessTokenWithAuthCode($query['code']);
            if (isset($token['error']) || !is_string($token['id_token'] ?? null)) {
                return $this->loginFailed($response, 'Google ログインに失敗しました。');
            }
            $claims = $client->verifyIdToken($token['id_token']);
        } catch (\Throwable) {
            return $this->loginFailed($response, 'Google ログインに失敗しました。');
        }

        if (!is_array($claims) || ($claims['aud'] ?? null) !== $this->settings['auth']['google_client_id']
            || !is_int($claims['exp'] ?? null) || $claims['exp'] <= time()
            || !is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            return $this->loginFailed($response, 'Google ログインに失敗しました。');
        }
        if (!$this->authService->loginWithGoogleSub($claims['sub'])) {
            return $this->loginFailed(
                $response,
                'この Google アカウントは許可されていません。Google アカウント ID (sub): ' . $claims['sub']
            );
        }
        session_regenerate_id(true);

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->authService->logout();
        session_regenerate_id(true);
        $_SESSION['kizami_login_notice'] = 'ログアウトしました。';

        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    private function createGoogleClient(): ?Client
    {
        $auth = $this->settings['auth'];
        $clientId = trim((string) ($auth['google_client_id'] ?? ''));
        $clientSecret = trim((string) ($auth['google_client_secret'] ?? ''));
        $redirectUri = trim((string) ($auth['google_redirect_uri'] ?? ''));
        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            return null;
        }
        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setScopes(['openid']);

        return $client;
    }

    private function loginFailed(Response $response, string $message): Response
    {
        $_SESSION['kizami_login_message'] = $message;

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
