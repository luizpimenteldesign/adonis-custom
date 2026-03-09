<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

// Cria tabela de histórico se não existir
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

// ── CONFIGURAÇÕES ───────────────────────────────────────────
define('ML_SITE',       'MLB');          // Brasil
define('ML_LIMIT',      5);              // Resultados por busca
define('MIN_VARIACAO',  3.0);            // Só atualiza se variar > 3%
define('ML_TIMEOUT',    8);              // Timeout da requisição (s)

// ── API: executar atualização (AJAX) ────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'atualizar') {
    header('Content-Type: application/json');

    $insumo_id = (int)($_POST['insumo_id'] ?? 0);
    if (!$insumo_id) { echo json_encode(['ok' => false, 'erro' => 'ID inválido']); exit; }

    $insumo = $conn->prepare('SELECT id, nome, valorunitario FROM insumos WHERE id = ? AND ativo = 1');
    $insumo->execute([$insumo_id]);
    $ins = $insumo->fetch(PDO::FETCH_ASSOC);
    if (!$ins) { echo json_encode(['ok' => false, 'erro' => 'Insumo não encontrado']); exit; }

    $query   = trim($_POST['query'] ?? $ins['nome']);
    $preco_atual = (float)$ins['valorunitario'];

    // Busca na API do Mercado Livre
    $url = 'https://api.mercadolibre.com/sites/' . ML_SITE . '/search?q=' . urlencode($query) . '&limit=' . ML_LIMIT;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => ML_TIMEOUT,
        CURLOPT_USERAGENT      => 'AdonisCustom/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$resp) {
        echo json_encode(['ok' => false, 'erro' => 'Falha na API ML: ' . $err]);
        exit;
    }

    $data = json_decode($resp, true);
    if (empty($data['results'])) {
        echo json_encode(['ok' => false, 'erro' => 'Nenhum resultado encontrado para: ' . $query]);
        exit;
    }

    // Coleta preços e calcula mediana
    $precos = array_filter(array_column($data['results'], 'price'), fn($p) => $p > 0);
    if (empty($precos)) {
        echo json_encode(['ok' => false, 'erro' => 'Resultados sem preço válido']);
        exit;
    }
    sort($precos);
    $n      = count($precos);
    $mediana = $n % 2 === 0
        ? ($precos[$n/2 - 1] + $precos[$n/2]) / 2
        : $precos[intval($n/2)];
    $mediana = round($mediana, 2);

    // Verifica variação mínima
    $variacao_pct = $preco_atual > 0
        ? round((($mediana - $preco_atual) / $preco_atual) * 100, 2)
        : 100.0;

    $atualizado = false;
    if (abs($variacao_pct) >= MIN_VARIACAO || $preco_atual == 0) {
        // Atualiza preço no insumo
        $conn->prepare('UPDATE insumos SET valorunitario = ? WHERE id = ?')
             ->execute([$mediana, $insumo_id]);

        // Grava histórico
        $conn->prepare('
            INSERT INTO insumos_precos_historico
                (insumo_id, preco_anterior, preco_novo, variacao_pct, fonte, query_usada)
            VALUES (?, ?, ?, ?, "mercadolivre", ?)
        ')->execute([$insumo_id, $preco_atual, $mediana, $variacao_pct, $query]);

        $atualizado = true;
    }

    echo json_encode([
        'ok'           => true,
        'atualizado'   => $atualizado,
        'insumo'       => $ins['nome'],
        'preco_antes'  => $preco_atual,
        'preco_novo'   => $mediana,
        'variacao_pct' => $variacao_pct,
        'n_resultados' => $n,
        'query'        => $query,
        'msg'          => $atualizado
            ? "Atualizado: R\$ " . number_format($preco_atual,2,',','.') . " → R\$ " . number_format($mediana,2,',','.')
            : "Sem variação relevante ({$variacao_pct}%)",
    ]);
    exit;
}

// ── API: buscar histórico de um insumo ───────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'historico') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
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

