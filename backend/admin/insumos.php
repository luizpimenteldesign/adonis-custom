<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$busca = trim($_GET['q'] ?? '');
$msg   = isset($_GET['msg']) ? $_GET['msg'] : '';

try {
    $sql = "SELECT i.*, GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ') as servicos_nomes
            FROM insumos i
            LEFT JOIN insumos_servicos ins ON ins.insumoid = i.id
            LEFT JOIN servicos s ON s.id = ins.servicoid";
    if ($busca) $sql .= " WHERE i.nome LIKE :q OR i.unidade LIKE :q";
    $sql .= " GROUP BY i.id ORDER BY i.nome";
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q' => '%'.$busca.'%']);
    else $stmt->execute();
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $insumos = []; }

try {
    $categorias = $conn->query("SELECT DISTINCT categoria FROM servicos WHERE ativo = 1 AND categoria IS NOT NULL AND categoria != '' ORDER BY categoria")
                       ->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $categorias = []; }

$current_page = 'insumos.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insumos — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
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
        <span class="topbar-title">Insumos</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px">inventory_2</span>Insumos
                </h1>
                <div class="page-subtitle"><?php echo count($insumos); ?> insumo<?php echo count($insumos) !== 1 ? 's' : ''; ?> cadastrado<?php echo count($insumos) !== 1 ? 's' : ''; ?></div>
            </div>
            <button class="btn btn-primary" onclick="abrirModal()">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">add</span> Novo Insumo
            </button>
        </div>

        <?php if ($msg): list($tipo, $texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <form method="GET" action="insumos.php" style="margin-bottom:16px">
            <div class="search-bar">
                <span class="search-icon material-symbols-outlined">search</span>
                <input type="text" name="q" placeholder="Buscar por nome ou unidade..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off">
                <?php if ($busca): ?>
                <button type="button" onclick="location.href='insumos.php'" style="background:none;border:none;cursor:pointer;color:var(--g-text-3);padding:0 4px;display:flex;align-items:center" title="Limpar">
                    <span class="material-symbols-outlined" style="font-size:18px">close</span>
                </button>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($insumos)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><span class="material-symbols-outlined">inventory_2</span></div>
                <div class="empty-state-title">Nenhum insumo encontrado</div>
                <div class="empty-state-sub"><?php echo $busca ? 'Tente outro termo de busca' : 'Clique em "+ Novo Insumo" para cadastrar'; ?></div>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th class="text-center">Unidade</th>
                        <th class="text-right">Valor Unit.</th>
                        <th class="text-center">Estoque</th>
                        <th>Serviços Vinculados</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($insumos as $ins): ?>
                <tr class="<?php echo !$ins['ativo'] ? 'row-inactive' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars($ins['nome']); ?></strong></td>
                    <td class="text-center">
                        <span class="badge badge-dark"><?php echo htmlspecialchars($ins['unidade']); ?></span>
                    </td>
                    <td class="text-right"><strong>R$&nbsp;<?php echo number_format((float)$ins['valor_unitario'], 2, ',', '.'); ?></strong></td>
                    <td class="text-center">
                        <?php
                            $estoque = (float)$ins['quantidade_estoque'];
                            $badge_class = $estoque <= 0 ? 'badge-danger' : ($estoque <= 5 ? 'badge-warning' : 'badge-success');
                        ?>
                        <span class="badge <?php echo $badge_class; ?>"><?php echo rtrim(rtrim(number_format($estoque, 3, ',', '.'), '0'), ','); ?></span>
                    </td>
                    <td style="font-size:13px;color:var(--g-text-2)">
                        <?php echo $ins['servicos_nomes'] ? htmlspecialchars($ins['servicos_nomes']) : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($ins['ativo']): ?>
                        <span class="badge badge-success">
                            <span class="material-symbols-outlined" style="font-size:11px;vertical-align:middle">check_circle</span> Ativo
                        </span>
                        <?php else: ?>
                        <span class="badge badge-dark">
                            <span class="material-symbols-outlined" style="font-size:11px;vertical-align:middle">cancel</span> Inativo
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="table-actions">
                            <button class="btn-icon" title="Editar" onclick="editarInsumo(<?php echo $ins['id']; ?>)">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            <button class="btn-icon <?php echo $ins['ativo'] ? 'danger' : ''; ?>" title="<?php echo $ins['ativo'] ? 'Desativar' : 'Reativar'; ?>" onclick="toggleAtivo(<?php echo $ins['id']; ?>, <?php echo $ins['ativo']; ?>)">
                                <span class="material-symbols-outlined"><?php echo $ins['ativo'] ? 'block' : 'check_circle'; ?></span>
                            </button>
                            <button class="btn-icon danger" title="Excluir" onclick="excluirInsumo(<?php echo $ins['id']; ?>, <?php echo htmlspecialchars(json_encode($ins['nome']), ENT_QUOTES); ?>)">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
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
    <a href="dashboard.php">
        <span class="material-symbols-outlined nav-icon">dashboard</span>Painel
    </a>
    <a href="clientes.php">
        <span class="material-symbols-outlined nav-icon">group</span>Clientes
    </a>
    <a href="servicos.php">
        <span class="material-symbols-outlined nav-icon">build</span>Serviços
    </a>
    <a href="insumos.php" class="active">
        <span class="material-symbols-outlined nav-icon">inventory_2</span>Insumos
    </a>
</nav>

<!-- MODAL CRIAR / EDITAR -->
<div class="modal-overlay" id="modal-insumo">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title" id="modal-titulo">Novo Insumo</div>
        <div id="form-insumo">
            <input type="hidden" id="f-id" value="">

            <label class="form-label">NOME DO INSUMO *</label>
            <input class="form-input" type="text" id="f-nome" placeholder="Ex: Encordamento para Guitarra 09-42">

            <div style="display:flex;gap:12px">
                <div style="flex:1">
                    <label class="form-label">UNIDADE *</label>
                    <input class="form-input" type="text" id="f-unidade" placeholder="Ex: conjunto, metro, ml">
                </div>
                <div style="flex:1">
                    <label class="form-label">VALOR UNITÁRIO (R$)</label>
                    <input class="form-input" type="number" id="f-valor" min="0" step="0.01" placeholder="0,00">
                </div>
            </div>

            <label class="form-label">QUANTIDADE EM ESTOQUE</label>
            <input class="form-input" type="number" id="f-estoque" min="0" step="0.001" placeholder="0">

            <label class="form-label" style="margin-top:16px">CATEGORIA DE SERVIÇOS</label>
            <select class="form-input" id="f-categoria" onchange="carregarServicos()">
                <option value="">Selecione uma categoria...</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>

            <div id="bloco-servicos" style="display:none;margin-top:12px">
                <label class="form-label">SERVIÇOS DESTA CATEGORIA (múltipla seleção)</label>
                <div id="lista-servicos" style="display:flex;flex-direction:column;gap:6px;max-height:180px;overflow-y:auto;background:var(--g-bg);border:1px solid var(--g-border);border-radius:8px;padding:10px"></div>
            </div>

            <label class="form-check" style="margin-top:12px">
                <input type="checkbox" id="f-ativo" value="1" checked>
                INSUMO ATIVO
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarInsumo()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
<script>
let servicosSelecionados = [];

function abrirModal() {
    document.getElementById('modal-titulo').textContent = 'Novo Insumo';
    document.getElementById('f-id').value       = '';
    document.getElementById('f-nome').value     = '';
    document.getElementById('f-unidade').value  = '';
    document.getElementById('f-valor').value    = '';
    document.getElementById('f-estoque').value  = '';
    document.getElementById('f-categoria').value = '';
    document.getElementById('f-ativo').checked  = true;
    document.getElementById('bloco-servicos').style.display = 'none';
    document.getElementById('lista-servicos').innerHTML = '';
    servicosSelecionados = [];
    document.getElementById('modal-insumo').classList.add('aberto');
    setTimeout(() => document.getElementById('f-nome').focus(), 150);
}

function editarInsumo(id) {
    fetch('insumos-api.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert('Erro ao carregar insumo.'); return; }
            const ins = data.insumo;
            document.getElementById('modal-titulo').textContent = 'Editar Insumo';
            document.getElementById('f-id').value      = ins.id;
            document.getElementById('f-nome').value    = ins.nome;
            document.getElementById('f-unidade').value = ins.unidade;
            document.getElementById('f-valor').value   = parseFloat(ins.valor_unitario).toFixed(2);
            document.getElementById('f-estoque').value = parseFloat(ins.quantidade_estoque);
            document.getElementById('f-ativo').checked = ins.ativo == 1;
            servicosSelecionados = (ins.servicos || []).map(Number);
            document.getElementById('modal-insumo').classList.add('aberto');
        });
}

