<?php
/**
 * CRON JOB — Atualização automática de preços via Mercado Livre
 * Adonis Custom — executar semanalmente (toda domingo 02:00)
 *
 * 0 2 * * 0   /usr/bin/php /home/luizpi39/public_html/backend/admin/cron-atualizar-precos.php >> /home/luizpi39/logs/precos.log 2>&1
 */
define('CRON_MODE', true);
@session_start();
$_SESSION['admin_logado'] = true;

require_once __DIR__ . '/../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

define('ML_SITE',      'MLB');
define('ML_LIMIT',     50);
define('MIN_VARIACAO', 3.0);
define('MAX_VARIACAO', 50.0);
define('ML_TIMEOUT',   10);

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

function mlBuscarPrecos(string $query): array {
    $params = http_build_query([
        'q'           => $query,
        'limit'       => ML_LIMIT,
        'buying_mode' => 'buy_it_now',
        'condition'   => 'new',
    ]);
    $url = 'https://api.mercadolibre.com/sites/' . ML_SITE . '/search?' . $params;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => ML_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$resp) return ['error' => 'Falha na API ML: ' . $err];

    $data = json_decode($resp, true);
    if (empty($data['results'])) return ['error' => 'Nenhum resultado'];

    $precos = array_values(array_filter(array_column($data['results'], 'price'), fn($p) => $p > 0));
    if (empty($precos)) return ['error' => 'Sem preços válidos'];

    return ['precos' => $precos, 'total' => $data['paging']['total'] ?? count($precos)];
}

$inicio = date('Y-m-d H:i:s');
echo "\n[{$inicio}] === Iniciando atualização de preços (API pública MLB/search) ===\n";

$insumos = $conn->query(
    'SELECT id, nome, valorunitario FROM insumos WHERE ativo = 1 ORDER BY nome'
)->fetchAll(PDO::FETCH_ASSOC);

$total = count($insumos);
$atualizados = $sem_variacao = $bloqueados = $erros = 0;

foreach ($insumos as $ins) {
    $query       = $ins['nome'];
    $preco_atual = (float)$ins['valorunitario'];

    $resultado = mlBuscarPrecos($query);
    if (isset($resultado['error'])) {
        echo "  [SKIP] {$ins['nome']} — {$resultado['error']}\n";
        $erros++; sleep(1); continue;
    }

    $precos       = $resultado['precos'];
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
