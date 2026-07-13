<?php

declare(strict_types=1);

return [
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
];
