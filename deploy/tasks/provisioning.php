<?php

namespace Deployer;

use function Env\env;

task('composer:auth:upload', function () {
    $localAuth = DEPLOY_ROOT . '/auth.json';

    if (!file_exists($localAuth)) {
        throw new \Exception('auth.json não encontrado localmente.');
    }

    run('mkdir -p {{deploy_path}}/shared');
    upload($localAuth, '{{deploy_path}}/shared/auth.json');
    run('chmod 600 {{deploy_path}}/shared/auth.json');
})->desc('Envia auth.json para shared no servidor');

task('setup:scripts', function () {
    $baseDir          = dirname(get('deploy_path'));
    $remoteScriptsDir = "{$baseDir}/scripts";
    $stack            = get_prod_stack();
    $type             = get_project_type();
    $scriptsUrl       = get('scripts_base_url') . "/{$stack}/{$type}";
    $scripts          = ['backup-db.sh', 'backup-files.sh', 'restore.sh'];

    run("mkdir -p {$remoteScriptsDir}/lib");

    writeln("📥 Baixando lib/common.sh...");
    run("curl -fsSL {$scriptsUrl}/lib/common.sh -o {$remoteScriptsDir}/lib/common.sh");

    foreach ($scripts as $script) {
        writeln("📥 Baixando {$script}...");
        run("curl -fsSL {$scriptsUrl}/{$script} -o {$remoteScriptsDir}/{$script}");
    }

    run("chmod +x {$remoteScriptsDir}/*.sh");
    writeln("✅ Scripts instalados em {$remoteScriptsDir} ({$stack}/{$type})");
})->desc('Instala scripts de manutenção a partir do repositório remoto');

task('duplicati:register-backup-task', function () {
    assert_easyengine();

    $domain  = get('domain');
    $baseDir = dirname(get('deploy_path'));

    $b2Key   = env('B2_APPLICATION_KEY')    ?: getenv('B2_APPLICATION_KEY');
    $b2KeyId = env('B2_APPLICATION_KEY_ID') ?: getenv('B2_APPLICATION_KEY_ID');

    if (empty($b2Key) || empty($b2KeyId)) {
        throw new \Exception('B2_APPLICATION_KEY e B2_APPLICATION_KEY_ID devem estar definidos no .env');
    }

    $script = '/opt/easyengine/services/duplicati/create-duplicati-backup.sh';
    $cmd    = 'B2_APPLICATION_KEY=' . escapeshellarg($b2Key)
            . ' B2_APPLICATION_KEY_ID=' . escapeshellarg($b2KeyId)
            . ' ' . $script . ' ' . escapeshellarg($domain);

    writeln('📦 Registrando tarefa de backup no Duplicati para: ' . $domain);
    $output = run_on_management_host($cmd);
    writeln($output);

    $cronCmd = 'sudo ee cron create ' . escapeshellarg($domain)
             . ' --command=' . escapeshellarg("{$baseDir}/scripts/backup-db.sh")
             . ' --schedule=' . escapeshellarg('@daily');

    writeln('⏰ Registrando cron de backup do banco de dados...');
    $output = run_on_management_host($cronCmd);
    writeln($output);

    writeln('✅ Duplicati e cron configurados para: ' . $domain);
})->desc('Registra tarefa de backup no Duplicati e cron diário de banco via EasyEngine');
