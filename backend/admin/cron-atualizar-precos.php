<?php
/**
 * CRON JOB — Atualização automática de preços via Mercado Livre
 * Adonis Custom — executar semanalmente (toda domingo 02:00)
 *
 * 0 2 * * 0   /usr/bin/php /home/luizpi39/public_html/backend/admin/cron-atualizar-precos.php >> /home/luizpi39/logs/precos.log 2>&1
 */
define('CRON_MODE', true);
@session_start();
$_SESSION['usuario_id'] = 0;

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ml-config.php';
require_once __DIR__ . '/../config/ml-token.php';

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
echo "\n[{$inicio}] === Iniciando atualização de preços ===\n";

// Obtém token antes do loop
try {
    $token = mlGetToken();
    echo "[AUTH] Token ML obtido com sucesso\n";
} catch (Exception $e) {
    echo "[FATAL] " . $e->getMessage() . "\n";
    exit(1);
}

$insumos = $conn->query(
    'SELECT id, nome, valorunitario FROM insumos WHERE ativo = 1 ORDER BY nome'
)->fetchAll(PDO::FETCH_ASSOC);

$total = count($insumos);
$atualizados = $sem_variacao = $bloqueados = $erros = 0;

foreach ($insumos as $ins) {
    $query       = $ins['nome'];
    $preco_atual = (float)$ins['valorunitario'];

    $url = 'https://api.mercadolibre.com/sites/' . ML_SITE . '/search?q=' . urlencode($query) . '&limit=' . ML_LIMIT;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => ML_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp) { echo "  [ERRO] {$ins['nome']} — sem resposta\n"; $erros++; sleep(1); continue; }

    $data = json_decode($resp, true);
    if (empty($data['results'])) { echo "  [SKIP] {$ins['nome']} — sem resultados\n"; $erros++; sleep(1); continue; }

    $precos = array_values(array_filter(array_column($data['results'], 'price'), fn($p) => $p > 0));
    if (empty($precos)) { echo "  [SKIP] {$ins['nome']} — sem preços válidos\n"; $erros++; sleep(1); continue; }

    $mediana      = medianaIQR($precos);
    $n            = count($precos);
    $variacao_pct = $preco_atual > 0
        ? round((($mediana - $preco_atual) / $preco_atual) * 100, 2)
        : 100.0;

    if (abs($variacao_pct) > MAX_VARIACAO && $preco_atual > 0) {
        $sinal = $variacao_pct > 0 ? '+' : '';
        echo "  [BLOQUEADO] {$ins['nome']}: {$sinal}{$variacao_pct}% — revise manualmente\n";
        $bloqueados++; sleep(1); continue;
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
            echo "  [ERRO] {$ins['nome']}: " . $e->getMessage() . "\n"; $erros++;
        }
    } else {
        echo "  [=]    {$ins['nome']}: estável ({$variacao_pct}%)\n";
        $sem_variacao++;
    }
    sleep(1);
}

$fim = date('Y-m-d H:i:s');
echo "\n[{$fim}] === Concluído: {$atualizados} atualizados | {$sem_variacao} estáveis | {$bloqueados} bloqueados | {$erros} erros | Total: {$total} ===\n";
