<?php
/**
 * Proxy para API do Mercado Livre.
 * O browser chama este arquivo (mesmo domínio = sem CORS).
 * Este arquivo chama api.mercadolivre.com.br (domínio BR, menos restritivo que api.mercadolibre.com).
 */
require_once 'auth.php';
header('Content-Type: application/json');

$q            = trim($_GET['q']            ?? '');
$limit        = min((int)($_GET['limit']   ?? 50), 50);
$buying_mode  = $_GET['buying_mode']       ?? 'buy_it_now';
$condition    = $_GET['condition']         ?? 'new';

if (!$q) {
    echo json_encode(['error' => 'Parâmetro q obrigatório']);
    exit;
}

$params = http_build_query([
    'q'           => $q,
    'limit'       => $limit,
    'buying_mode' => $buying_mode,
    'condition'   => $condition,
]);

// Tenta domínio .com.br primeiro, fallback para .com
$urls = [
    'https://api.mercadolivre.com.br/sites/MLB/search?' . $params,
    'https://api.mercadolibre.com/sites/MLB/search?' . $params,
];

$resposta = null;

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Accept-Language: pt-BR,pt;q=0.9',
            'X-Requested-With: XMLHttpRequest',
        ],
    ]);
    $raw  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if (!$err && $http === 200 && $raw) {
        $resposta = $raw;
        break;
    }
}

if (!$resposta) {
    echo json_encode(['error' => 'API indisponível (HTTP ' . $http . ')']);
    exit;
}

// Repassa o JSON diretamente
echo $resposta;
