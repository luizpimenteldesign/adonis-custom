<?php
/**
 * DEPLOY AUTOMÁTICO - ADONIS CUSTOM
 * Acionado pelo webhook do GitHub a cada push na branch main
 */

// Chave secreta (deve ser a mesma cadastrada no GitHub Webhook)
// Se não quiser usar secret, comente as linhas de verificação abaixo
define('WEBHOOK_SECRET', 'adonis2026deploy!');

// Log de debug
$debug_log = [];
$debug_log[] = date('Y-m-d H:i:s') . ' - Deploy iniciado';

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

$debug_log[] = 'Método POST OK';

// Verificar assinatura do GitHub (se SECRET estiver definido)
if (defined('WEBHOOK_SECRET') && WEBHOOK_SECRET !== '') {
    $payload   = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expected  = 'sha256=' . hash_hmac('sha256', $payload, WEBHOOK_SECRET);

    if (!hash_equals($expected, $signature)) {
        $debug_log[] = 'ERRO: Assinatura inválida';
        $debug_log[] = 'Esperado: ' . substr($expected, 0, 20) . '...';
        $debug_log[] = 'Recebido: ' . substr($signature, 0, 20) . '...';
        file_put_contents(__DIR__ . '/deploy.log', implode(PHP_EOL, $debug_log) . PHP_EOL, FILE_APPEND);
        http_response_code(403);
        die('Forbidden: Invalid signature');
    }
    $debug_log[] = 'Assinatura válida';
} else {
    $payload = file_get_contents('php://input');
    $debug_log[] = 'Secret desabilitado - pulando verificação';
}

// Decodificar payload e verificar se é push na main
$data = json_decode($payload, true);
$debug_log[] = 'Branch: ' . ($data['ref'] ?? 'N/A');

if (($data['ref'] ?? '') !== 'refs/heads/main') {
    $debug_log[] = 'Ignorado - não é a branch main';
    file_put_contents(__DIR__ . '/deploy.log', implode(PHP_EOL, $debug_log) . PHP_EOL, FILE_APPEND);
    http_response_code(200);
    die('Ignored: Not main branch');
}

$debug_log[] = 'Branch main detectada - iniciando git pull';

// Detectar caminho automático
$repo_path = __DIR__;
$debug_log[] = 'Caminho: ' . $repo_path;

// Executar git pull
$output = [];
$return = 0;

chdir($repo_path);
exec("git pull origin main 2>&1", $output, $return);

$debug_log[] = 'Exit code: ' . $return;
$debug_log[] = 'Output: ' . implode(' | ', $output);

// Salvar log completo
file_put_contents(__DIR__ . '/deploy.log', implode(PHP_EOL, $debug_log) . PHP_EOL . PHP_EOL, FILE_APPEND);

if ($return === 0) {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Deploy realizado com sucesso!',
        'output' => $output,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro no deploy',
        'output' => $output,
        'return_code' => $return,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