// ── CARREGA INSUMOS PARA A PÁGINA ───────────────────────────
$insumos = $conn->query('
    SELECT i.id, i.nome, i.unidade, i.valorunitario,
           MAX(h.atualizado_em) as ultima_atualizacao
    FROM insumos i
    LEFT JOIN insumos_precos_historico h ON h.insumo_id = i.id AND h.fonte = "mercadolivre"
    WHERE i.ativo = 1
    GROUP BY i.id
    ORDER BY i.nome
')->fetchAll(PDO::FETCH_ASSOC);

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
    .preco-card:hover { border-color:var(--color-primary,#7c3aed); }
    .preco-card.atualizado { border-color:#22c55e; background:#f0fdf4; }
    .preco-card.erro       { border-color:#ef4444; background:#fef2f2; }
    .preco-card.sem-variacao { opacity:.7; }
    .preco-nome  { font-size:13px; font-weight:600; }
    .preco-meta  { font-size:11px; color:var(--g-text-3); margin-top:2px; }
    .preco-valor {
        font-size:15px; font-weight:700;
        color:var(--color-primary,#7c3aed);
        white-space:nowrap;
        min-width:90px;
        text-align:right;
    }
    .preco-status {
        font-size:11px; padding:3px 8px; border-radius:12px;
        white-space:nowrap;
        min-width:80px; text-align:center;
    }
    .preco-status.aguardando { background:var(--g-border); color:var(--g-text-2); }
    .preco-status.buscando   { background:#fef9c3; color:#854d0e; }
    .preco-status.ok         { background:#dcfce7; color:#166534; }
    .preco-status.unchanged  { background:#f1f5f9; color:#64748b; }
    .preco-status.falhou     { background:#fee2e2; color:#991b1b; }
    .btn-hist {
        padding:4px 8px; border-radius:6px; border:1px solid var(--g-border);
        background:transparent; cursor:pointer; font-size:12px;
        color:var(--g-text-2); display:flex; align-items:center; gap:3px;
    }
    .btn-hist:hover { background:var(--g-hover); }
    .progress-bar-wrap {
        height:6px; background:var(--g-border);
        border-radius:4px; overflow:hidden; margin:8px 0;
    }
    .progress-bar {
        height:100%; background:var(--color-primary,#7c3aed);
        border-radius:4px; transition:width .4s;
        width:0%;
    }
    .stats-row {
        display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px;
    }
    .stat-box {
        flex:1; min-width:120px; padding:14px 16px;
        background:var(--g-surface); border:1px solid var(--g-border);
        border-radius:10px; text-align:center;
    }
    .stat-box .val { font-size:22px; font-weight:700; color:var(--color-primary,#7c3aed); }
    .stat-box .lbl { font-size:11px; color:var(--g-text-3); margin-top:2px; }
    .hist-table { width:100%; border-collapse:collapse; font-size:12px; }
    .hist-table th { text-align:left; padding:6px 8px; border-bottom:2px solid var(--g-border); color:var(--g-text-2); }
    .hist-table td { padding:6px 8px; border-bottom:1px solid var(--g-border); }
    .variacao-pos { color:#16a34a; font-weight:600; }
    .variacao-neg { color:#dc2626; font-weight:600; }
    .cron-box {
        background:#1e1e2e; color:#cdd6f4; font-family:monospace;
        padding:16px; border-radius:8px; font-size:13px;
        white-space:pre; overflow-x:auto;
    }
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
            <button class="btn btn-primary" id="btn-atualizar-todos" onclick="atualizarTodos()">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">sync</span> Atualizar Todos
            </button>
        </div>

        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="val" id="stat-total"><?php echo count($insumos); ?></div>
                <div class="lbl">Total de Insumos</div>
            </div>
            <div class="stat-box">
                <div class="val" id="stat-atualizados">0</div>
                <div class="lbl">Atualizados agora</div>
            </div>
            <div class="stat-box">
                <div class="val" id="stat-sem-variacao">0</div>
                <div class="lbl">Sem variação</div>
            </div>
            <div class="stat-box">
                <div class="val" id="stat-erros">0</div>
                <div class="lbl">Erros</div>
            </div>
        </div>

        <!-- Barra de progresso -->
        <div id="progress-wrap" style="display:none">
            <div style="font-size:12px;color:var(--g-text-2);margin-bottom:4px">
                Processando <span id="prog-atual">0</span> de <span id="prog-total">0</span> insumos...
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar" id="progress-bar"></div>
            </div>
        </div>

        <!-- Lista de insumos -->
        <div id="lista-insumos">
        <?php foreach ($insumos as $ins): ?>
        <?php
            $ultima = $ins['ultima_atualizacao'] ? date('d/m/Y H:i', strtotime($ins['ultima_atualizacao'])) : null;
        ?>
        <div class="preco-card" id="card-<?php echo $ins['id']; ?>" data-id="<?php echo $ins['id']; ?>">
            <div>
                <div class="preco-nome"><?php echo htmlspecialchars($ins['nome']); ?></div>
                <div class="preco-meta">
                    <?php echo htmlspecialchars($ins['unidade']); ?>
                    <?php if ($ultima): ?> • <span style="color:var(--g-blue)">Última atualização ML: <?php echo $ultima; ?></span><?php endif; ?>
                </div>
            </div>
            <div class="preco-valor" id="val-<?php echo $ins['id']; ?>">
                R$ <?php echo number_format((float)$ins['valorunitario'], 2, ',', '.'); ?>
            </div>
            <span class="preco-status aguardando" id="status-<?php echo $ins['id']; ?>">Aguardando</span>
            <button class="btn-hist" onclick="verHistorico(<?php echo $ins['id']; ?>, '<?php echo htmlspecialchars(addslashes($ins['nome'])); ?>')"
                title="Ver histórico de preços">
                <span class="material-symbols-outlined" style="font-size:14px">history</span>
            </button>
        </div>
        <?php endforeach; ?>
        </div>

        <!-- CRON JOB -->
        <div style="margin-top:32px">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:8px">
                <span class="material-symbols-outlined" style="vertical-align:middle;font-size:18px">schedule</span>
                Agendamento Automático (Cron Job — HostGator)
            </h2>
            <p style="font-size:13px;color:var(--g-text-2);margin-bottom:12px">
                Para rodar automaticamente todo domingo às 02h00, adicione este Cron Job no cPanel:
            </p>
            <div class="cron-box"><?php
$dominio = $_SERVER['HTTP_HOST'] ?? 'seudominio.com.br';
echo "# Toda domingo às 02:00\n";
echo "0 2 * * 0   /usr/bin/php /home/luizpi39/public_html/backend/admin/cron-atualizar-precos.php >> /home/luizpi39/logs/precos.log 2>&1";
?></div>
            <p style="font-size:12px;color:var(--g-text-3);margin-top:8px">
                <strong>cPanel → Cron Jobs → Add New Cron Job</strong> — cole a linha acima.
            </p>
        </div>
    </div>
</main>
</div>

<!-- MODAL HISTÓRICO -->
<div class="modal-overlay" id="modal-historico">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-drag"></div>
        <div class="modal-title" id="hist-titulo">Histórico de Preços</div>
        <div id="hist-conteudo"><div style="text-align:center;padding:30px;color:var(--g-text-3)">Carregando...</div></div>
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
let statAtualizados = 0;
let statSemVariacao = 0;
let statErros       = 0;
const total         = <?php echo count($insumos); ?>;
const ids           = [<?php echo implode(',', array_column($insumos, 'id')); ?>];

async function atualizarInsumo(id) {
    const card   = document.getElementById('card-' + id);
    const status = document.getElementById('status-' + id);
    const val    = document.getElementById('val-' + id);

    status.className = 'preco-status buscando';
    status.textContent = 'Buscando...';

    try {
        const fd = new FormData();
        fd.append('action',    'atualizar');
        fd.append('insumo_id', id);

        const r    = await fetch('atualizar-precos.php', { method: 'POST', body: fd });
        const data = await r.json();

        if (!data.ok) {
            status.className = 'preco-status falhou';
            status.textContent = 'Erro';
            card.classList.add('erro');
            card.title = data.erro;
            statErros++;
            return;
        }

        if (data.atualizado) {
            const sinal = data.variacao_pct > 0 ? '+' : '';
            val.textContent = 'R$ ' + _fmt(data.preco_novo);
            status.className = 'preco-status ok';
            status.textContent = sinal + data.variacao_pct.toFixed(1) + '%';
            card.classList.add('atualizado');
            statAtualizados++;
        } else {
            status.className = 'preco-status unchanged';
            status.textContent = '= estável';
            card.classList.add('sem-variacao');
            statSemVariacao++;
        }
    } catch (e) {
        status.className = 'preco-status falhou';
        status.textContent = 'Erro';
        statErros++;
    }

    document.getElementById('stat-atualizados').textContent  = statAtualizados;
    document.getElementById('stat-sem-variacao').textContent = statSemVariacao;
    document.getElementById('stat-erros').textContent        = statErros;
}

async function atualizarTodos() {
    const btn = document.getElementById('btn-atualizar-todos');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">hourglass_empty</span> Atualizando...';

    statAtualizados = 0; statSemVariacao = 0; statErros = 0;
    document.getElementById('progress-wrap').style.display = 'block';
    document.getElementById('prog-total').textContent = ids.length;

    for (let i = 0; i < ids.length; i++) {
        document.getElementById('prog-atual').textContent = i + 1;
        document.getElementById('progress-bar').style.width = ((i + 1) / ids.length * 100) + '%';
        await atualizarInsumo(ids[i]);
        await _sleep(600); // delay entre requisições
    }

    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">check_circle</span> Concluído!';
    setTimeout(() => {
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">sync</span> Atualizar Todos';
    }, 4000);
}

async function verHistorico(id, nome) {
    document.getElementById('hist-titulo').textContent = 'Histórico — ' + nome;
    document.getElementById('hist-conteudo').innerHTML = '<div style="text-align:center;padding:30px;color:var(--g-text-3)">Carregando...</div>';
    document.getElementById('modal-historico').classList.add('aberto');

    const r    = await fetch('atualizar-precos.php?action=historico&id=' + id);
    const rows = await r.json();

    if (!rows.length) {
        document.getElementById('hist-conteudo').innerHTML =
            '<div style="text-align:center;padding:30px;color:var(--g-text-3)">Nenhum histórico ainda.<br>Execute uma atualização primeiro.</div>';
        return;
    }

    let html = '<table class="hist-table"><thead><tr>';
    html += '<th>Data</th><th>Antes</th><th>Depois</th><th>Variação</th><th>Fonte</th>';
    html += '</tr></thead><tbody>';
    rows.forEach(r => {
        const cls = r.variacao_pct > 0 ? 'variacao-pos' : (r.variacao_pct < 0 ? 'variacao-neg' : '');
        const sinal = r.variacao_pct > 0 ? '+' : '';
        html += `<tr>
            <td>${r.atualizado_em}</td>
            <td>R$ ${_fmt(parseFloat(r.preco_anterior))}</td>
            <td><strong>R$ ${_fmt(parseFloat(r.preco_novo))}</strong></td>
            <td class="${cls}">${sinal}${parseFloat(r.variacao_pct).toFixed(2)}%</td>
            <td>${r.fonte}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    document.getElementById('hist-conteudo').innerHTML = html;
}

function fecharHistorico() {
    document.getElementById('modal-historico').classList.remove('aberto');
}
document.getElementById('modal-historico').addEventListener('click', function(e) {
    if (e.target === this) fecharHistorico();
});

function _fmt(v) { return v.toFixed(2).replace('.', ','); }
function _sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
</script>
</body>
</html>
