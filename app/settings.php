<?php

declare(strict_types=1);

use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        'settings' => [
            'slim' => [
                'displayErrorDetails' => ($_ENV['APP_ENV'] ?? 'production') !== 'production',
                'logErrors' => true,
                'logErrorDetails' => true,
            ],
            'doctrine' => [
                'dev_mode' => ($_ENV['APP_ENV'] ?? 'production') !== 'production',
                'cache_dir' => __DIR__ . '/../var/doctrine',
                'metadata_dirs' => [__DIR__ . '/../src/Domain/Entity'],
                'connection' => [
                    'driver' => 'pdo_mysql',
                    'host' => $_ENV['DB_HOST'] ?? 'localhost',
                    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                    'dbname' => $_ENV['DB_NAME'] ?? 'kizami',
                    'user' => $_ENV['DB_USER'] ?? 'root',
                    'password' => $_ENV['DB_PASSWORD'] ?? '',
                    'charset' => 'utf8mb4',
                ],
            ],
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
