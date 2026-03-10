<?php
/**
 * CRON JOB — Atualização automática de preços via Mercado Livre
 * Adonis Custom — executar semanalmente (toda domingo 02:00)
 *
 * Configurar no cPanel > Cron Jobs:
 * 0 2 * * 0   /usr/bin/php /home/luizpi39/public_html/backend/admin/cron-atualizar-precos.php >> /home/luizpi39/logs/precos.log 2>&1
 *
 * Só processa insumos com query_ml definida.
 * Aplica filtro IQR para descartar outliers.
 * Bloqueia atualizações com variação > 50% (registra aviso no log).
 */

define('CRON_MODE', true);

@session_start();
$_SESSION['usuario_id'] = 0;

require_once __DIR__ . '/../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

define('ML_SITE',      'MLB');
define('ML_LIMIT',     20);
define('MIN_VARIACAO', 3.0);
define('MAX_VARIACAO', 50.0);
define('ML_TIMEOUT',   8);

function medianaIQR(array $precos): ?float {
    if (empty($precos)) return null;
    sort($precos);
    $n   = count($precos);
    $q1  = $precos[intval($n * 0.25)];
    $q3  = $precos[intval($n * 0.75)];
    $iqr = $q3 - $q1;
    $filtrados = array_values(array_filter($precos, fn($p) => $p >= ($q1 - 1.5 * $iqr) && $p <= ($q3 + 1.5 * $iqr)));
    if (empty($filtrados)) $filtrados = $precos;
    $nf = count($filtrados);
    return round($nf % 2 === 0
        ? ($filtrados[$nf/2 - 1] + $filtrados[$nf/2]) / 2
        : $filtrados[intval($nf/2)], 2);
}

$inicio = date('Y-m-d H:i:s');
echo "\n[{$inicio}] === Iniciando atualização de preços (apenas rastreados) ===\n";

// Busca APENAS insumos com query_ml definida
$insumos = $conn->query(
    "SELECT id, nome, valorunitario, query_ml FROM insumos WHERE ativo = 1 AND query_ml IS NOT NULL AND query_ml != '' ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

$total        = count($insumos);
$atualizados  = 0;
$sem_variacao = 0;
$bloqueados   = 0;
$erros        = 0;

echo "[INFO] {$total} insumos rastreados encontrados\n";

foreach ($insumos as $ins) {
    $query       = $ins['query_ml'];
    $preco_atual = (float)$ins['valorunitario'];

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

    if (!$resp) {
        echo "  [ERRO] {$ins['nome']} — sem resposta da API\n";
        $erros++;
        sleep(1);
        continue;
    }

    $data = json_decode($resp, true);
    if (empty($data['results'])) {
        echo "  [SKIP] {$ins['nome']} — nenhum resultado para: {$query}\n";
        $erros++;
        sleep(1);
        continue;
    }

    $precos = array_values(array_filter(array_column($data['results'], 'price'), fn($p) => $p > 0));
    if (empty($precos)) {
        echo "  [SKIP] {$ins['nome']} — sem preços válidos\n";
        $erros++;
        sleep(1);
        continue;
    }

    $mediana = medianaIQR($precos);
    $n       = count($precos);

    $variacao_pct = $preco_atual > 0
        ? round((($mediana - $preco_atual) / $preco_atual) * 100, 2)
        : 100.0;

    // Bloqueia variações suspeitas no cron (requer confirmação manual na tela)
    if (abs($variacao_pct) > MAX_VARIACAO && $preco_atual > 0) {
        $sinal = $variacao_pct > 0 ? '+' : '';
        echo "  [BLOQUADO] {$ins['nome']}: variação {$sinal}{$variacao_pct}% suspeita — revise manualmente\n";
        $bloqueados++;
        sleep(1);
        continue;
    }

    if (abs($variacao_pct) >= MIN_VARIACAO || $preco_atual == 0) {
        try {
            $conn->prepare('UPDATE insumos SET valorunitario = ? WHERE id = ?')->execute([$mediana, $ins['id']]);
            $conn->prepare('
                INSERT INTO insumos_precos_historico
                    (insumo_id, preco_anterior, preco_novo, variacao_pct, fonte, query_usada)
                VALUES (?, ?, ?, ?, "mercadolivre", ?)
            ')->execute([$ins['id'], $preco_atual, $mediana, $variacao_pct, $query]);
            $sinal = $variacao_pct > 0 ? '+' : '';
            echo "  [OK]   {$ins['nome']}: R$ {$preco_atual} → R$ {$mediana} ({$sinal}{$variacao_pct}%) [{$n} resultados]\n";
            $atualizados++;
        } catch (Exception $e) {
            echo "  [ERRO] {$ins['nome']}: " . $e->getMessage() . "\n";
            $erros++;
        }
    } else {
        echo "  [=]    {$ins['nome']}: estável ({$variacao_pct}%)\n";
        $sem_variacao++;
    }

    sleep(1);
}

$fim = date('Y-m-d H:i:s');
echo "\n[{$fim}] === Concluído: {$atualizados} atualizados | {$sem_variacao} estáveis | {$bloqueados} bloqueados | {$erros} erros | Total rastreados: {$total} ===\n";
