<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

try { $conn->query("ALTER TABLE servicos ADD COLUMN prazo_padrao_dias INT DEFAULT NULL"); } catch (Exception $e) {}

// API JSON: categorias
if (isset($_GET['action']) && $_GET['action'] === 'categorias') {
    header('Content-Type: application/json');
    $cats = $conn->query("SELECT id, nome FROM categorias_servico WHERE ativo=1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($cats);
    exit;
}

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

        // Sincroniza categorias
        $conn->prepare('DELETE FROM servico_categorias WHERE servico_id=?')->execute([$sid]);
        foreach ($cats as $cid) {
            if ($cid > 0) $conn->prepare('INSERT IGNORE INTO servico_categorias (servico_id, categoria_id) VALUES (?,?)')->execute([$sid, $cid]);
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

try {
    $sql = 'SELECT s.*, COUNT(DISTINCT ps.id) as total_uso FROM servicos s LEFT JOIN pre_os_servicos ps ON ps.servico_id = s.id'
         . ($busca ? ' WHERE s.nome LIKE :q OR s.descricao LIKE :q' : '')
         . ' GROUP BY s.id ORDER BY s.nome';
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q' => '%'.$busca.'%']);
    else $stmt->execute();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $servicos = []; }

// Carrega categorias de cada serviço
$cats_por_servico = [];
try {
    $rows = $conn->query("
        SELECT sc.servico_id, c.nome
        FROM servico_categorias sc
        JOIN categorias_servico c ON c.id = sc.categoria_id
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $cats_por_servico[$r['servico_id']][] = $r['nome'];
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
    .chips-wrap { display:flex; flex-wrap:wrap; gap:8px; margin-top:6px; }
    .chip {
        display:inline-flex; align-items:center; gap:4px;
        padding:5px 14px; border-radius:20px; font-size:13px; font-weight:500;
        cursor:pointer; border:2px solid var(--g-border);
        background:var(--g-surface); color:var(--g-text-2);
        transition:all .15s;
        user-select:none;
    }
    .chip.active {
        background:var(--color-primary,#7c3aed);
        border-color:var(--color-primary,#7c3aed);
        color:#fff;
    }
    .chip .material-symbols-outlined { font-size:15px; }
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
                        <th class="text-right">Valor Base</th>
                        <th class="text-center">Prazo (d.u.)</th>
                        <th class="text-center">Uso</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($servicos as $s): ?>
                <?php $sc = $cats_por_servico[$s['id']] ?? []; ?>
                <tr class="<?php echo !$s['ativo'] ? 'row-inactive' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars($s['nome']); ?></strong>
                        <?php if ($s['descricao']): ?><br><span style="font-size:12px;color:var(--g-text-3)"><?php echo htmlspecialchars($s['descricao']); ?></span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($sc): foreach ($sc as $cn): ?>
                        <span class="badge badge-info" style="margin:1px 2px"><?php echo htmlspecialchars($cn); ?></span>
                        <?php endforeach; else: ?><span class="text-muted">—</span><?php endif; ?>
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
                            <button class="btn-icon" title="Editar"
                                onclick="editarServico(<?php echo $s['id']; ?>,<?php echo htmlspecialchars(json_encode($s['nome']),ENT_QUOTES); ?>,<?php echo htmlspecialchars(json_encode($s['descricao']),ENT_QUOTES); ?>,<?php echo $s['valor_base']; ?>,<?php echo $s['ativo']; ?>,<?php echo $s['prazo_padrao_dias'] !== null ? $s['prazo_padrao_dias'] : 'null'; ?>,<?php echo htmlspecialchars(json_encode(array_map('intval', array_keys(array_flip(array_map(fn($n) => $n, array_column($conn->query('SELECT c.id FROM servico_categorias sc JOIN categorias_servico c ON c.id=sc.categoria_id WHERE sc.servico_id='.(int)$s['id'])->fetchAll(PDO::FETCH_ASSOC), 'id'))))),ENT_QUOTES); ?>)">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            <?php if ($s['total_uso'] == 0): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir serviço &quot;<?php echo htmlspecialchars(addslashes($s['nome'])); ?>&quot;?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="btn-icon danger" title="Excluir">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="btn-icon disabled" title="Em uso — não pode excluir">
                                <span class="material-symbols-outlined">lock</span>
                            </span>
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
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title" id="modal-titulo">Novo Serviço</div>
        <form method="POST" id="form-servico">
            <input type="hidden" name="acao" id="f-acao" value="criar">
            <input type="hidden" name="id"   id="f-id"   value="">

            <label class="form-label">NOME DO SERVIÇO *</label>
            <input class="form-input" type="text" name="nome" id="f-nome" required placeholder="Ex: Setup Completo">

            <label class="form-label">DESCRIÇÃO</label>
            <textarea class="form-input" name="descricao" id="f-descricao" rows="3" placeholder="Descreva brevemente o serviço..."></textarea>

            <label class="form-label">CATEGORIAS</label>
            <div class="chips-wrap" id="chips-categorias"></div>
            <div id="hidden-cats"></div>

            <div style="display:flex;gap:12px;margin-top:12px">
                <div style="flex:1">
                    <label class="form-label">VALOR BASE (R$)</label>
                    <input class="form-input" type="number" name="valor_base" id="f-valor" min="0" step="0.01" placeholder="0,00">
                </div>
                <div style="flex:1">
                    <label class="form-label">PRAZO PADRÃO (DIAS ÚTEIS)</label>
                    <input class="form-input" type="number" name="prazo_padrao_dias" id="f-prazo" min="1" step="1" placeholder="Ex: 7">
                </div>
            </div>

            <label class="form-check" style="margin-top:8px">
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
let todasCategorias = [];
let catsSelecionadas = new Set();

async function carregarCategorias() {
    if (todasCategorias.length) return;
    const r = await fetch('servicos.php?action=categorias');
    todasCategorias = await r.json();
}

function renderChips() {
    const wrap = document.getElementById('chips-categorias');
    const hidden = document.getElementById('hidden-cats');
    wrap.innerHTML = '';
    hidden.innerHTML = '';
    todasCategorias.forEach(c => {
        const chip = document.createElement('span');
        chip.className = 'chip' + (catsSelecionadas.has(c.id) ? ' active' : '');
        chip.innerHTML = (catsSelecionadas.has(c.id) ? '<span class="material-symbols-outlined">check</span>' : '') + c.nome;
        chip.onclick = () => {
            if (catsSelecionadas.has(c.id)) catsSelecionadas.delete(c.id);
            else catsSelecionadas.add(c.id);
            renderChips();
        };
        wrap.appendChild(chip);
    });
    catsSelecionadas.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'categorias[]'; inp.value = id;
        hidden.appendChild(inp);
    });
}

async function abrirModal() {
    await carregarCategorias();
    catsSelecionadas = new Set();
    document.getElementById('modal-titulo').textContent = 'Novo Serviço';
    document.getElementById('f-acao').value     = 'criar';
    document.getElementById('f-id').value       = '';
    document.getElementById('f-nome').value     = '';
    document.getElementById('f-descricao').value= '';
    document.getElementById('f-valor').value    = '';
    document.getElementById('f-prazo').value    = '';
    document.getElementById('f-ativo').checked  = true;
    renderChips();
    document.getElementById('modal-servico').classList.add('aberto');
    setTimeout(() => document.getElementById('f-nome').focus(), 150);
}

async function editarServico(id, nome, desc, valor, ativo, prazo, catIds) {
    await carregarCategorias();
    catsSelecionadas = new Set(catIds);
    document.getElementById('modal-titulo').textContent = 'Editar Serviço';
    document.getElementById('f-acao').value      = 'editar';
    document.getElementById('f-id').value        = id;
    document.getElementById('f-nome').value      = nome;
    document.getElementById('f-descricao').value = desc || '';
    document.getElementById('f-valor').value     = Number(valor).toFixed(2);
    document.getElementById('f-prazo').value     = prazo !== null && prazo !== undefined ? prazo : '';
    document.getElementById('f-ativo').checked   = !!ativo;
    renderChips();
    document.getElementById('modal-servico').classList.add('aberto');
    setTimeout(() => document.getElementById('f-nome').focus(), 150);
}

function fecharModalServico() {
    document.getElementById('modal-servico').classList.remove('aberto');
}

document.getElementById('modal-servico').addEventListener('click', function(e) {
    if (e.target === this) fecharModalServico();
});
</script>
</body>
</html>
