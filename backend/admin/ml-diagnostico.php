<?php
/**
 * DIAGNÓSTICO ML — Testa client_credentials e busca
 * REMOVA após diagnóstico!
 */
require_once 'auth.php';
require_once '../config/ml-config.php';
header('Content-Type: text/html; charset=utf-8');

// ── Passo 1: busca App Token ──────────────────────────────────────────────
$token_ok    = false;
$token_valor = '';
$token_erro  = '';
$token_raw   = '';

$ch = curl_init('https://api.mercadolibre.com/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => ML_APP_ID,
        'client_secret' => ML_SECRET_KEY,
    ]),
]);
$token_raw  = curl_exec($ch);
$token_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$token_cerr = curl_error($ch);
curl_close($ch);

$token_data = json_decode($token_raw, true);
if ($token_http === 200 && !empty($token_data['access_token'])) {
    $token_ok    = true;
    $token_valor = $token_data['access_token'];
} else {
    $token_erro = $token_raw;
}

// ── Passo 2: busca com token (se obtido) ─────────────────────────────────
$busca_http = 0;
$busca_raw  = '';
$busca_err  = '';

if ($token_ok) {
    $url = 'https://api.mercadolibre.com/sites/MLB/search?q=capacitor+ceramico&limit=3&buying_mode=buy_it_now';
    $ch2 = curl_init($url);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . $token_valor,
        ],
    ]);
    $busca_raw  = curl_exec($ch2);
    $busca_http = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    $busca_err  = curl_error($ch2);
    curl_close($ch2);
}

// ── Passo 3: busca sem token (para comparar) ─────────────────────────────
$sem_token_http = 0;
$sem_token_raw  = '';
$url3 = 'https://api.mercadolibre.com/sites/MLB/search?q=capacitor+ceramico&limit=3';
$ch3 = curl_init($url3);
curl_setopt_array($ch3, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$sem_token_raw  = curl_exec($ch3);
$sem_token_http = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

// ── Passo 4: busca usando o token de usuário (ml-token.php) ─────────────
$user_token_ok  = false;
$user_token_raw = '';
$user_busca_http= 0;
$user_busca_raw = '';
try {
    require_once '../config/ml-token.php';
    $user_token = mlGetToken();
    if ($user_token) {
        $user_token_ok = true;
        $url4 = 'https://api.mercadolibre.com/sites/MLB/search?q=capacitor+ceramico&limit=3&buying_mode=buy_it_now';
        $ch4  = curl_init($url4);
        curl_setopt_array($ch4, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $user_token,
            ],
        ]);
        $user_busca_raw  = curl_exec($ch4);
        $user_busca_http = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
        curl_close($ch4);
    }
} catch (Exception $e) {
    $user_token_raw = $e->getMessage();
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Diagnóstico ML</title>
<style>
body { font-family:monospace; padding:24px; background:#1e1e2e; color:#cdd6f4; }
h1   { color:#cba6f7; }
h2   { color:#89b4fa; margin-top:24px; border-top:1px solid #313244; padding-top:12px; }
.ok  { color:#a6e3a1; font-weight:bold; }
.err { color:#f38ba8; font-weight:bold; }
.warn{ color:#fab387; }
pre  { background:#313244; padding:12px; border-radius:6px; overflow-x:auto; white-space:pre-wrap; word-break:break-all; font-size:12px; }
.badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; }
.badge-ok  { background:#a6e3a1; color:#1e1e2e; }
.badge-err { background:#f38ba8; color:#1e1e2e; }
</style></head><body>
<h1>🔍 Diagnóstico ML API — Completo</h1>
<p>APP_ID: <strong><?php echo ML_APP_ID; ?></strong></p>

<h2>1. App Token (client_credentials)</h2>
<pre>HTTP: <?php echo $token_http; ?>
Resposta: <?php echo htmlspecialchars(substr($token_raw, 0, 500)); ?></pre>
<p class="<?php echo $token_ok ? 'ok' : 'err'; ?>">
    <?php echo $token_ok ? '✅ TOKEN OBTIDO' : '❌ FALHOU AO OBTER TOKEN'; ?>
</p>

<?php if ($token_ok): ?>
<h2>2. Busca com App Token</h2>
<pre>HTTP: <?php echo $busca_http; ?>
<?php echo htmlspecialchars(substr($busca_raw, 0, 600)); ?></pre>
<p class="<?php echo $busca_http === 200 ? 'ok' : 'err'; ?>">
    <?php echo $busca_http === 200 ? '✅ BUSCA COM APP TOKEN OK' : '❌ BUSCA COM APP TOKEN FALHOU (HTTP ' . $busca_http . ')'; ?>
</p>
<?php endif; ?>

<h2>3. Busca SEM token (referência)</h2>
<pre>HTTP: <?php echo $sem_token_http; ?>
<?php echo htmlspecialchars(substr($sem_token_raw, 0, 300)); ?></pre>
<p class="<?php echo $sem_token_http === 200 ? 'ok' : 'err'; ?>">
    <?php echo $sem_token_http === 200 ? '✅ SEM TOKEN FUNCIONA' : '❌ SEM TOKEN BLOQUEADO (HTTP ' . $sem_token_http . ')'; ?>
</p>

<h2>4. Busca com Token de Usuário (ml-token.php)</h2>
<?php if ($user_token_ok): ?>
<pre>HTTP: <?php echo $user_busca_http; ?>
<?php echo htmlspecialchars(substr($user_busca_raw, 0, 600)); ?></pre>
<p class="<?php echo $user_busca_http === 200 ? 'ok' : 'err'; ?>">
    <?php echo $user_busca_http === 200 ? '✅ TOKEN DE USUÁRIO FUNCIONA' : '❌ TOKEN DE USUÁRIO FALHOU (HTTP ' . $user_busca_http . ')'; ?>
</p>
<?php else: ?>
<pre>Erro ao carregar token de usuário: <?php echo htmlspecialchars($user_token_raw); ?></pre>
<?php endif; ?>

<p style="color:#6c7086;font-size:12px;margin-top:32px">⚠️ Remova este arquivo após o diagnóstico!</p>
</body></html>
