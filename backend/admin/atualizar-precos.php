<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

// Garante coluna query_ml na tabela insumos
try {
    $conn->query('SELECT query_ml FROM insumos LIMIT 1');
} catch (Exception $e) {
    try {
        $conn->exec('ALTER TABLE insumos ADD COLUMN query_ml VARCHAR(200) NULL DEFAULT NULL');
    } catch (Exception $e2) { /* sem permissão ou já existe */ }
}

// Cria tabela de histórico se não existir
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

// ── CONFIGURAÇÕES ───────────────────────────────────────────
define('ML_SITE',        'MLB');
define('ML_LIMIT',       20);
define('MIN_VARIACAO',   3.0);
define('MAX_VARIACAO',   50.0);  // acima disso bloqueia atualização automática
define('ML_TIMEOUT',     8);

// Verifica se tabela de histórico existe
try {
    $conn->query('SELECT 1 FROM insumos_precos_historico LIMIT 1');
    $historico_existe = true;
} catch (Exception $e) {
    $historico_existe = false;
}

// ── HELPER: mediana com filtro IQR ───────────────────────────
function medianaIQR(array $precos): ?float {
    if (empty($precos)) return null;
    sort($precos);
    $n  = count($precos);
    $q1 = $precos[intval($n * 0.25)];
    $q3 = $precos[intval($n * 0.75)];
    $iqr = $q3 - $q1;
    $filtrados = array_values(array_filter($precos, fn($p) => $p >= ($q1 - 1.5 * $iqr) && $p <= ($q3 + 1.5 * $iqr)));
    if (empty($filtrados)) $filtrados = $precos;
    $nf = count($filtrados);
    return round($nf % 2 === 0
        ? ($filtrados[$nf/2 - 1] + $filtrados[$nf/2]) / 2
        : $filtrados[intval($nf/2)], 2);
}

// ── API: salvar query_ml de um insumo ───────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'salvar_query') {
    header('Content-Type: application/json');
    $id    = (int)($_POST['insumo_id'] ?? 0);
    $query = trim($_POST['query_ml'] ?? '');
    if (!$id) { echo json_encode(['ok' => false, 'erro' => 'ID inválido']); exit; }
    try {
        $conn->prepare('UPDATE insumos SET query_ml = ? WHERE id = ?')
             ->execute([$query ?: null, $id]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ── API: executar atualização (AJAX) ────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'atualizar') {
    header('Content-Type: application/json');

    if (!$historico_existe) {
        echo json_encode(['ok' => false, 'erro' => 'Tabela de histórico não encontrada. Crie manualmente via phpMyAdmin.']);
        exit;
    }

    $insumo_id = (int)($_POST['insumo_id'] ?? 0);
    if (!$insumo_id) { echo json_encode(['ok' => false, 'erro' => 'ID inválido']); exit; }

    try {
        $stmt = $conn->prepare('SELECT id, nome, valorunitario, query_ml FROM insumos WHERE id = ? AND ativo = 1');
        $stmt->execute([$insumo_id]);
        $ins = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => 'Erro ao buscar insumo: ' . $e->getMessage()]);
        exit;
    }

    if (!$ins) { echo json_encode(['ok' => false, 'erro' => 'Insumo não encontrado']); exit; }

    // Usa query_ml salva, ou o que veio no POST, ou o nome do insumo como último recurso
    $query = trim(
        $_POST['query'] ?? ''
        ?: $ins['query_ml'] ?? ''
        ?: $ins['nome']
    );

    if (empty($query)) {
        echo json_encode(['ok' => false, 'erro' => 'Nenhuma query definida para este insumo']);
        exit;
    }

    $preco_atual = (float)$ins['valorunitario'];

    // Busca na API ML
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
        echo json_encode(['ok' => false, 'erro' => 'Nenhum resultado para: ' . $query]);
        exit;
    }

    $precos = array_filter(array_column($data['results'], 'price'), fn($p) => $p > 0);
    if (empty($precos)) {
        echo json_encode(['ok' => false, 'erro' => 'Resultados sem preço válido']);
        exit;
    }

    $mediana = medianaIQR(array_values($precos));
    $n       = count($precos);

    $variacao_pct = $preco_atual > 0
        ? round((($mediana - $preco_atual) / $preco_atual) * 100, 2)
        : 100.0;

    // Verifica se variação é suspeita (acima do limite)
    $suspeito = abs($variacao_pct) > MAX_VARIACAO && $preco_atual > 0;

    // Se veio confirmado pelo modal, força atualização mesmo se suspeito
    $confirmado = isset($_POST['confirmado']) && $_POST['confirmado'] === '1';

    $atualizado = false;

    if (!$suspeito || $confirmado) {
        if (abs($variacao_pct) >= MIN_VARIACAO || $preco_atual == 0) {
            try {
                $conn->prepare('UPDATE insumos SET valorunitario = ? WHERE id = ?')
                     ->execute([$mediana, $insumo_id]);
                $conn->prepare('
                    INSERT INTO insumos_precos_historico
                        (insumo_id, preco_anterior, preco_novo, variacao_pct, fonte, query_usada)
                    VALUES (?, ?, ?, ?, "mercadolivre", ?)
                ')->execute([$insumo_id, $preco_atual, $mediana, $variacao_pct, $query]);
                $atualizado = true;
            } catch (Exception $e) {
                echo json_encode(['ok' => false, 'erro' => 'Erro ao salvar: ' . $e->getMessage()]);
                exit;
            }
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
                ? "Atualizado: R\$ " . number_format($preco_atual,2,',','.') . " → R\$ " . number_format($mediana,2,',','.')
                : "Sem variação relevante ({$variacao_pct}%)"),
    ]);
    exit;
}

