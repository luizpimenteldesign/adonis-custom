<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

try { $conn->query("ALTER TABLE servicos ADD COLUMN prazo_padrao_dias INT DEFAULT NULL"); } catch (Exception $e) {}

// ─── API: categorias ────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'categorias') {
    header('Content-Type: application/json');
    $cats = $conn->query("SELECT id, nome FROM categorias_servico WHERE ativo=1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($cats);
    exit;
}

// ─── API: criar categoria ───────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'criar_categoria') {
    header('Content-Type: application/json');
    $nome = trim($_POST['nome'] ?? '');
    if (!$nome) { echo json_encode(['sucesso' => false, 'erro' => 'Nome é obrigatório']); exit; }
    try {
        $conn->prepare('INSERT INTO categorias_servico (nome, ativo) VALUES (?, 1)')->execute([$nome]);
        $newId = (int)$conn->lastInsertId();
        echo json_encode(['sucesso' => true, 'id' => $newId, 'nome' => $nome]);
    } catch (Exception $e) {
        echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar: ' . $e->getMessage()]);
    }
    exit;
}

// ─── API: buscar insumos (nomes de colunas corretos) ────────
if (isset($_GET['action']) && $_GET['action'] === 'buscar_insumos') {
    header('Content-Type: application/json');
    $busca = trim($_GET['q'] ?? '');
    $sql = "SELECT id, nome, unidade, valorunitario, categoria, tipo_insumo FROM insumos WHERE ativo=1";
    if ($busca) $sql .= " AND (nome LIKE :q OR categoria LIKE :q)";
    $sql .= " ORDER BY categoria, nome LIMIT 60";
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q' => '%'.$busca.'%']);
    else $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ─── API: insumos vinculados a um serviço ───────────────────
if (isset($_GET['action']) && $_GET['action'] === 'insumos_servico') {
    header('Content-Type: application/json');
    $servicoId = (int)($_GET['servico_id'] ?? 0);
    if (!$servicoId) { echo json_encode([]); exit; }
    $stmt = $conn->prepare("
        SELECT si.insumo_id, si.quantidade_padrao, si.tipo_vinculo,
               i.nome, i.unidade, i.valorunitario, i.tipo_insumo, i.categoria
        FROM servicos_insumos si
        JOIN insumos i ON si.insumo_id = i.id
        WHERE si.servico_id = ?
        ORDER BY si.tipo_vinculo DESC, i.nome
    ");
    $stmt->execute([$servicoId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ─── API: dados completos de um serviço (para edição) ───────
if (isset($_GET['action']) && $_GET['action'] === 'get_servico') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false]); exit; }
    $stmt = $conn->prepare('SELECT * FROM servicos WHERE id=?');
    $stmt->execute([$id]);
    $serv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$serv) { echo json_encode(['ok' => false]); exit; }
    // Categorias
    $stmtCat = $conn->prepare('SELECT categoria_id FROM servico_categorias WHERE servico_id=?');
    $stmtCat->execute([$id]);
    $serv['cat_ids'] = $stmtCat->fetchAll(PDO::FETCH_COLUMN);
    // Insumos
    $stmtIns = $conn->prepare("
        SELECT si.insumo_id, si.quantidade_padrao, si.tipo_vinculo,
               i.nome, i.unidade, i.valorunitario, i.tipo_insumo, i.categoria
        FROM servicos_insumos si
        JOIN insumos i ON si.insumo_id = i.id
        WHERE si.servico_id = ?
        ORDER BY si.tipo_vinculo DESC, i.nome
    ");
    $stmtIns->execute([$id]);
    $serv['insumos'] = $stmtIns->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'servico' => $serv]);
    exit;
}

