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
                'session_key' => 'kizami_google_sub',
                'google_client_id' => $_ENV['APP_GOOGLE_CLIENT_ID'] ?? '',
                'google_client_secret' => $_ENV['APP_GOOGLE_CLIENT_SECRET'] ?? '',
                'google_redirect_uri' => $_ENV['APP_GOOGLE_REDIRECT_URI'] ?? '',
                'google_allowed_sub' => $_ENV['APP_GOOGLE_ALLOWED_SUB'] ?? '',
            ],
        ],
    ]);
};
