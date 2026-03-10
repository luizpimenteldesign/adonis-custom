<?php
/**
 * Gerenciador de access_token do Mercado Livre via Client Credentials.
 * Cacheia o token em arquivo para não gerar um novo a cada requisição.
 */
if (!defined('ML_APP_ID')) {
    require_once __DIR__ . '/ml-config.php';
}

function mlGetToken(): string {
    // Tenta usar token em cache
    if (file_exists(ML_TOKEN_FILE)) {
        $cache = json_decode(file_get_contents(ML_TOKEN_FILE), true);
        if (!empty($cache['access_token']) && isset($cache['expires_at']) && time() < $cache['expires_at'] - 60) {
            return $cache['access_token'];
        }
    }

    // Garante que o diretório de logs existe
    $dir = dirname(ML_TOKEN_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Solicita novo token
    $ch = curl_init('https://api.mercadolibre.com/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POST           => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => ML_APP_ID,
            'client_secret' => ML_SECRET_KEY,
        ]),
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$resp) {
        throw new RuntimeException('Falha ao obter token ML: ' . $err);
    }

    $data = json_decode($resp, true);
    if (empty($data['access_token'])) {
        throw new RuntimeException('Token ML inválido: ' . $resp);
    }

    // Salva cache
    file_put_contents(ML_TOKEN_FILE, json_encode([
        'access_token' => $data['access_token'],
        'expires_at'   => time() + (int)($data['expires_in'] ?? 21600),
    ]));

    return $data['access_token'];
}
