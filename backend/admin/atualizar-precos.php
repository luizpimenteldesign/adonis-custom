<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `insumos_precos_historico` (
            `id`             INT(11) NOT NULL AUTO_INCREMENT,
            `insumo_id`      INT(11) NOT NULL,
            `preco_anterior` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `preco_novo`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `variacao_pct`   DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            `fonte`          VARCHAR(50) NOT NULL DEFAULT 'mercadolivre',
            `query_usada`    VARCHAR(200) DEFAULT NULL,
            `atualizado_em`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_insumo_id` (`insumo_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) { /* ignora */ }

define('ML_SITE',      'MLB');
define('ML_LIMIT',     50);
define('MIN_VARIACAO', 3.0);
define('MAX_VARIACAO', 50.0);
define('ML_TIMEOUT',   12);

try {
    $conn->query('SELECT 1 FROM insumos_precos_historico LIMIT 1');
    $historico_existe = true;
} catch (Exception $e) {
    $historico_existe = false;
}

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

/**
 * Busca preços na API pública do ML (/sites/MLB/search).
 * Tenta primeiro cURL; se falhar (ex: HostGator bloqueado), tenta file_get_contents.
 * Retorna ['precos' => [...], 'total' => N] ou ['error' => 'mensagem', 'debug' => '...'].
 */
function mlBuscarPrecos(string $query): array {
    $params = http_build_query([
        'q'           => $query,
        'limit'       => ML_LIMIT,
        'buying_mode' => 'buy_it_now',
        'condition'   => 'new',
    ]);
    $url = 'https://api.mercadolibre.com/sites/' . ML_SITE . '/search?' . $params;

    $resp  = false;
    $debug = [];

    // --- Tentativa 1: cURL ---
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => ML_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Accept-Language: pt-BR,pt;q=0.9',
            ],
        ]);
        $raw   = curl_exec($ch);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $debug[] = "cURL: http={$http} errno={$errno} err={$err}";

        if ($errno === 0 && $http === 200 && $raw) {
            $resp = $raw;
        }
    }

    // --- Tentativa 2: file_get_contents ---
    if (!$resp && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => [
            'method'         => 'GET',
            'header'         => "Accept: application/json\r\nUser-Agent: Mozilla/5.0\r\n",
            'timeout'        => ML_TIMEOUT,
            'ignore_errors'  => true,
        ], 'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
        ]]);
        $raw2  = @file_get_contents($url, false, $ctx);
        $debug[] = 'file_get_contents: ' . ($raw2 !== false ? strlen($raw2) . ' bytes' : 'falhou');
        if ($raw2 !== false && strlen($raw2) > 10) {
            $resp = $raw2;
        }
    }

    if (!$resp) {
        return [
            'error' => 'Sem acesso à API ML (cURL e file_get_contents falharam)',
            'debug' => implode(' | ', $debug),
        ];
    }

    $data = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'JSON inválido: ' . json_last_error_msg(), 'debug' => substr($resp, 0, 200)];
    }
    if (empty($data['results'])) {
        $total = $data['paging']['total'] ?? 0;
        return ['error' => "Nenhum resultado (total={$total}) para: {$query}", 'debug' => implode(' | ', $debug)];
    }

    $precos = array_values(array_filter(array_column($data['results'], 'price'), fn($p) => $p > 0));
    if (empty($precos)) {
        return ['error' => 'Resultados sem preço válido', 'debug' => implode(' | ', $debug)];
    }

    return ['precos' => $precos, 'total' => $data['paging']['total'] ?? count($precos)];
}