// ── API: buscar histórico ────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'historico') {
    header('Content-Type: application/json');
    if (!$historico_existe) { echo json_encode([]); exit; }
    $id = (int)($_GET['id'] ?? 0);
    try {
        $rows = $conn->prepare('
            SELECT preco_anterior, preco_novo, variacao_pct, fonte, query_usada, atualizado_em
            FROM insumos_precos_historico
            WHERE insumo_id = ?
            ORDER BY atualizado_em DESC
            LIMIT 20
        ');
        $rows->execute([$id]);
        echo json_encode($rows->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// ── CARREGA INSUMOS ──────────────────────────────────────────
try {
    if ($historico_existe) {
        $insumos = $conn->query('
            SELECT i.id, i.nome, i.unidade, i.valorunitario, i.query_ml,
                   MAX(h.atualizado_em) as ultima_atualizacao
            FROM insumos i
            LEFT JOIN insumos_precos_historico h ON h.insumo_id = i.id AND h.fonte = "mercadolivre"
            WHERE i.ativo = 1
            GROUP BY i.id
            ORDER BY i.nome
        ')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $insumos = $conn->query('
            SELECT id, nome, unidade, valorunitario, query_ml, NULL as ultima_atualizacao
            FROM insumos
            WHERE ativo = 1
            ORDER BY nome
        ')->fetchAll(PDO::FETCH_ASSOC);
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
        grid-template-columns: 1fr auto auto auto auto;
        align-items:center;
        gap:10px;
        padding:12px 16px;
        border:1px solid var(--g-border);
        border-radius:10px;
        background:var(--g-surface);
        margin-bottom:8px;
        transition:border-color .2s;
    }
    .preco-card:hover { border-color:var(--color-primary,#7c3aed); }
    .preco-card.atualizado  { border-color:#22c55e; background:#f0fdf4; }
    .preco-card.erro        { border-color:#ef4444; background:#fef2f2; }
    .preco-card.suspeito    { border-color:#f59e0b; background:#fffbeb; }
    .preco-card.sem-variacao { opacity:.7; }
    .preco-card.sem-query   { opacity:.55; }
    .preco-nome  { font-size:13px; font-weight:600; }
    .preco-meta  { font-size:11px; color:var(--g-text-3); margin-top:2px; }
    .preco-query {
        font-size:10px; margin-top:3px;
        color:var(--color-primary,#7c3aed);
        font-style:italic;
    }
    .preco-sem-query {
        font-size:10px; margin-top:3px;
        color:#94a3b8;
        font-style:italic;
    }
    .preco-valor {
        font-size:15px; font-weight:700;
        color:var(--color-primary,#7c3aed);
        white-space:nowrap;
        min-width:90px;
        text-align:right;
    }
    .preco-status {
        font-size:11px; padding:3px 8px; border-radius:12px;
        white-space:nowrap; min-width:80px; text-align:center;
    }
    .preco-status.aguardando { background:var(--g-border); color:var(--g-text-2); }
    .preco-status.nao-rastreado { background:#f1f5f9; color:#94a3b8; }
    .preco-status.buscando   { background:#fef9c3; color:#854d0e; }
    .preco-status.ok         { background:#dcfce7; color:#166534; }
    .preco-status.unchanged  { background:#f1f5f9; color:#64748b; }
    .preco-status.falhou     { background:#fee2e2; color:#991b1b; }
    .preco-status.alerta     { background:#fef3c7; color:#92400e; }
    .btn-icon {
        padding:4px 8px; border-radius:6px; border:1px solid var(--g-border);
        background:transparent; cursor:pointer; font-size:12px;
        color:var(--g-text-2); display:flex; align-items:center; gap:3px;
    }
    .btn-icon:hover { background:var(--g-hover); }
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
    /* Modal query */
    .modal-query-form { display:flex; gap:8px; margin-top:12px; }
    .modal-query-form input { flex:1; padding:8px 10px; border:1px solid var(--g-border); border-radius:6px; font-size:13px; }
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
            <button class="btn btn-primary" id="btn-atualizar-todos" onclick="atualizarTodos()" <?php echo !$historico_existe ? 'disabled title="Crie a tabela de histórico primeiro"' : ''; ?>>
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">sync</span> Atualizar Rastreados
            </button>
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `insumos` ADD COLUMN IF NOT EXISTS `query_ml` VARCHAR(200) NULL DEFAULT NULL;</pre>
        </div>
        <?php endif; ?>

        <?php if (isset($erro_carregamento)): ?>
        <div class="aviso-tabela"><strong>Erro ao carregar insumos:</strong> <?php echo htmlspecialchars($erro_carregamento); ?></div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-box"><div class="val" id="stat-total"><?php echo count($insumos); ?></div><div class="lbl">Total de Insumos</div></div>
            <div class="stat-box"><div class="val" id="stat-rastreados"><?php echo count(array_filter($insumos, fn($i) => !empty($i['query_ml']))); ?></div><div class="lbl">Com query ML</div></div>
            <div class="stat-box"><div class="val" id="stat-atualizados">0</div><div class="lbl">Atualizados agora</div></div>
            <div class="stat-box"><div class="val" id="stat-erros">0</div><div class="lbl">Erros / Suspeitos</div></div>
        </div>

        <!-- Barra de progresso -->
        <div id="progress-wrap" style="display:none">
            <div style="font-size:12px;color:var(--g-text-2);margin-bottom:4px">
                Processando <span id="prog-atual">0</span> de <span id="prog-total">0</span> insumos rastreados...
            </div>
            <div class="progress-bar-wrap"><div class="progress-bar" id="progress-bar"></div></div>
        </div>

        <!-- Lista de insumos -->
        <div id="lista-insumos">
        <?php foreach ($insumos as $ins):
            $tem_query = !empty($ins['query_ml']);
            $ultima    = !empty($ins['ultima_atualizacao']) ? date('d/m/Y H:i', strtotime($ins['ultima_atualizacao'])) : null;
        ?>
        <div class="preco-card <?php echo $tem_query ? '' : 'sem-query'; ?>" id="card-<?php echo $ins['id']; ?>" data-id="<?php echo $ins['id']; ?>" data-tem-query="<?php echo $tem_query ? '1' : '0'; ?>">
            <div>
                <div class="preco-nome"><?php echo htmlspecialchars($ins['nome']); ?></div>
                <div class="preco-meta">
                    <?php echo htmlspecialchars($ins['unidade']); ?>
                    <?php if ($ultima): ?> • <span style="color:var(--g-blue)">Última ML: <?php echo $ultima; ?></span><?php endif; ?>
                </div>
                <?php if ($tem_query): ?>
                <div class="preco-query" id="qshow-<?php echo $ins['id']; ?>">🔍 <?php echo htmlspecialchars($ins['query_ml']); ?></div>
                <?php else: ?>
                <div class="preco-sem-query" id="qshow-<?php echo $ins['id']; ?>">Sem query — clique ✏️ para definir</div>
                <?php endif; ?>
            </div>
            <div class="preco-valor" id="val-<?php echo $ins['id']; ?>">
                R$ <?php echo number_format((float)$ins['valorunitario'], 2, ',', '.'); ?>
            </div>
            <span class="preco-status <?php echo $tem_query ? 'aguardando' : 'nao-rastreado'; ?>" id="status-<?php echo $ins['id']; ?>">
                <?php echo $tem_query ? 'Aguardando' : 'Não rastr.'; ?>
            </span>
            <button class="btn-icon" onclick="editarQuery(<?php echo $ins['id']; ?>, '<?php echo htmlspecialchars(addslashes($ins['query_ml'] ?? '')); ?>')"
                title="Editar query de busca">
                <span class="material-symbols-outlined" style="font-size:14px">edit</span>
            </button>
            <button class="btn-icon" onclick="verHistorico(<?php echo $ins['id']; ?>, '<?php echo htmlspecialchars(addslashes($ins['nome'])); ?>')"
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

        <!-- CRON JOB -->
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

<!-- MODAL: Editar Query -->
<div class="modal-overlay" id="modal-query">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-drag"></div>
        <div class="modal-title">Editar Query de Busca</div>
        <div style="font-size:13px;color:var(--g-text-2);margin-bottom:4px">
            Termo usado para buscar este insumo no Mercado Livre.
            Deixe vazio para desativar o rastreamento.
        </div>
        <div class="modal-query-form">
            <input type="text" id="input-query" placeholder="ex: titebond original madeira 473ml" />
            <button class="btn btn-primary" onclick="salvarQuery()">Salvar</button>
        </div>
        <div style="margin-top:8px;font-size:11px;color:var(--g-text-3)">
            Dica: use termos em português ou inglês que os vendedores do ML usam, com marca/modelo quando possível.
        </div>
        <div class="modal-actions" style="margin-top:16px">
            <button class="btn btn-secondary" onclick="fecharModalQuery()">Cancelar</button>
        </div>
    </div>
</div>

<!-- MODAL: Confirmar variação suspeita -->
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
let statErros       = 0;

// Apenas IDs de insumos COM query_ml
const ids = [<?php
    echo implode(',', array_map(fn($i) => $i['id'], array_filter($insumos, fn($i) => !empty($i['query_ml']))));
?>];

// Estado para modal de confirmação
let _pendingConfirm = null;

async function atualizarInsumo(id, confirmado = false) {
    const card   = document.getElementById('card-' + id);
    const status = document.getElementById('status-' + id);
    const val    = document.getElementById('val-' + id);
    status.className   = 'preco-status buscando';
    status.textContent = 'Buscando...';
    try {
        const fd = new FormData();
        fd.append('action',    'atualizar');
        fd.append('insumo_id', id);
        if (confirmado) fd.append('confirmado', '1');
        const r    = await fetch('atualizar-precos.php', { method: 'POST', body: fd });
        const data = await r.json();
        if (!data.ok) {
            status.className   = 'preco-status falhou';
            status.textContent = 'Erro';
            card.classList.add('erro');
            card.title = data.erro;
            statErros++;
        } else if (data.suspeito) {
            // Variação acima de 50% — pede confirmação
            status.className   = 'preco-status alerta';
            status.textContent = '⚠️ Suspeito';
            card.classList.add('suspeito');
            _pendingConfirm = { id, data };
            const sinal = data.variacao_pct > 0 ? '+' : '';
            document.getElementById('confirmar-texto').innerHTML =
                `<strong>${data.insumo}</strong><br>` +
                `Preço atual: <strong>R$ ${_fmt(data.preco_antes)}</strong><br>` +
                `ML sugere: <strong>R$ ${_fmt(data.preco_novo)}</strong><br>` +
                `Variação: <strong class="${data.variacao_pct > 0 ? 'variacao-pos' : 'variacao-neg'}">${sinal}${data.variacao_pct.toFixed(1)}%</strong><br>` +
                `<span style="font-size:11px;color:#94a3b8">${data.n_resultados} resultados • query: "${data.query}"</span>`;
            document.getElementById('modal-confirmar').classList.add('aberto');
            statErros++;
        } else if (data.atualizado) {
            const sinal = data.variacao_pct > 0 ? '+' : '';
            val.textContent    = 'R$ ' + _fmt(data.preco_novo);
            status.className   = 'preco-status ok';
            status.textContent = sinal + data.variacao_pct.toFixed(1) + '%';
            card.classList.remove('suspeito');
            card.classList.add('atualizado');
            statAtualizados++;
        } else {
            status.className   = 'preco-status unchanged';
            status.textContent = '= estável';
            card.classList.add('sem-variacao');
        }
    } catch (e) {
        status.className   = 'preco-status falhou';
        status.textContent = 'Erro';
        statErros++;
    }
    document.getElementById('stat-atualizados').textContent = statAtualizados;
    document.getElementById('stat-erros').textContent       = statErros;
}

async function confirmarAtualizacao() {
    fecharConfirmar();
    if (!_pendingConfirm) return;
    const { id } = _pendingConfirm;
    _pendingConfirm = null;
    await atualizarInsumo(id, true);
}

function fecharConfirmar() {
    document.getElementById('modal-confirmar').classList.remove('aberto');
}

async function atualizarTodos() {
    if (!ids.length) { alert('Nenhum insumo com query definida. Use o botão ✏️ para configurar.'); return; }
    const btn = document.getElementById('btn-atualizar-todos');
    btn.disabled  = true;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">hourglass_empty</span> Atualizando...';
    statAtualizados = 0; statErros = 0;
    document.getElementById('progress-wrap').style.display = 'block';
    document.getElementById('prog-total').textContent = ids.length;
    for (let i = 0; i < ids.length; i++) {
        document.getElementById('prog-atual').textContent = i + 1;
        document.getElementById('progress-bar').style.width = ((i + 1) / ids.length * 100) + '%';
        await atualizarInsumo(ids[i]);
        await _sleep(700);
    }
    btn.disabled  = false;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">check_circle</span> Concluído!';
    setTimeout(() => {
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">sync</span> Atualizar Rastreados';
    }, 4000);
}

// ── Query ML ────────────────────────────────────────────────
let _editandoId = null;
function editarQuery(id, queryAtual) {
    _editandoId = id;
    document.getElementById('input-query').value = queryAtual || '';
    document.getElementById('modal-query').classList.add('aberto');
    setTimeout(() => document.getElementById('input-query').focus(), 100);
}
async function salvarQuery() {
    const query = document.getElementById('input-query').value.trim();
    const fd = new FormData();
    fd.append('action',    'salvar_query');
    fd.append('insumo_id', _editandoId);
    fd.append('query_ml',  query);
    const r    = await fetch('atualizar-precos.php', { method: 'POST', body: fd });
    const data = await r.json();
    if (data.ok) {
        const qshow = document.getElementById('qshow-' + _editandoId);
        const card  = document.getElementById('card-' + _editandoId);
        if (query) {
            qshow.className   = 'preco-query';
            qshow.textContent = '🔍 ' + query;
            card.classList.remove('sem-query');
            card.dataset.temQuery = '1';
            // Atualiza status
            const status = document.getElementById('status-' + _editandoId);
            if (status.textContent === 'Não rastr.') {
                status.className   = 'preco-status aguardando';
                status.textContent = 'Aguardando';
            }
        } else {
            qshow.className   = 'preco-sem-query';
            qshow.textContent = 'Sem query — clique ✏️ para definir';
            card.classList.add('sem-query');
            card.dataset.temQuery = '0';
        }
    }
    fecharModalQuery();
}
function fecharModalQuery() { document.getElementById('modal-query').classList.remove('aberto'); }
document.getElementById('modal-query').addEventListener('click', function(e) { if (e.target === this) fecharModalQuery(); });
document.getElementById('input-query').addEventListener('keydown', e => { if (e.key === 'Enter') salvarQuery(); });

// ── Histórico ───────────────────────────────────────────────
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
    let html = '<table class="hist-table"><thead><tr><th>Data</th><th>Antes</th><th>Depois</th><th>Variação</th><th>Query</th></tr></thead><tbody>';
    rows.forEach(r => {
        const cls   = r.variacao_pct > 0 ? 'variacao-pos' : (r.variacao_pct < 0 ? 'variacao-neg' : '');
        const sinal = r.variacao_pct > 0 ? '+' : '';
        html += `<tr><td>${r.atualizado_em}</td><td>R$ ${_fmt(parseFloat(r.preco_anterior))}</td><td><strong>R$ ${_fmt(parseFloat(r.preco_novo))}</strong></td><td class="${cls}">${sinal}${parseFloat(r.variacao_pct).toFixed(2)}%</td><td style="font-size:10px;color:#94a3b8">${r.query_usada || '—'}</td></tr>`;
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
