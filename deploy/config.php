<?php

namespace Deployer;

use function Env\env;

// Host - Production
$prod_ip     = env('PROD_IP')     ?: getenv('PROD_IP');
$prod_port   = (int) (env('PROD_PORT')   ?: getenv('PROD_PORT'));
$prod_domain = env('PROD_DOMAIN') ?: getenv('PROD_DOMAIN');

// Host - Staging
$staging_ip     = env('STAGING_IP')     ?: getenv('STAGING_IP');
$staging_port   = (int) (env('STAGING_PORT')   ?: getenv('STAGING_PORT'));
$staging_domain = env('STAGING_DOMAIN') ?: getenv('STAGING_DOMAIN');

// EasyEngine management host (porta 22 padrão)
$mgmt_port       = 22;
$mgmt_user       = env('MGMT_USER') ?: getenv('MGMT_USER') ?: 'infoadm';
// CI staging: runner no mesmo servidor acessa via Docker (10.0.0.1)
// Local: definir STAGING_MGMT_IP com o IP real do servidor staging
$staging_mgmt_ip = env('STAGING_MGMT_IP') ?: getenv('STAGING_MGMT_IP') ?: '10.0.0.1';

// Configurações básicas
set('application', 'WordPress');
set('user', 'www-data');
set('keep_releases', 5);
set('composer_options', '--no-dev --prefer-dist --optimize-autoloader');

// Desabilitar git (modo CI)
set('repository', '.');
set('branch', 'main');
set('git_strategy', false);

set('target', function () {
    $branch = getenv('GITHUB_REF_NAME') ?: getenv('BRANCH_NAME');
    if (empty($branch) && file_exists(DEPLOY_ROOT . '/.git')) {
        $branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null'));
    }
    return $branch ?: 'unknown';
});

// Configurações WordPress — ajuste por projeto
set('shared_files', ['wp-config.php', 'nginx.conf', 'auth.json', 'robots.txt']);
set('shared_dirs', ['web/wp-content/uploads', 'web/wp-content/webp-express']);
set('writable_dirs', ['web/wp-content/uploads', 'web/wp-content/webp-express']);
set('writable_mode', 'chmod');
set('scripts_base_url', 'https://raw.githubusercontent.com/rodrigo-gpereira/wp-server-scripts/main');

host('production')
    ->setHostname($prod_ip)
    ->setPort($prod_port)
    ->set('remote_user', 'www-data')
    ->set('deploy_path', '/var/www/' . $prod_domain . '/htdocs')
    ->set('domain', $prod_domain)
    ->set('mgmt_host', $prod_ip)
    ->set('mgmt_port', $mgmt_port)
    ->set('mgmt_user', $mgmt_user)
    ->set('branch', 'main')
    ->set('env_required', ['PROD_IP', 'PROD_PORT', 'PROD_DOMAIN']);

host('staging')
    ->setHostname($staging_ip)
    ->setPort($staging_port)
    ->set('remote_user', 'www-data')
    ->set('deploy_path', '/var/www/' . $staging_domain . '/htdocs')
    ->set('domain', $staging_domain)
    ->set('mgmt_host', $staging_mgmt_ip)
    ->set('mgmt_port', $mgmt_port)
    ->set('mgmt_user', $mgmt_user)
    ->set('branch', 'develop')
    ->set('env_required', ['STAGING_IP', 'STAGING_PORT', 'STAGING_DOMAIN']);
