<?php

declare(strict_types=1);

use App\Service\AuthService;
use DI\ContainerBuilder;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

return function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([

        EntityManagerInterface::class => function (ContainerInterface $c): EntityManagerInterface {
            $settings = $c->get('settings')['doctrine'];

            $cache = $settings['dev_mode']
                ? new ArrayAdapter()
                : new FilesystemAdapter(directory: $settings['cache_dir']);

            $config = ORMSetup::createAttributeMetadataConfiguration(
                paths: $settings['metadata_dirs'],
                isDevMode: $settings['dev_mode'],
                cache: $cache,
            );
            $config->enableNativeLazyObjects(true);

            $connection = DriverManager::getConnection($settings['connection']);

            return new EntityManager($connection, $config);
        },

        'view' => function (ContainerInterface $c): Twig {
            $settings = $c->get('settings')['twig'];

            return Twig::create(
                $settings['template_path'],
                ['cache' => $settings['cache_path']],
            );
        },

        Twig::class => DI\get('view'),

        AuthService::class => function (ContainerInterface $c): AuthService {
            return new AuthService(
                $c->get(EntityManagerInterface::class),
                $c->get('settings'),
            );
        },

        LoggerInterface::class => function (ContainerInterface $c): LoggerInterface {
            $settings = $c->get('settings')['logger'];
            $logger = new Logger($settings['name']);
            $logger->pushProcessor(new UidProcessor());
            $logger->pushHandler(new StreamHandler($settings['path']));

            return $logger;
        },

    ]);
};