function carregarServicos() {
    const cat = document.getElementById('f-categoria').value;
    const bloco = document.getElementById('bloco-servicos');
    const lista = document.getElementById('lista-servicos');
    if (!cat) { bloco.style.display = 'none'; lista.innerHTML = ''; return; }
    fetch('insumos-api.php?categoria=' + encodeURIComponent(cat))
        .then(r => r.json())
        .then(data => {
            if (!data.ok || !data.servicos.length) { bloco.style.display = 'none'; return; }
            lista.innerHTML = '';
            data.servicos.forEach(s => {
                const checked = servicosSelecionados.includes(s.id) ? 'checked' : '';
                lista.innerHTML += `<label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer">
                    <input type="checkbox" value="${s.id}" ${checked} onchange="toggleServico(${s.id})">
                    ${esc(s.nome)}
                </label>`;
            });
            bloco.style.display = 'block';
        });
}

function toggleServico(id) {
    const idx = servicosSelecionados.indexOf(id);
    if (idx === -1) servicosSelecionados.push(id);
    else servicosSelecionados.splice(idx, 1);
}

function salvarInsumo() {
    const id      = document.getElementById('f-id').value;
    const nome    = document.getElementById('f-nome').value.trim();
    const unidade = document.getElementById('f-unidade').value.trim();
    const valor   = document.getElementById('f-valor').value;
    const estoque = document.getElementById('f-estoque').value;
    const ativo   = document.getElementById('f-ativo').checked ? 1 : 0;

    if (!nome || !unidade) { alert('Nome e unidade sao obrigatorios.'); return; }

    const payload = { nome, unidade, valor_unitario: valor, quantidade_estoque: estoque, ativo, servicos: servicosSelecionados };
    const method  = id ? 'PUT' : 'POST';
    const url     = id ? 'insumos-api.php?id=' + id : 'insumos-api.php';

    fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(r => r.json())
        .then(data => {
            if (data.ok) location.href = 'insumos.php?msg=sucesso:Insumo ' + (id ? 'atualizado' : 'criado') + ' com sucesso!';
            else alert('Erro: ' + data.erro);
        });
}

function toggleAtivo(id, ativo) {
    const acao = ativo ? 'Desativar' : 'Reativar';
    if (!confirm(acao + ' este insumo?')) return;
    if (ativo) {
        fetch('insumos-api.php?id=' + id, { method: 'DELETE' })
            .then(r => r.json())
            .then(data => { if (data.ok) location.reload(); else alert('Erro: ' + data.erro); });
    } else {
        fetch('insumos-api.php?id=' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ativo: 1, nome: '', unidade: '' })
        }).then(r => r.json()).then(data => { if (data.ok) location.reload(); else alert('Erro: ' + data.erro); });
    }
}

function excluirInsumo(id, nome) {
    if (!confirm('Excluir o insumo "' + nome + '" permanentemente? Esta acao nao pode ser desfeita.')) return;
    fetch('insumos-api.php?id=' + id + '&excluir=1', { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.ok) location.href = 'insumos.php?msg=sucesso:Insumo excluido com sucesso.';
            else alert('Erro ao excluir: ' + data.erro);
        });
}

function fecharModal() {
    document.getElementById('modal-insumo').classList.remove('aberto');
}

document.getElementById('modal-insumo').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