// ── API: atualizar (AJAX) ─────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'atualizar') {
    header('Content-Type: application/json');

    if (!$historico_existe) {
        echo json_encode(['ok' => false, 'erro' => 'Tabela de histórico não encontrada.']);
        exit;
    }

    $insumo_id = (int)($_POST['insumo_id'] ?? 0);
    if (!$insumo_id) { echo json_encode(['ok' => false, 'erro' => 'ID inválido']); exit; }

    $stmt = $conn->prepare('SELECT id, nome, valorunitario FROM insumos WHERE id = ? AND ativo = 1');
    $stmt->execute([$insumo_id]);
    $ins = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ins) { echo json_encode(['ok' => false, 'erro' => 'Insumo não encontrado']); exit; }

    $query       = trim($_POST['query'] ?? $ins['nome']);
    $preco_atual = (float)$ins['valorunitario'];

    $resultado = mlBuscarPrecos($query);
    if (isset($resultado['error'])) {
        echo json_encode([
            'ok'    => false,
            'erro'  => $resultado['error'],
            'debug' => $resultado['debug'] ?? '',
        ]);
        exit;
    }

    $precos       = $resultado['precos'];
    $mediana      = medianaIQR($precos);
    $n            = count($precos);
    $variacao_pct = $preco_atual > 0
        ? round((($mediana - $preco_atual) / $preco_atual) * 100, 2)
        : 100.0;

    $suspeito   = abs($variacao_pct) > MAX_VARIACAO && $preco_atual > 0;
    $confirmado = isset($_POST['confirmado']) && $_POST['confirmado'] === '1';
    $atualizado = false;

    if (!$suspeito || $confirmado) {
        if (abs($variacao_pct) >= MIN_VARIACAO || $preco_atual == 0) {
            $conn->prepare('UPDATE insumos SET valorunitario = ? WHERE id = ?')
                 ->execute([$mediana, $insumo_id]);
            $conn->prepare('
                INSERT INTO insumos_precos_historico
                    (insumo_id, preco_anterior, preco_novo, variacao_pct, fonte, query_usada)
                VALUES (?, ?, ?, ?, "mercadolivre", ?)
            ')->execute([$insumo_id, $preco_atual, $mediana, $variacao_pct, $query]);
            $atualizado = true;
        }
    }

    echo json_encode([
        'ok'           => true,
        'atualizado'   => $atualizado,
        'suspeito'     => $suspeito && !$confirmado,
        'insumo'       => $ins['nome'],
        'preco_antes'  => $preco_atual,
        'preco_novo'   => $mediana,
        'variacao_pct' => $variacao_pct,
        'n_resultados' => $n,
        'query'        => $query,
        'msg'          => $suspeito && !$confirmado
            ? "⚠️ Variação suspeita ({$variacao_pct}%). Confirme manualmente."
            : ($atualizado
                ? "Atualizado: R$ " . number_format($preco_atual,2,',','.') . " → R$ " . number_format($mediana,2,',','.')
                : "Sem variação relevante ({$variacao_pct}%)"),
    ]);
    exit;
}

