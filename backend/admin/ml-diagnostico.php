<?php
/**
 * DIAGNÓSTICO — Teste de conectividade com a API do Mercado Livre
 * Acesse: /backend/admin/ml-diagnostico.php
 * REMOVA este arquivo após o diagnóstico!
 */
require_once 'auth.php';
header('Content-Type: text/html; charset=utf-8');

$url_teste = 'https://api.mercadolibre.com/sites/MLB/search?q=capacitor+ceramico&limit=3&buying_mode=buy_it_now';

$resultados = [];

// Teste 1: cURL
$ch = curl_init($url_teste);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_FOLLOWLOCATION => true,
]);
$resp  = curl_exec($ch);
$errno = curl_errno($ch);
$err   = curl_error($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resultados['curl'] = [
    'ok'        => ($errno === 0 && $http === 200),
    'http_code' => $http,
    'curl_errno'=> $errno,
    'curl_error'=> $err,
    'resposta'  => $resp ? substr($resp, 0, 300) : '(vazia)',
];

// Teste 2: file_get_contents com stream_context
$ctx  = stream_context_create(['http' => [
    'method'     => 'GET',
    'header'     => "Accept: application/json\r\nUser-Agent: Mozilla/5.0\r\n",
    'timeout'    => 10,
    'ignore_errors' => true,
]]);
$resp2 = @file_get_contents($url_teste, false, $ctx);
$resultados['file_get_contents'] = [
    'ok'       => ($resp2 !== false && strlen($resp2) > 10),
    'resposta' => $resp2 ? substr($resp2, 0, 300) : '(vazia ou bloqueado)',
];

// Teste 3: allow_url_fopen habilitado?
$resultados['allow_url_fopen'] = ini_get('allow_url_fopen') ? 'ON' : 'OFF';

// Teste 4: cURL instalado?
$resultados['curl_instalado'] = function_exists('curl_init') ? 'SIM' : 'NÃO';

// Teste 5: funções de socket disponíveis
$resultados['fsockopen'] = function_exists('fsockopen') ? 'SIM' : 'NÃO';

// Exibe
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Diagnóstico ML</title>
<style>body{font-family:monospace;padding:24px;background:#1e1e2e;color:#cdd6f4}h1{color:#cba6f7}h2{color:#89b4fa;margin-top:20px}
.ok{color:#a6e3a1}.err{color:#f38ba8}.warn{color:#fab387}pre{background:#313244;padding:12px;border-radius:6px;overflow-x:auto}
</style></head><body>
<h1>🔍 Diagnóstico ML API</h1>
<h2>cURL</h2>
<pre>
Status HTTP : <?php echo $resultados['curl']['http_code']; ?>
cURL errno  : <?php echo $resultados['curl']['curl_errno']; ?>
cURL error  : <?php echo htmlspecialchars($resultados['curl']['curl_error'] ?: '(nenhum)'); ?>
Resposta    : <?php echo htmlspecialchars($resultados['curl']['resposta']); ?>
</pre>
<p class="<?php echo $resultados['curl']['ok'] ? 'ok' : 'err'; ?>">
    <?php echo $resultados['curl']['ok'] ? '✅ cURL FUNCIONANDO' : '❌ cURL FALHOU'; ?>
</p>

<h2>file_get_contents</h2>
<pre><?php echo htmlspecialchars($resultados['file_get_contents']['resposta']); ?></pre>
<p class="<?php echo $resultados['file_get_contents']['ok'] ? 'ok' : 'err'; ?>">
    <?php echo $resultados['file_get_contents']['ok'] ? '✅ file_get_contents FUNCIONANDO' : '❌ file_get_contents FALHOU'; ?>
</p>

<h2>Configurações PHP</h2>
<pre>
allow_url_fopen : <?php echo $resultados['allow_url_fopen']; ?>
cURL instalado  : <?php echo $resultados['curl_instalado']; ?>
fsockopen       : <?php echo $resultados['fsockopen']; ?>
PHP versão      : <?php echo phpversion(); ?>
Servidor        : <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'desconhecido'; ?>
</pre>

<p style="color:#6c7086;font-size:12px;margin-top:32px">⚠️ Remova este arquivo após o diagnóstico!</p>
</body></html>
