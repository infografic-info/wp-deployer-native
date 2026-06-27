<?php

namespace Deployer;

define('DEPLOY_ROOT', __DIR__);

require __DIR__ . '/deploy/bootstrap.php';
require __DIR__ . '/deploy/config.php';

$deployCore = __DIR__ . '/vendor/infografic/wp-deployer-core/deploy.php';
if (!file_exists($deployCore)) {
	throw new \RuntimeException(
		'Pacote infografic/wp-deployer-core não encontrado em vendor. '
		. 'Execute `composer install --prefer-dist --optimize-autoloader` '
		. 'com dependencias de desenvolvimento habilitadas.'
	);
}

require $deployCore;
