<?php

namespace Deployer;

use function Env\env;

function is_first_release(): bool {
    $latestReleasePath = get('deploy_path') . '/.dep/latest_release';
    return !test("[ -f {$latestReleasePath} ]");
}

function get_project_type(): string {
    $type = env('PROJECT_TYPE') ?: getenv('PROJECT_TYPE') ?: 'native';
    if (!in_array($type, ['native', 'bedrock'])) {
        throw new \Exception("PROJECT_TYPE inválido: '{$type}'. Use 'native' ou 'bedrock'.");
    }
    return $type;
}

function assert_native_wp(): void {
    if (get_project_type() !== 'native') {
        throw new \Exception('Esta task é exclusiva para projetos WordPress Native. No Bedrock use o .env.');
    }
}

function get_prod_stack(): string {
    $stack = env('PROD_STACK') ?: getenv('PROD_STACK') ?: 'easyengine';
    $supported = ['easyengine'];
    if (!in_array($stack, $supported)) {
        throw new \Exception("PROD_STACK inválido: '{$stack}'. Suportados: " . implode(', ', $supported));
    }
    return $stack;
}

function assert_easyengine(): void {
    if (get_prod_stack() !== 'easyengine') {
        throw new \Exception('Esta operação requer PROD_STACK=easyengine.');
    }
    $result = run_on_management_host('which ee 2>/dev/null || echo NOT_FOUND');
    if (str_contains($result, 'NOT_FOUND')) {
        throw new \Exception('EasyEngine (ee) não encontrado no host de gerenciamento.');
    }
}

function run_on_management_host(string $cmd): string {
    $mgmtHost = get('mgmt_host');
    $mgmtPort = get('mgmt_port', 22);
    $mgmtUser = get('mgmt_user', 'infoadm');
    return runLocally(sprintf(
        'ssh -o StrictHostKeyChecking=no -p %d %s@%s "%s"',
        $mgmtPort, $mgmtUser, $mgmtHost, $cmd
    ));
}