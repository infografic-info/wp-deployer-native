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

$_deployStage = null;
foreach (['production', 'staging'] as $_s) {
    if (getenv('DEPLOY_ENV') === $_s || in_array($_s, $_SERVER['argv'] ?? [], true)) {
        $_deployStage = $_s;
        break;
    }
}

if ($_deployStage && file_exists(DEPLOY_ROOT . "/.env.{$_deployStage}")) {
    \Dotenv\Dotenv::createImmutable(DEPLOY_ROOT, ".env.{$_deployStage}")->load();
}

if (file_exists(DEPLOY_ROOT . '/.env')) {
    \Dotenv\Dotenv::createImmutable(DEPLOY_ROOT)->load();
}

\Env\Env::$options = 31;

require 'recipe/common.php';
