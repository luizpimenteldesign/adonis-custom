<?php
/**
 * CRON JOB — Atualização automática de preços via Mercado Livre
 * Adonis Custom — executar semanalmente (toda domingo 02:00)
 *
 * Configurar no cPanel > Cron Jobs:
 * 0 2 * * 0   /usr/bin/php /home/luizpi39/public_html/backend/admin/cron-atualizar-precos.php >> /home/luizpi39/logs/precos.log 2>&1
 */

define('CRON_MODE', true);

// Simula sessão para não travar no auth.php
@session_start();
$_SESSION['usuario_id'] = 0; // cron não precisa de auth

require_once __DIR__ . '/../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

// Configurações
define('ML_SITE',      'MLB');
define('ML_LIMIT',     5);
define('MIN_VARIACAO', 3.0);
define('ML_TIMEOUT',   8);

$inicio = date('Y-m-d H:i:s');
echo "\n[{$inicio}] === Iniciando atualização de preços ===\n";

$insumos = $conn->query('SELECT id, nome, valor_unitario FROM insumos WHERE ativo = 1 ORDER BY nome')
               ->fetchAll(PDO::FETCH_ASSOC);

$total = count($insumos);
$atualizados = 0;
$sem_variacao = 0;
$erros = 0;

foreach ($insumos as $ins) {
    $query       = $ins['nome'];
    $preco_atual = (float)$ins['valor_unitario'];

    $url = 'https://api.mercadolibre.com/sites/' . ML_SITE . '/search?q=' . urlencode($query) . '&limit=' . ML_LIMIT;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => ML_TIMEOUT,
        CURLOPT_USERAGENT      => 'AdonisCustom-Cron/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp) { echo "  [ERRO] {$ins['nome']} — sem resposta da API\n"; $erros++; continue; }

    $data = json_decode($resp, true);
    if (empty($data['results'])) { echo "  [SKIP] {$ins['nome']} — sem resultados\n"; $erros++; continue; }

    $precos = array_filter(array_column($data['results'], 'price'), fn($p) => $p > 0);
    if (empty($precos)) { echo "  [SKIP] {$ins['nome']} — sem preços válidos\n"; $erros++; continue; }

    sort($precos);
    $n = count($precos);
    $mediana = $n % 2 === 0
        ? ($precos[$n/2 - 1] + $precos[$n/2]) / 2
        : $precos[intval($n/2)];
    $mediana = round($mediana, 2);

    $variacao_pct = $preco_atual > 0
        ? round((($mediana - $preco_atual) / $preco_atual) * 100, 2)
        : 100.0;

    if (abs($variacao_pct) >= MIN_VARIACAO || $preco_atual == 0) {
        $conn->prepare('UPDATE insumos SET valor_unitario = ? WHERE id = ?')->execute([$mediana, $ins['id']]);
        $conn->prepare('
            INSERT INTO insumos_precos_historico
                (insumo_id, preco_anterior, preco_novo, variacao_pct, fonte, query_usada)
            VALUES (?, ?, ?, ?, "mercadolivre", ?)
        ')->execute([$ins['id'], $preco_atual, $mediana, $variacao_pct, $query]);

        $sinal = $variacao_pct > 0 ? '+' : '';
        echo "  [OK]   {$ins['nome']}: R$ {$preco_atual} → R$ {$mediana} ({$sinal}{$variacao_pct}%)\n";
        $atualizados++;
    } else {
        echo "  [=]    {$ins['nome']}: estável ({$variacao_pct}%)\n";
        $sem_variacao++;
    }

    sleep(1); // respeita rate limit da API ML
}

$fim = date('Y-m-d H:i:s');
echo "\n[{$fim}] === Concluído: {$atualizados} atualizados | {$sem_variacao} estáveis | {$erros} erros | Total: {$total} ===\n";
