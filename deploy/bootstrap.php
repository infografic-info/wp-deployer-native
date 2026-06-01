<?php

namespace Deployer;

if (file_exists(DEPLOY_ROOT . '/vendor/autoload.php')) {
    require_once DEPLOY_ROOT . '/vendor/autoload.php';
} else {
    $paths = [
        $_SERVER['HOME'] . '/.composer/vendor/autoload.php',
        '/home/runner/.composer/vendor/autoload.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

if (file_exists(DEPLOY_ROOT . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(DEPLOY_ROOT);
    $dotenv->load();
}

\Env\Env::$options = 31;

require 'recipe/common.php';