// ─── POST: criar / editar / excluir ─────────────────────────
$busca = trim($_GET['q'] ?? '');
$msg   = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar' || $acao === 'editar') {
        $nome      = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $valor     = str_replace(',', '.', trim($_POST['valor_base'] ?? '0'));
        $prazo     = ($_POST['prazo_padrao_dias'] ?? '') !== '' ? (int)$_POST['prazo_padrao_dias'] : null;
        $ativo     = isset($_POST['ativo']) ? 1 : 0;
        $cats      = array_map('intval', $_POST['categorias'] ?? []);
        $insumos   = json_decode($_POST['insumos_json'] ?? '[]', true) ?: [];

        if (!$nome) { header('Location: servicos.php?msg=erro:Nome obrigatório'); exit; }

        if ($acao === 'criar') {
            $conn->prepare('INSERT INTO servicos (nome, descricao, valor_base, prazo_padrao_dias, ativo) VALUES (?,?,?,?,?)')
                 ->execute([$nome, $descricao, $valor, $prazo, $ativo]);
            $sid = (int)$conn->lastInsertId();
        } else {
            $sid = (int)$_POST['id'];
            $conn->prepare('UPDATE servicos SET nome=?, descricao=?, valor_base=?, prazo_padrao_dias=?, ativo=? WHERE id=?')
                 ->execute([$nome, $descricao, $valor, $prazo, $ativo, $sid]);
        }

        // Categorias
        $conn->prepare('DELETE FROM servico_categorias WHERE servico_id=?')->execute([$sid]);
        foreach ($cats as $cid) {
            if ($cid > 0) $conn->prepare('INSERT IGNORE INTO servico_categorias (servico_id, categoria_id) VALUES (?,?)')->execute([$sid, $cid]);
        }

        // Insumos com tipo_vinculo e quantidade_padrao
        $conn->prepare('DELETE FROM servicos_insumos WHERE servico_id=?')->execute([$sid]);
        foreach ($insumos as $ins) {
            $iid  = (int)($ins['id'] ?? 0);
            $qtd  = (float)($ins['quantidade'] ?? 1);
            $tipo = in_array($ins['tipo_vinculo'] ?? '', ['fixo','variavel']) ? $ins['tipo_vinculo'] : 'variavel';
            if ($iid > 0 && $qtd > 0) {
                $conn->prepare('INSERT INTO servicos_insumos (servico_id, insumo_id, quantidade_padrao, tipo_vinculo) VALUES (?,?,?,?)')
                     ->execute([$sid, $iid, $qtd, $tipo]);
            }
        }

        $label = $acao === 'criar' ? 'criado' : 'atualizado';
        header("Location: servicos.php?msg=sucesso:Serviço {$label}!"); exit;
    }

    if ($acao === 'excluir') {
        $id = (int)$_POST['id'];
        try {
            $conn->prepare('DELETE FROM servicos WHERE id=?')->execute([$id]);
            header('Location: servicos.php?msg=sucesso:Serviço removido.'); exit;
        } catch (Exception $e) {
            header('Location: servicos.php?msg=erro:Não é possível excluir — serviço usado em pedidos.'); exit;
        }
    }
    header('Location: servicos.php'); exit;
}

// ─── Listagem ────────────────────────────────────────────────
try {
    $sql = 'SELECT s.*, COUNT(DISTINCT ps.id) as total_uso FROM servicos s LEFT JOIN pre_os_servicos ps ON ps.servico_id = s.id'
         . ($busca ? ' WHERE s.nome LIKE :q OR s.descricao LIKE :q' : '')
         . ' GROUP BY s.id ORDER BY s.nome';
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q' => '%'.$busca.'%']);
    else $stmt->execute();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $servicos = []; }

$cats_por_servico    = [];
$cat_ids_por_servico = [];
try {
    $rows = $conn->query("
        SELECT sc.servico_id, c.id as cat_id, c.nome
        FROM servico_categorias sc
        JOIN categorias_servico c ON c.id = sc.categoria_id
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $cats_por_servico[$r['servico_id']][]    = $r['nome'];
        $cat_ids_por_servico[$r['servico_id']][] = (int)$r['cat_id'];
    }
} catch (Exception $e) {}

