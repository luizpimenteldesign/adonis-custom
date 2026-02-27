<?php
/**
 * DEPLOY AUTOMÁTICO - ADONIS CUSTOM
 * Acionado pelo webhook do GitHub a cada push na branch main
 */

// Chave secreta (deve ser a mesma cadastrada no GitHub Webhook)
define('WEBHOOK_SECRET', 'adonis2026deploy!');

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// Verificar assinatura do GitHub
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected  = 'sha256=' . hash_hmac('sha256', $payload, WEBHOOK_SECRET);

if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    die('Forbidden: Invalid signature');
}

// Decodificar payload e verificar se é push na main
$data = json_decode($payload, true);
if (($data['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(200);
    die('Ignored: Not main branch');
}

// Executar git pull
$repo_path = '/home1/luizpi39/adns.luizpimentel.com/adonis-custom';
$output = [];
$return = 0;

exec("cd {$repo_path} && git pull origin main 2>&1", $output, $return);

$log = date('Y-m-d H:i:s') . ' | Exit: ' . $return . ' | ' . implode(' ', $output) . PHP_EOL;
file_put_contents(__DIR__ . '/deploy.log', $log, FILE_APPEND);

if ($return === 0) {
    http_response_code(200);
    echo 'Deploy realizado com sucesso!';
} else {
    http_response_code(500);
    echo 'Erro no deploy: ' . implode('\n', $output);
}