// ── API: histórico ────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'historico') {
    header('Content-Type: application/json');
    if (!$historico_existe) { echo json_encode([]); exit; }
    $id   = (int)($_GET['id'] ?? 0);
    $rows = $conn->prepare('
        SELECT preco_anterior, preco_novo, variacao_pct, fonte, query_usada, atualizado_em
        FROM insumos_precos_historico
        WHERE insumo_id = ?
        ORDER BY atualizado_em DESC
        LIMIT 20
    ');
    $rows->execute([$id]);
    echo json_encode($rows->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── Carrega insumos ───────────────────────────────────────────────────
try {
    if ($historico_existe) {
        $insumos = $conn->query('
            SELECT i.id, i.nome, i.unidade, i.valorunitario,
                   MAX(h.atualizado_em) as ultima_atualizacao
            FROM insumos i
            LEFT JOIN insumos_precos_historico h ON h.insumo_id = i.id AND h.fonte = "mercadolivre"
            WHERE i.ativo = 1
            GROUP BY i.id
            ORDER BY i.nome
        ')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $insumos = $conn->query(
            'SELECT id, nome, unidade, valorunitario, NULL as ultima_atualizacao FROM insumos WHERE ativo = 1 ORDER BY nome'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $insumos = [];
    $erro_carregamento = $e->getMessage();
}

$current_page = 'atualizar-precos.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Preços — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
    <style>
    .preco-card {
        display:grid;
        grid-template-columns: 1fr auto auto auto;
        align-items:center;
        gap:12px;
        padding:12px 16px;
        border:1px solid var(--g-border);
        border-radius:10px;
        background:var(--g-surface);
        margin-bottom:8px;
        transition:border-color .2s;
    }
    .preco-card:hover       { border-color:var(--color-primary,#7c3aed); }
    .preco-card.atualizado  { border-color:#22c55e; background:#f0fdf4; }
    .preco-card.erro        { border-color:#ef4444; background:#fef2f2; }
    .preco-card.suspeito    { border-color:#f59e0b; background:#fffbeb; }
    .preco-card.sem-variacao { opacity:.7; }
    .preco-nome  { font-size:13px; font-weight:600; }
    .preco-meta  { font-size:11px; color:var(--g-text-3); margin-top:2px; }
    .preco-valor {
        font-size:15px; font-weight:700;
        color:var(--color-primary,#7c3aed);
        white-space:nowrap; min-width:90px; text-align:right;
    }
    .preco-status {
        font-size:11px; padding:3px 8px; border-radius:12px;
        white-space:nowrap; min-width:80px; text-align:center;
    }
    .preco-status.aguardando { background:var(--g-border); color:var(--g-text-2); }
    .preco-status.buscando   { background:#fef9c3; color:#854d0e; }
    .preco-status.ok         { background:#dcfce7; color:#166534; }
    .preco-status.unchanged  { background:#f1f5f9; color:#64748b; }
    .preco-status.falhou     { background:#fee2e2; color:#991b1b; }
    .preco-status.alerta     { background:#fef3c7; color:#92400e; }
    .btn-hist {
        padding:4px 8px; border-radius:6px; border:1px solid var(--g-border);
        background:transparent; cursor:pointer; font-size:12px;
        color:var(--g-text-2); display:flex; align-items:center; gap:3px;
    }
    .btn-hist:hover { background:var(--g-hover); }
    .progress-bar-wrap { height:6px; background:var(--g-border); border-radius:4px; overflow:hidden; margin:8px 0; }
    .progress-bar      { height:100%; background:var(--color-primary,#7c3aed); border-radius:4px; transition:width .4s; width:0%; }
    .stats-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
    .stat-box  { flex:1; min-width:120px; padding:14px 16px; background:var(--g-surface); border:1px solid var(--g-border); border-radius:10px; text-align:center; }
    .stat-box .val { font-size:22px; font-weight:700; color:var(--color-primary,#7c3aed); }
    .stat-box .lbl { font-size:11px; color:var(--g-text-3); margin-top:2px; }
    .hist-table { width:100%; border-collapse:collapse; font-size:12px; }
    .hist-table th { text-align:left; padding:6px 8px; border-bottom:2px solid var(--g-border); color:var(--g-text-2); }
    .hist-table td { padding:6px 8px; border-bottom:1px solid var(--g-border); }
    .variacao-pos { color:#16a34a; font-weight:600; }
    .variacao-neg { color:#dc2626; font-weight:600; }
    .cron-box { background:#1e1e2e; color:#cdd6f4; font-family:monospace; padding:16px; border-radius:8px; font-size:13px; white-space:pre; overflow-x:auto; }
    .aviso-tabela { background:#fef9c3; border:1px solid #fcd34d; border-radius:8px; padding:12px 16px; font-size:13px; color:#92400e; margin-bottom:16px; }
    .debug-info { font-size:10px; color:#94a3b8; font-family:monospace; margin-top:2px; }
    </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="app-layout">
<?php include '_sidebar.php'; ?>
<main class="main-content">
    <div class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <span class="topbar-title">Atualizar Preços</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px">price_check</span>Atualizar Preços de Insumos
                </h1>
                <div class="page-subtitle">Busca preços de referência no Mercado Livre e atualiza automaticamente</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <a href="ml-diagnostico.php" class="btn btn-secondary" style="font-size:12px" target="_blank">🔍 Diagnóstico</a>
                <button class="btn btn-primary" id="btn-atualizar-todos" onclick="atualizarTodos()" <?php echo !$historico_existe ? 'disabled title="Crie a tabela de histórico primeiro"' : ''; ?>>
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">sync</span> Atualizar Todos
                </button>
            </div>
        </div>

        <?php if (!$historico_existe): ?>
        <div class="aviso-tabela">
            <strong>⚠️ Tabela de histórico não encontrada.</strong> Execute o SQL abaixo no phpMyAdmin:
            <pre style="margin:8px 0 0;font-size:12px;background:#fff8;padding:8px;border-radius:4px;overflow-x:auto">CREATE TABLE IF NOT EXISTS `insumos_precos_historico` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `insumo_id` INT(11) NOT NULL,
  `preco_anterior` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `preco_novo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `variacao_pct` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `fonte` VARCHAR(50) NOT NULL DEFAULT 'mercadolivre',
  `query_usada` VARCHAR(200) DEFAULT NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_insumo_id` (`insumo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</pre>
        </div>
        <?php endif; ?>

        <?php if (isset($erro_carregamento)): ?>
        <div class="aviso-tabela"><strong>Erro ao carregar insumos:</strong> <?php echo htmlspecialchars($erro_carregamento); ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box"><div class="val" id="stat-total"><?php echo count($insumos); ?></div><div class="lbl">Total de Insumos</div></div>
            <div class="stat-box"><div class="val" id="stat-atualizados">0</div><div class="lbl">Atualizados agora</div></div>
            <div class="stat-box"><div class="val" id="stat-sem-variacao">0</div><div class="lbl">Sem variação</div></div>
            <div class="stat-box"><div class="val" id="stat-erros">0</div><div class="lbl">Erros / Suspeitos</div></div>
        </div>

        <div id="progress-wrap" style="display:none">
            <div style="font-size:12px;color:var(--g-text-2);margin-bottom:4px">
                Processando <span id="prog-atual">0</span> de <span id="prog-total">0</span> insumos...
            </div>
            <div class="progress-bar-wrap"><div class="progress-bar" id="progress-bar"></div></div>
        </div>

        <div id="lista-insumos">
        <?php foreach ($insumos as $ins):
            $ultima = !empty($ins['ultima_atualizacao']) ? date('d/m/Y H:i', strtotime($ins['ultima_atualizacao'])) : null;
        ?>
        <div class="preco-card" id="card-<?php echo $ins['id']; ?>" data-id="<?php echo $ins['id']; ?>">
            <div>
                <div class="preco-nome"><?php echo htmlspecialchars($ins['nome']); ?></div>
                <div class="preco-meta">
                    <?php echo htmlspecialchars($ins['unidade']); ?>
                    <?php if ($ultima): ?> • <span style="color:var(--g-blue)">Última ML: <?php echo $ultima; ?></span><?php endif; ?>
                </div>
                <div class="debug-info" id="debug-<?php echo $ins['id']; ?>"></div>
            </div>
            <div class="preco-valor" id="val-<?php echo $ins['id']; ?>">
                R$ <?php echo number_format((float)$ins['valorunitario'], 2, ',', '.'); ?>
            </div>
            <span class="preco-status aguardando" id="status-<?php echo $ins['id']; ?>">Aguardando</span>
            <button class="btn-hist" onclick="verHistorico(<?php echo $ins['id']; ?>, '<?php echo htmlspecialchars(addslashes($ins['nome'])); ?>')"
                title="Ver histórico" <?php echo !$historico_existe ? 'disabled' : ''; ?>>
                <span class="material-symbols-outlined" style="font-size:14px">history</span>
            </button>
        </div>
        <?php endforeach; ?>
        <?php if (empty($insumos) && !isset($erro_carregamento)): ?>
        <div style="text-align:center;padding:40px;color:var(--g-text-3)">
            <span class="material-symbols-outlined" style="font-size:40px">inventory_2</span>
            <div style="margin-top:8px">Nenhum insumo ativo encontrado.</div>
        </div>
        <?php endif; ?>
        </div>

        <div style="margin-top:32px">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:8px">
                <span class="material-symbols-outlined" style="vertical-align:middle;font-size:18px">schedule</span>
                Agendamento Automático (Cron Job — HostGator)
            </h2>
            <p style="font-size:13px;color:var(--g-text-2);margin-bottom:12px">Para rodar automaticamente todo domingo às 02h00:</p>
            <div class="cron-box"><?php
echo "0 2 * * 0   /usr/bin/php /home/luizpi39/public_html/backend/admin/cron-atualizar-precos.php >> /home/luizpi39/logs/precos.log 2>&1";
?></div>
            <p style="font-size:12px;color:var(--g-text-3);margin-top:8px"><strong>cPanel → Cron Jobs → Add New Cron Job</strong></p>
        </div>
    </div>
</main>
</div>

<!-- MODAL: Variação Suspeita -->
<div class="modal-overlay" id="modal-confirmar">
    <div class="modal-box" style="max-width:420px">
        <div class="modal-drag"></div>
        <div class="modal-title" style="color:#d97706">⚠️ Variação Suspeita</div>
        <div id="confirmar-texto" style="font-size:13px;margin:12px 0"></div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="fecharConfirmar()">Cancelar</button>
            <button class="btn btn-primary" style="background:#d97706;border-color:#d97706" onclick="confirmarAtualizacao()">Confirmar mesmo assim</button>
        </div>
    </div>
</div>

<!-- MODAL: Histórico -->
<div class="modal-overlay" id="modal-historico">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-drag"></div>
        <div class="modal-title" id="hist-titulo">Histórico de Preços</div>
        <div id="hist-conteudo"></div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="fecharHistorico()">Fechar</button>
        </div>
    </div>
</div>

<nav class="bottom-nav">
    <a href="dashboard.php"><span class="material-symbols-outlined nav-icon">dashboard</span>Painel</a>
    <a href="insumos.php"><span class="material-symbols-outlined nav-icon">inventory_2</span>Insumos</a>
    <a href="atualizar-precos.php" class="active"><span class="material-symbols-outlined nav-icon">price_check</span>Preços</a>
    <a href="logout.php"><span class="material-symbols-outlined nav-icon">logout</span>Sair</a>
</nav>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
<script>
let statAtualizados = 0, statSemVariacao = 0, statErros = 0;
const ids = [<?php echo implode(',', array_column($insumos, 'id')); ?>];
let _pendingConfirm = null;

async function atualizarInsumo(id, confirmado = false) {
    const card   = document.getElementById('card-' + id);
    const status = document.getElementById('status-' + id);
    const val    = document.getElementById('val-' + id);
    const dbg    = document.getElementById('debug-' + id);
    status.className = 'preco-status buscando'; status.textContent = 'Buscando...';
    try {
        const fd = new FormData();
        fd.append('action', 'atualizar'); fd.append('insumo_id', id);
        if (confirmado) fd.append('confirmado', '1');
        const r    = await fetch('atualizar-precos.php', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.debug) dbg.textContent = data.debug;
        if (!data.ok) {
            status.className = 'preco-status falhou'; status.textContent = 'Erro';
            card.classList.add('erro'); card.title = data.erro; statErros++;
        } else if (data.suspeito) {
            status.className = 'preco-status alerta'; status.textContent = '⚠️ Suspeito';
            card.classList.add('suspeito');
            _pendingConfirm = { id, data };
            const sinal = data.variacao_pct > 0 ? '+' : '';
            document.getElementById('confirmar-texto').innerHTML =
                `<strong>${data.insumo}</strong><br>` +
                `Preço atual: <strong>R$ ${_fmt(data.preco_antes)}</strong><br>` +
                `ML sugere: <strong>R$ ${_fmt(data.preco_novo)}</strong><br>` +
                `Variação: <strong class="${data.variacao_pct>0?'variacao-pos':'variacao-neg'}">${sinal}${data.variacao_pct.toFixed(1)}%</strong><br>` +
                `<span style="font-size:11px;color:#94a3b8">${data.n_resultados} resultados analisados</span>`;
            document.getElementById('modal-confirmar').classList.add('aberto');
            statErros++;
        } else if (data.atualizado) {
            const sinal = data.variacao_pct > 0 ? '+' : '';
            val.textContent = 'R$ ' + _fmt(data.preco_novo);
            status.className = 'preco-status ok'; status.textContent = sinal + data.variacao_pct.toFixed(1) + '%';
            card.classList.add('atualizado'); statAtualizados++;
        } else {
            status.className = 'preco-status unchanged'; status.textContent = '= estável';
            card.classList.add('sem-variacao'); statSemVariacao++;
        }
    } catch(e) {
        status.className = 'preco-status falhou'; status.textContent = 'Erro'; statErros++;
    }
    document.getElementById('stat-atualizados').textContent  = statAtualizados;
    document.getElementById('stat-sem-variacao').textContent = statSemVariacao;
    document.getElementById('stat-erros').textContent        = statErros;
}

async function confirmarAtualizacao() {
    fecharConfirmar();
    if (!_pendingConfirm) return;
    const { id } = _pendingConfirm; _pendingConfirm = null;
    await atualizarInsumo(id, true);
}
function fecharConfirmar() { document.getElementById('modal-confirmar').classList.remove('aberto'); }

async function atualizarTodos() {
    const btn = document.getElementById('btn-atualizar-todos');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">hourglass_empty</span> Atualizando...';
    statAtualizados = 0; statSemVariacao = 0; statErros = 0;
    document.getElementById('progress-wrap').style.display = 'block';
    document.getElementById('prog-total').textContent = ids.length;
    for (let i = 0; i < ids.length; i++) {
        document.getElementById('prog-atual').textContent = i + 1;
        document.getElementById('progress-bar').style.width = ((i+1)/ids.length*100) + '%';
        await atualizarInsumo(ids[i]);
        await _sleep(800);
    }
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">check_circle</span> Concluído!';
    setTimeout(() => { btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">sync</span> Atualizar Todos'; }, 4000);
}

async function verHistorico(id, nome) {
    document.getElementById('hist-titulo').textContent = 'Histórico — ' + nome;
    document.getElementById('hist-conteudo').innerHTML = '<div style="text-align:center;padding:30px;color:var(--g-text-3)">Carregando...</div>';
    document.getElementById('modal-historico').classList.add('aberto');
    const r    = await fetch('atualizar-precos.php?action=historico&id=' + id);
    const rows = await r.json();
    if (!rows.length) {
        document.getElementById('hist-conteudo').innerHTML = '<div style="text-align:center;padding:30px;color:var(--g-text-3)">Nenhum histórico ainda.</div>';
        return;
    }
    let html = '<table class="hist-table"><thead><tr><th>Data</th><th>Antes</th><th>Depois</th><th>Variação</th></tr></thead><tbody>';
    rows.forEach(r => {
        const cls = r.variacao_pct > 0 ? 'variacao-pos' : (r.variacao_pct < 0 ? 'variacao-neg' : '');
        const sinal = r.variacao_pct > 0 ? '+' : '';
        html += `<tr><td>${r.atualizado_em}</td><td>R$ ${_fmt(parseFloat(r.preco_anterior))}</td><td><strong>R$ ${_fmt(parseFloat(r.preco_novo))}</strong></td><td class="${cls}">${sinal}${parseFloat(r.variacao_pct).toFixed(2)}%</td></tr>`;
    });
    html += '</tbody></table>';
    document.getElementById('hist-conteudo').innerHTML = html;
}
function fecharHistorico() { document.getElementById('modal-historico').classList.remove('aberto'); }
document.getElementById('modal-historico').addEventListener('click', function(e) { if (e.target === this) fecharHistorico(); });

function _fmt(v) { return v.toFixed(2).replace('.', ','); }
function _sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
</script>
</body>
</html>