$insumos_por_servico = [];
try {
    $rows = $conn->query("SELECT servico_id, COUNT(*) as total FROM servicos_insumos GROUP BY servico_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $insumos_por_servico[$r['servico_id']] = (int)$r['total'];
} catch (Exception $e) {}

$current_page = 'servicos.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serviços — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
    <style>
    .chips-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
    .chip{display:inline-flex;align-items:center;gap:4px;padding:5px 14px;border-radius:20px;font-size:13px;font-weight:500;cursor:pointer;border:2px solid var(--g-border);background:var(--g-surface);color:var(--g-text-2);transition:all .15s;user-select:none}
    .chip.active{background:var(--color-primary,#7c3aed);border-color:var(--color-primary,#7c3aed);color:#fff}
    .chip .material-symbols-outlined{font-size:15px}
    .cat-label-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
    .btn-nova-cat{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;background:var(--color-primary,#7c3aed);color:#fff;font-size:12px;font-weight:600;border:none;cursor:pointer;transition:opacity .15s}
    .btn-nova-cat:hover{opacity:.85}
    .btn-nova-cat .material-symbols-outlined{font-size:16px}
    .nova-cat-inline{display:none;padding:12px;background:var(--g-bg);border:1px solid var(--g-border);border-radius:8px;margin-bottom:10px;gap:8px;align-items:flex-start}
    .nova-cat-inline.show{display:flex}
    .nova-cat-inline input{flex:1;padding:8px 12px;border:1px solid var(--g-border);border-radius:6px;font-size:13px}
    .nova-cat-inline button{padding:8px 14px;border-radius:6px;border:none;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px}
    .nova-cat-inline .btn-salvar-cat{background:var(--color-primary,#7c3aed);color:#fff}
    .nova-cat-inline .btn-cancelar-cat{background:var(--g-surface);color:var(--g-text-2);border:1px solid var(--g-border)}
    /* badge insumos na tabela */
    .badge-insumos{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:12px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:600}
    .badge-insumos .material-symbols-outlined{font-size:13px}
    /* seção insumos no modal */
    .insumos-search{position:relative;margin-bottom:10px}
    .insumos-search input{width:100%;padding:9px 12px 9px 36px;border:1px solid var(--g-border);border-radius:8px;font-size:13px}
    .insumos-search .material-symbols-outlined{position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px;color:var(--g-text-3);pointer-events:none}
    .insumos-results{max-height:180px;overflow-y:auto;border:1px solid var(--g-border);border-radius:8px;margin-bottom:10px;background:var(--g-bg)}
    .insumo-item{padding:9px 12px;border-bottom:1px solid var(--g-border);cursor:pointer;transition:background .15s;display:flex;justify-content:space-between;align-items:center}
    .insumo-item:last-child{border-bottom:none}
    .insumo-item:hover{background:var(--g-hover)}
    .insumo-item-nome{font-size:13px;font-weight:500}
    .insumo-item-meta{font-size:11px;color:var(--g-text-3);margin-top:1px}
    .insumo-item-direita{text-align:right;flex-shrink:0}
    .insumo-item-valor{font-size:12px;color:var(--g-text-2)}
    .insumo-item-tipo{font-size:10px;font-weight:600;padding:1px 6px;border-radius:8px;margin-top:2px;display:inline-block}
    .tipo-fixo{background:#dcfce7;color:#166534}
    .tipo-variavel{background:#dbeafe;color:#1d4ed8}
    /* card insumo selecionado */
    .insumo-selecionado{display:grid;grid-template-columns:1fr auto auto auto;gap:8px;align-items:center;padding:9px 10px;background:var(--g-surface);border:1px solid var(--g-border);border-radius:8px;margin-bottom:6px}
    .insumo-sel-nome{font-size:13px;font-weight:500}
    .insumo-sel-meta{font-size:11px;color:var(--g-text-3)}
    .insumo-sel-qtd{width:64px;padding:5px 6px;border:1px solid var(--g-border);border-radius:6px;text-align:center;font-size:12px}
    .insumo-sel-qtd:disabled{opacity:.35;pointer-events:none}
    .vinculo-toggle-sm{display:flex;border:1px solid var(--g-border);border-radius:6px;overflow:hidden;font-size:11px}
    .vinculo-toggle-sm button{padding:3px 8px;border:none;background:transparent;cursor:pointer;color:var(--g-text-2);transition:background .1s,color .1s;font-size:11px}
    .vinculo-toggle-sm button.ativo-v{background:#dbeafe;color:#1d4ed8}
    .vinculo-toggle-sm button.ativo-f{background:#dcfce7;color:#166534}
    .btn-remover-insumo{padding:4px;border:none;background:transparent;color:var(--g-red,#dc2626);cursor:pointer;display:flex;align-items:center;border-radius:4px;transition:background .15s}
    .btn-remover-insumo:hover{background:#fee2e2}
    .insumos-vazio{text-align:center;padding:18px;color:var(--g-text-3);font-size:13px}
    .insumos-selecionados-titulo{font-size:11px;font-weight:600;color:var(--g-text-2);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px}
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
        <span class="topbar-title">Serviços</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px">build</span>Serviços
                </h1>
                <div class="page-subtitle"><?php echo count($servicos); ?> serviço<?php echo count($servicos) !== 1 ? 's' : ''; ?> cadastrado<?php echo count($servicos) !== 1 ? 's' : ''; ?></div>
            </div>
            <button class="btn btn-primary" onclick="abrirModal()">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">add</span> Novo Serviço
            </button>
        </div>

        <?php if ($msg): list($tipo, $texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <form method="GET" action="servicos.php" style="margin-bottom:16px">
            <div class="search-bar">
                <span class="search-icon material-symbols-outlined">search</span>
                <input type="text" name="q" placeholder="Buscar por nome ou descrição..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off">
                <?php if ($busca): ?>
                <button type="button" onclick="location.href='servicos.php'" style="background:none;border:none;cursor:pointer;color:var(--g-text-3);padding:0 4px;display:flex;align-items:center" title="Limpar">
                    <span class="material-symbols-outlined" style="font-size:18px">close</span>
                </button>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($servicos)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><span class="material-symbols-outlined">build</span></div>
                <div class="empty-state-title">Nenhum serviço encontrado</div>
                <div class="empty-state-sub"><?php echo $busca ? 'Tente outro termo de busca' : 'Clique em "+ Novo Serviço" para cadastrar'; ?></div>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Categorias</th>
                        <th>Insumos</th>
                        <th class="text-right">Valor Base</th>
                        <th class="text-center">Prazo (d.u.)</th>
                        <th class="text-center">Uso</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($servicos as $s): ?>
                <?php
                    $sc     = $cats_por_servico[$s['id']] ?? [];
                    $sc_ids = $cat_ids_por_servico[$s['id']] ?? [];
                    $total_insumos = $insumos_por_servico[$s['id']] ?? 0;
                ?>
                <tr class="<?php echo !$s['ativo'] ? 'row-inactive' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars($s['nome']); ?></strong>
                        <?php if ($s['descricao']): ?><br><span style="font-size:12px;color:var(--g-text-3)"><?php echo htmlspecialchars($s['descricao']); ?></span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($sc): foreach ($sc as $cn): ?>
                        <span class="badge badge-info" style="margin:1px 2px"><?php echo htmlspecialchars($cn); ?></span>
                        <?php endforeach; else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($total_insumos > 0): ?>
                        <span class="badge-insumos"><span class="material-symbols-outlined">inventory_2</span><?php echo $total_insumos; ?></span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="text-right"><strong>R$&nbsp;<?php echo number_format((float)$s['valor_base'], 2, ',', '.'); ?></strong></td>
                    <td class="text-center">
                        <?php if ($s['prazo_padrao_dias']): ?>
                        <span class="badge badge-info"><?php echo $s['prazo_padrao_dias']; ?> d.u.</span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($s['total_uso'] > 0): ?>
                        <span class="badge badge-info"><?php echo $s['total_uso']; ?></span>
                        <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($s['ativo']): ?>
                        <span class="badge badge-success"><span class="material-symbols-outlined" style="font-size:11px;vertical-align:middle">check_circle</span> Ativo</span>
                        <?php else: ?>
                        <span class="badge badge-dark"><span class="material-symbols-outlined" style="font-size:11px;vertical-align:middle">cancel</span> Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="table-actions">
                            <button class="btn-icon" title="Editar" onclick="editarServico(<?php echo $s['id']; ?>)">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            <?php if ($s['total_uso'] == 0): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir serviço &quot;<?php echo htmlspecialchars(addslashes($s['nome'])); ?>&quot;?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="btn-icon danger" title="Excluir"><span class="material-symbols-outlined">delete</span></button>
                            </form>
                            <?php else: ?>
                            <span class="btn-icon disabled" title="Em uso — não pode excluir"><span class="material-symbols-outlined">lock</span></span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<nav class="bottom-nav">
    <a href="dashboard.php"><span class="material-symbols-outlined nav-icon">dashboard</span>Painel</a>
    <a href="clientes.php"><span class="material-symbols-outlined nav-icon">group</span>Clientes</a>
    <a href="servicos.php" class="active"><span class="material-symbols-outlined nav-icon">build</span>Serviços</a>
    <a href="logout.php"><span class="material-symbols-outlined nav-icon">logout</span>Sair</a>
</nav>

<!-- MODAL CRIAR / EDITAR -->
<div class="modal-overlay" id="modal-servico">
    <div class="modal-box" style="max-width:600px;max-height:90vh;overflow-y:auto">
        <div class="modal-drag"></div>
        <div class="modal-title" id="modal-titulo">Novo Serviço</div>
        <form method="POST" id="form-servico" onsubmit="return prepararEnvio()">
            <input type="hidden" name="acao" id="f-acao" value="criar">
            <input type="hidden" name="id"   id="f-id"   value="">
            <input type="hidden" name="insumos_json" id="f-insumos-json" value="[]">

            <label class="form-label">NOME DO SERVIÇO *</label>
            <input class="form-input" type="text" name="nome" id="f-nome" required placeholder="Ex: Setup Completo">

            <label class="form-label">DESCRIÇÃO</label>
            <textarea class="form-input" name="descricao" id="f-descricao" rows="2" placeholder="Descreva brevemente o serviço..."></textarea>

            <div class="cat-label-row" style="margin-top:12px">
                <label class="form-label" style="margin:0">CATEGORIAS</label>
                <button type="button" class="btn-nova-cat" onclick="toggleNovaCatForm()">
                    <span class="material-symbols-outlined">add</span> Nova Categoria
                </button>
            </div>
            <div class="nova-cat-inline" id="nova-cat-form">
                <input type="text" id="nova-cat-nome" placeholder="Nome da nova categoria..." maxlength="50">
                <button type="button" class="btn-salvar-cat" onclick="salvarNovaCategoria()"><span class="material-symbols-outlined" style="font-size:16px">check</span> Salvar</button>
                <button type="button" class="btn-cancelar-cat" onclick="cancelarNovaCategoria()"><span class="material-symbols-outlined" style="font-size:16px">close</span></button>
            </div>
            <div class="chips-wrap" id="chips-categorias"></div>
            <div id="hidden-cats"></div>

            <div style="display:flex;gap:12px;margin-top:14px">
                <div style="flex:1">
                    <label class="form-label">VALOR BASE (R$)</label>
                    <input class="form-input" type="number" name="valor_base" id="f-valor" min="0" step="0.01" placeholder="0,00">
                </div>
                <div style="flex:1">
                    <label class="form-label">PRAZO PADRÃO (DIAS ÚTEIS)</label>
                    <input class="form-input" type="number" name="prazo_padrao_dias" id="f-prazo" min="1" step="1" placeholder="Ex: 7">
                </div>
            </div>

            <hr style="margin:16px 0;border:none;border-top:1px solid var(--g-border)">

            <!-- ── INSUMOS DO SERVIÇO ── -->
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
                <span class="material-symbols-outlined" style="font-size:18px;color:var(--g-text-2)">inventory_2</span>
                <span class="form-label" style="margin:0">INSUMOS DO SERVIÇO</span>
            </div>
            <p style="font-size:12px;color:var(--g-text-3);margin:0 0 10px">Fixo = incluso no preço (pré-marcado no pedido). Variável = selecionável conforme necessidade.</p>

            <div class="insumos-search">
                <span class="material-symbols-outlined">search</span>
                <input type="text" id="busca-insumo" placeholder="Buscar insumo por nome ou categoria..." autocomplete="off" oninput="buscarInsumos()">
            </div>
            <div class="insumos-results" id="resultados-insumos" style="display:none"></div>

            <div class="insumos-selecionados-titulo">INSUMOS SELECIONADOS (<span id="count-insumos">0</span>)</div>
            <div id="lista-insumos-selecionados"><div class="insumos-vazio">Nenhum insumo vinculado</div></div>

            <hr style="margin:16px 0;border:none;border-top:1px solid var(--g-border)">

            <label class="form-check">
                <input type="checkbox" name="ativo" id="f-ativo" value="1">
                SERVIÇO ATIVO (VISÍVEL NO FORMULÁRIO)
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="fecharModalServico()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
<script>
let todasCategorias  = [];
let catsSelecionadas = new Set();
// [{id, nome, unidade, valorunitario, categoria, tipo_insumo, quantidade, tipo_vinculo}]
let insumosSelecionados = [];
let timeoutBusca = null;

// ═══════════════════════════════════════════════════════════
//  CATEGORIAS
// ═══════════════════════════════════════════════════════════
async function carregarCategorias() {
    const r = await fetch('servicos.php?action=categorias');
    todasCategorias = await r.json();
}

function renderChips() {
    const wrap   = document.getElementById('chips-categorias');
    const hidden = document.getElementById('hidden-cats');
    wrap.innerHTML = ''; hidden.innerHTML = '';
    todasCategorias.forEach(c => {
        const chip = document.createElement('span');
        chip.className = 'chip' + (catsSelecionadas.has(c.id) ? ' active' : '');
        chip.innerHTML = (catsSelecionadas.has(c.id) ? '<span class="material-symbols-outlined">check</span>' : '') + _esc(c.nome);
        chip.onclick = () => { catsSelecionadas.has(c.id) ? catsSelecionadas.delete(c.id) : catsSelecionadas.add(c.id); renderChips(); };
        wrap.appendChild(chip);
    });
    catsSelecionadas.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'categorias[]'; inp.value = id;
        hidden.appendChild(inp);
    });
}

function toggleNovaCatForm() {
    const f = document.getElementById('nova-cat-form');
    f.classList.toggle('show');
    if (f.classList.contains('show')) { document.getElementById('nova-cat-nome').value = ''; setTimeout(() => document.getElementById('nova-cat-nome').focus(), 100); }
}
function cancelarNovaCategoria() { document.getElementById('nova-cat-form').classList.remove('show'); document.getElementById('nova-cat-nome').value = ''; }

async function salvarNovaCategoria() {
    const nome = document.getElementById('nova-cat-nome').value.trim();
    if (!nome) { alert('Digite o nome da categoria!'); return; }
    const fd = new FormData(); fd.append('action','criar_categoria'); fd.append('nome', nome);
    const data = await (await fetch('servicos.php', {method:'POST', body:fd})).json();
    if (data.sucesso) {
        todasCategorias.push({id:data.id, nome:data.nome});
        todasCategorias.sort((a,b) => a.nome.localeCompare(b.nome));
        catsSelecionadas.add(data.id);
        renderChips(); cancelarNovaCategoria(); _toast('Categoria criada!');
    } else { alert('Erro: ' + (data.erro || 'desconhecido')); }
}

// ═══════════════════════════════════════════════════════════
//  INSUMOS — busca
// ═══════════════════════════════════════════════════════════
function buscarInsumos() {
    clearTimeout(timeoutBusca);
    const q = document.getElementById('busca-insumo').value.trim();
    if (!q) { document.getElementById('resultados-insumos').style.display = 'none'; return; }
    timeoutBusca = setTimeout(async () => {
        const insumos = await (await fetch('servicos.php?action=buscar_insumos&q=' + encodeURIComponent(q))).json();
        renderResultadosInsumos(insumos);
    }, 280);
}

function renderResultadosInsumos(insumos) {
    const div = document.getElementById('resultados-insumos');
    if (!insumos.length) { div.innerHTML = '<div class="insumos-vazio">Nenhum insumo encontrado</div>'; div.style.display='block'; return; }
    div.innerHTML = '';
    insumos.forEach(ins => {
        if (insumosSelecionados.find(i => i.id == ins.id)) return;
        const d = document.createElement('div');
        d.className = 'insumo-item';
        d.onclick = () => adicionarInsumo(ins);
        const tipoCls = ins.tipo_insumo === 'fixo' ? 'tipo-fixo' : 'tipo-variavel';
        const tipoTxt = ins.tipo_insumo === 'fixo' ? 'Fixo' : 'Variável';
        d.innerHTML = `
            <div>
                <div class="insumo-item-nome">${_esc(ins.nome)}</div>
                <div class="insumo-item-meta">${_esc(ins.unidade)}${ins.categoria ? ' · ' + _esc(ins.categoria) : ''}</div>
            </div>
            <div class="insumo-item-direita">
                <div class="insumo-item-valor">R$ ${_fmt(parseFloat(ins.valorunitario||0))}</div>
                <div class="insumo-item-tipo ${tipoCls}">${tipoTxt}</div>
            </div>`;
        div.appendChild(d);
    });
    div.style.display = 'block';
}

function adicionarInsumo(ins) {
    const tipoVinculo = ins.tipo_insumo === 'fixo' ? 'fixo' : 'variavel';
    insumosSelecionados.push({
        id: parseInt(ins.id),
        nome: ins.nome,
        unidade: ins.unidade,
        valorunitario: parseFloat(ins.valorunitario || 0),
        categoria: ins.categoria || '',
        tipo_insumo: ins.tipo_insumo || 'variavel',
        quantidade: ins.quantidade_padrao ? parseFloat(ins.quantidade_padrao) : 1,
        tipo_vinculo: tipoVinculo
    });
    document.getElementById('busca-insumo').value = '';
    document.getElementById('resultados-insumos').style.display = 'none';
    renderInsumosSelecionados();
}

function removerInsumo(id) {
    insumosSelecionados = insumosSelecionados.filter(i => i.id !== parseInt(id));
    renderInsumosSelecionados();
}

function setTipoVinculo(id, tipo, btn) {
    const ins = insumosSelecionados.find(i => i.id === parseInt(id));
    if (ins) ins.tipo_vinculo = tipo;
    // atualiza botões
    const par = btn.parentElement;
    par.querySelectorAll('button').forEach(b => b.classList.remove('ativo-v','ativo-f'));
    btn.classList.add(tipo === 'fixo' ? 'ativo-f' : 'ativo-v');
    // habilita/desabilita qtd
    const qtdEl = par.closest('.insumo-selecionado').querySelector('.insumo-sel-qtd');
    if (qtdEl) qtdEl.disabled = (tipo === 'variavel');
}

function atualizarQtd(id, val) {
    const ins = insumosSelecionados.find(i => i.id === parseInt(id));
    if (ins) ins.quantidade = Math.max(0.001, parseFloat(val) || 1);
}

function renderInsumosSelecionados() {
    const div = document.getElementById('lista-insumos-selecionados');
    document.getElementById('count-insumos').textContent = insumosSelecionados.length;
    if (!insumosSelecionados.length) { div.innerHTML = '<div class="insumos-vazio">Nenhum insumo vinculado</div>'; return; }
    div.innerHTML = '';
    insumosSelecionados.forEach(ins => {
        const isFixo = ins.tipo_vinculo === 'fixo';
        const card = document.createElement('div');
        card.className = 'insumo-selecionado';
        card.innerHTML = `
            <div class="insumo-sel-info">
                <div class="insumo-sel-nome">${_esc(ins.nome)}</div>
                <div class="insumo-sel-meta">${_esc(ins.unidade)}${ins.categoria ? ' · ' + _esc(ins.categoria) : ''} · R$ ${_fmt(ins.valorunitario)}</div>
            </div>
            <div class="vinculo-toggle-sm">
                <button type="button" class="${!isFixo?'ativo-v':''}" onclick="setTipoVinculo(${ins.id},'variavel',this)">Variável</button>
                <button type="button" class="${isFixo?'ativo-f':''}" onclick="setTipoVinculo(${ins.id},'fixo',this)">Fixo</button>
            </div>
            <input class="insumo-sel-qtd" type="number" min="0.001" step="0.001"
                value="${ins.quantidade}" ${!isFixo?'disabled':''}
                onchange="atualizarQtd(${ins.id}, this.value)" title="Qtd padrão (apenas fixo)">
            <button type="button" class="btn-remover-insumo" onclick="removerInsumo(${ins.id})" title="Remover">
                <span class="material-symbols-outlined" style="font-size:18px">close</span>
            </button>`;
        div.appendChild(card);
    });
}

function prepararEnvio() {
    document.getElementById('f-insumos-json').value = JSON.stringify(
        insumosSelecionados.map(i => ({id: i.id, quantidade: i.quantidade, tipo_vinculo: i.tipo_vinculo}))
    );
    return true;
}

// ═══════════════════════════════════════════════════════════
//  MODAL PRINCIPAL
// ═══════════════════════════════════════════════════════════
async function abrirModal() {
    await carregarCategorias();
    catsSelecionadas = new Set(); insumosSelecionados = [];
    document.getElementById('modal-titulo').textContent   = 'Novo Serviço';
    document.getElementById('f-acao').value               = 'criar';
    document.getElementById('f-id').value                 = '';
    document.getElementById('f-nome').value               = '';
    document.getElementById('f-descricao').value          = '';
    document.getElementById('f-valor').value              = '';
    document.getElementById('f-prazo').value              = '';
    document.getElementById('f-ativo').checked            = true;
    document.getElementById('busca-insumo').value         = '';
    document.getElementById('resultados-insumos').style.display = 'none';
    document.getElementById('nova-cat-form').classList.remove('show');
    renderChips(); renderInsumosSelecionados();
    document.getElementById('modal-servico').classList.add('aberto');
    setTimeout(() => document.getElementById('f-nome').focus(), 150);
}

async function editarServico(id) {
    await carregarCategorias();
    const data = await (await fetch('servicos.php?action=get_servico&id=' + id)).json();
    if (!data.ok) { alert('Erro ao carregar serviço.'); return; }
    const s = data.servico;
    document.getElementById('modal-titulo').textContent   = 'Editar Serviço';
    document.getElementById('f-acao').value               = 'editar';
    document.getElementById('f-id').value                 = s.id;
    document.getElementById('f-nome').value               = s.nome;
    document.getElementById('f-descricao').value          = s.descricao || '';
    document.getElementById('f-valor').value              = parseFloat(s.valor_base || 0).toFixed(2);
    document.getElementById('f-prazo').value              = s.prazo_padrao_dias || '';
    document.getElementById('f-ativo').checked            = s.ativo == 1;
    document.getElementById('busca-insumo').value         = '';
    document.getElementById('resultados-insumos').style.display = 'none';
    document.getElementById('nova-cat-form').classList.remove('show');
    // categorias
    catsSelecionadas = new Set((s.cat_ids || []).map(Number));
    // insumos
    insumosSelecionados = (s.insumos || []).map(i => ({
        id:           parseInt(i.insumo_id),
        nome:         i.nome,
        unidade:      i.unidade,
        valorunitario: parseFloat(i.valorunitario || 0),
        categoria:    i.categoria || '',
        tipo_insumo:  i.tipo_insumo || 'variavel',
        quantidade:   parseFloat(i.quantidade_padrao || 1),
        tipo_vinculo: i.tipo_vinculo || 'variavel'
    }));
    renderChips(); renderInsumosSelecionados();
    document.getElementById('modal-servico').classList.add('aberto');
    setTimeout(() => document.getElementById('f-nome').focus(), 150);
}

function fecharModalServico() { document.getElementById('modal-servico').classList.remove('aberto'); }
document.getElementById('modal-servico').addEventListener('click', function(e) { if (e.target === this) fecharModalServico(); });

// ═══════════════════════════════════════════════════════════
//  UTILITÁRIOS
// ═══════════════════════════════════════════════════════════
function _esc(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }
function _fmt(v) { return (parseFloat(v)||0).toFixed(2).replace('.',','); }
function _toast(msg) {
    const el = document.createElement('div');
    el.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#323232;color:#fff;padding:12px 22px;border-radius:8px;font-size:14px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,.3)';
    el.textContent = msg; document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('nova-cat-nome').addEventListener('keypress', e => { if (e.key==='Enter'){e.preventDefault();salvarNovaCategoria();} });
});
</script>
</body>
</html>
