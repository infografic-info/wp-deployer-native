<?php

namespace Deployer;

task('db:import', function () {
    $domain   = get('domain');
    $localSql = DEPLOY_ROOT . '/init/data/db.sql';

    if (!file_exists($localSql)) {
        throw new \Exception('Arquivo init/data/db.sql não encontrado.');
    }

    $baseDir   = dirname(get('deploy_path'));
    $remoteSql = "{$baseDir}/init/data/db.sql";

    run("mkdir -p {$baseDir}/init/data");

    writeln('📤 Enviando db.sql para o servidor...');
    upload($localSql, $remoteSql);

    writeln('⚠️  Resetando banco de dados...');
    $output = run_on_management_host('sudo ee shell ' . $domain . ' --command=\"wp db reset --yes\"');
    writeln($output);

    writeln('⚙️  Importando banco de dados via WP-CLI...');
    $output = run_on_management_host('sudo ee shell ' . $domain . ' --command=\"wp db import /var/www/init/data/db.sql\"');
    writeln($output);

    run("rm -f {$remoteSql}");
    writeln('✅ Importação concluída e arquivo removido.');
})->desc('Envia e importa db.sql via WP-CLI');

task('db:replace-urls', function () {
    $domain    = get('domain');
    $localUrl  = trim(runLocally('ddev exec printenv DDEV_PRIMARY_URL'));
    $remoteUrl = 'https://' . $domain;

    if (empty($localUrl)) {
        throw new \Exception('Não foi possível obter DDEV_PRIMARY_URL. O ambiente DDEV está rodando?');
    }

    writeln("🔄 Substituindo URLs: {$localUrl} → {$remoteUrl}");
    $output = run_on_management_host('sudo ee shell ' . $domain . ' --command=\"wp search-replace ' . $localUrl . ' ' . $remoteUrl . ' --all-tables\"');
    writeln($output);

    writeln('🧹 Limpando cache...');
    $output = run_on_management_host('sudo ee shell ' . $domain . ' --command=\"wp cache flush\"');
    writeln($output);

    writeln('🔍 Verificando Elementor...');
    $output = run_on_management_host('sudo ee shell ' . $domain . ' --command=\"wp plugin is-active elementor && echo ELEMENTOR_ACTIVE\"');

    if (str_contains($output, 'ELEMENTOR_ACTIVE')) {
        writeln('⚙️  Elementor detectado, atualizando URLs...');
        $output = run_on_management_host('sudo ee shell ' . $domain . ' --command=\"wp elementor replace_urls ' . $localUrl . ' ' . $remoteUrl . '\"');
        writeln($output);
        $output = run_on_management_host('sudo ee shell ' . $domain . ' --command=\"wp elementor flush_css\"');
        writeln($output);
    } else {
        writeln('ℹ️  Elementor não está ativo, pulando.');
    }

    writeln('✅ Substituição de URLs concluída.');
})->desc('Substitui URLs do DDEV para produção via WP-CLI');

task('uploads:import', function () {
    $domain   = get('domain');
    $localTar = DEPLOY_ROOT . '/init/data/uploads.tar.gz';

    if (!file_exists($localTar)) {
        throw new \Exception('Arquivo init/data/uploads.tar.gz não encontrado.');
    }

    $baseDir      = dirname(get('deploy_path'));
    $remoteTar    = "{$baseDir}/init/data/uploads.tar.gz";
    $uploadsPath  = '/var/www/' . $domain . '/htdocs/shared';

    run("mkdir -p {$baseDir}/init/data");

    writeln('📤 Enviando uploads.tar.gz para o servidor...');
    upload($localTar, $remoteTar);

    writeln('📦 Extraindo uploads...');
    run("mkdir -p {$uploadsPath}");
    run("tar -xzf {$remoteTar} -C {$uploadsPath}");

    run("rm -f {$remoteTar}");
    writeln('✅ Uploads importados e arquivo removido.');
})->desc('Envia e extrai uploads.tar.gz para o diretório do site');

task('webp:import', function () {
    $domain        = get('domain');
    $localWebpTar  = DEPLOY_ROOT . '/init/data/webp-express.tar.gz';

    if (!file_exists($localWebpTar)) {
        writeln('ℹ️  webp-express.tar.gz não encontrado. Pulando importação de webp-express.');
        return;
    }

    $baseDir       = dirname(get('deploy_path'));
    $remoteWebpTar = "{$baseDir}/init/data/webp-express.tar.gz";
    $uploadsPath   = '/var/www/' . $domain . '/htdocs/shared';

    run("mkdir -p {$baseDir}/init/data");
    run("mkdir -p {$uploadsPath}");

    writeln('📤 Enviando webp-express.tar.gz para o servidor...');
    upload($localWebpTar, $remoteWebpTar);

    writeln('📦 Extraindo webp-express...');
    run("tar -xzf {$remoteWebpTar} -C {$uploadsPath}");
    run("rm -f {$remoteWebpTar}");
    writeln('✅ webp-express importado e arquivo removido.');
})->desc('Envia e extrai webp-express.tar.gz para o diretório do site');

task('init:data:import', [
    'db:import',
    'uploads:import',
    'webp:import',
])->desc('Importa banco, uploads e webp-express em sequência');

task('restore:latest', function () {
    writeln('♻️  Restaurando arquivos compartilhados e banco de dados...');
    $baseDir       = dirname(get('deploy_path'));
    $restoreScript = "{$baseDir}/scripts/restore.sh";

    if (test("[ -f {$restoreScript} ]")) {
        writeln("🔄 Executando restore: {$restoreScript}");
        run("cd {$baseDir} && ./scripts/restore.sh");
        writeln('✅ Restore concluído com sucesso');
    } else {
        writeln("⚠️  Warning: Restore script not found at {$restoreScript}");
        writeln('⏭️  Skipping restore...');
    }
});

task('wp:config:lock', function () {
    assert_native_wp();

    $domain    = get('domain');
    $constants = [
        'AUTOMATIC_UPDATER_DISABLED' => 'true',
        'DISALLOW_FILE_EDIT'         => 'true',
        'DISALLOW_FILE_MODS'         => 'true',
    ];

    foreach ($constants as $name => $value) {
        $cmd = 'sudo ee shell ' . $domain
             . ' --command=\"wp config set ' . $name . ' ' . $value . ' --raw --type=constant\"';
        writeln("🔒 Definindo {$name}...");
        writeln(run_on_management_host($cmd));
    }

    writeln('✅ wp-config.php bloqueado para produção.');
})->desc('Define constantes de segurança no wp-config.php via WP-CLI (Native WP)');

task('wp:config:unlock', function () {
    assert_native_wp();

    $domain    = get('domain');
    $constants = [
        'AUTOMATIC_UPDATER_DISABLED',
        'DISALLOW_FILE_EDIT',
        'DISALLOW_FILE_MODS',
    ];

    foreach ($constants as $name) {
        $cmd = 'sudo ee shell ' . $domain
             . ' --command=\"wp config delete ' . $name . ' --type=constant 2>/dev/null || true\"';
        writeln("🔓 Removendo {$name}...");
        writeln(run_on_management_host($cmd));
    }

    writeln('✅ wp-config.php desbloqueado para manutenção.');
})->desc('Remove constantes de segurança do wp-config.php via WP-CLI (Native WP)');
