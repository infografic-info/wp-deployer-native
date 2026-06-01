<?php

namespace Deployer;

define('DEPLOY_ROOT', __DIR__);

require __DIR__ . '/deploy/bootstrap.php';
require __DIR__ . '/deploy/helpers.php';
require __DIR__ . '/deploy/config.php';
require __DIR__ . '/deploy/tasks/deploy-chain.php';
require __DIR__ . '/deploy/tasks/provisioning.php';
require __DIR__ . '/deploy/tasks/maintenance.php';
