<?php

declare(strict_types=1);

use DI\ContainerBuilder;

$doctrineSettings = require __DIR__ . '/../config/doctrine.php';

return function (ContainerBuilder $containerBuilder) use ($doctrineSettings): void {
    $containerBuilder->addDefinitions([
        'settings' => [
            'slim' => [
                'displayErrorDetails' => ($_ENV['APP_ENV'] ?? 'production') !== 'production',
                'logErrors' => true,
                'logErrorDetails' => true,
            ],
            'doctrine' => $doctrineSettings,
            'twig' => [
                'template_path' => __DIR__ . '/../templates',
                'cache_path' => ($_ENV['APP_ENV'] ?? 'production') === 'production'
                    ? __DIR__ . '/../var/cache/twig'
                    : false,
            ],
            'logger' => [
                'name' => 'kizami',
                'path' => __DIR__ . '/../var/log/app.log',
            ],
            'auth' => [
                'session_key' => 'kizami_user',
                'admin_username' => $_ENV['APP_ADMIN_USERNAME'] ?? null,
                'admin_password_hash' => $_ENV['APP_ADMIN_PASSWORD_HASH'] ?? null,
                'rate_limit' => [
                    'max_attempts' => (int) ($_ENV['APP_AUTH_MAX_ATTEMPTS'] ?? 5),
                    'window_seconds' => (int) ($_ENV['APP_AUTH_WINDOW_SECONDS'] ?? 300),
                    'lock_seconds' => (int) ($_ENV['APP_AUTH_LOCK_SECONDS'] ?? 600),
                ],
            ],
        ],
    ]);
};
