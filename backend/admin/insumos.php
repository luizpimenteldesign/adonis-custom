<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$busca = trim($_GET['q'] ?? '');
$msg   = isset($_GET['msg']) ? $_GET['msg'] : '';

try {
    $sql = "SELECT i.*, GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') as servicos_nomes
            FROM insumos i
            LEFT JOIN servicos_insumos si ON si.insumo_id = i.id
            LEFT JOIN servicos s ON s.id = si.servico_id";
    if ($busca) $sql .= " WHERE i.nome LIKE :q OR i.marca LIKE :q OR i.modelo LIKE :q OR i.unidade LIKE :q OR i.categoria LIKE :q";
    $sql .= " GROUP BY i.id ORDER BY i.categoria, i.nome";
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q' => '%'.$busca.'%']);
    else $stmt->execute();
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $insumos = []; }

try {
    $stmt_cat = $conn->query("SELECT nome FROM categorias_servico ORDER BY nome");
    $categorias = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);

    $todos_servicos = $conn->query(
        "SELECT id, nome, categoria FROM servicos WHERE ativo = 1 AND categoria IS NOT NULL AND categoria != '' ORDER BY categoria, nome"
    )->fetchAll(PDO::FETCH_ASSOC);

    $servicos_por_categoria = [];
    foreach ($todos_servicos as $s) {
        $servicos_por_categoria[$s['categoria']][] = $s;
    }

    // Categorias de insumos existentes para o datalist
    $cats_insumos = $conn->query(
        "SELECT DISTINCT categoria FROM insumos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria"
    )->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $categorias = [];
    $servicos_por_categoria = [];
    $cats_insumos = [];
}

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
    <style>
    /* ── Chips de categoria ── */
    .cat-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
    .cat-chip{display:flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;border:1px solid var(--g-border);background:var(--g-bg);font-size:13px;cursor:pointer;user-select:none;transition:background .15s,border-color .15s,color .15s}
    .cat-chip:hover{background:var(--g-hover)}
    .cat-chip.ativa{background:var(--color-primary,#7c3aed);border-color:var(--color-primary,#7c3aed);color:#fff}
    .cat-chip .material-symbols-outlined{font-size:14px}
    /* ── Grupos de serviço ── */
    .servicos-grupos{display:flex;flex-direction:column;gap:0;margin-top:12px;border:1px solid var(--g-border);border-radius:8px;overflow:hidden;max-height:260px;overflow-y:auto}
    .grupo-cat{border-bottom:1px solid var(--g-border)}
    .grupo-cat:last-child{border-bottom:none}
    .grupo-cat-titulo{padding:8px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--g-text-3);background:var(--g-hover)}
    .grupo-cat-itens{display:flex;flex-direction:column;gap:0}
    /* ── Item de serviço com vinculo inline ── */
    .servico-vinculo-item{display:grid;grid-template-columns:20px 1fr auto auto;align-items:center;gap:8px;padding:8px 12px;font-size:13px;border-top:1px solid var(--g-border);transition:background .1s}
    .servico-vinculo-item:first-child{border-top:none}
    .servico-vinculo-item:hover{background:var(--g-hover)}
    .servico-vinculo-item input[type=checkbox]{width:15px;height:15px;cursor:pointer;accent-color:var(--color-primary,#7c3aed)}
    .vinculo-toggle{display:flex;border:1px solid var(--g-border);border-radius:6px;overflow:hidden;font-size:11px}
    .vinculo-toggle button{padding:2px 8px;border:none;background:transparent;cursor:pointer;color:var(--g-text-2);transition:background .1s,color .1s}
    .vinculo-toggle button.ativo{background:var(--color-primary,#7c3aed);color:#fff}
    .vinculo-qtd{width:56px;padding:2px 6px;border:1px solid var(--g-border);border-radius:6px;font-size:12px;text-align:center}
    .vinculo-qtd:disabled{opacity:.3;pointer-events:none}
    /* ── Toggle fixo/variavel do insumo ── */
    .tipo-toggle{display:flex;border:1px solid var(--g-border);border-radius:8px;overflow:hidden;margin-top:6px}
    .tipo-toggle button{flex:1;padding:7px 0;border:none;background:transparent;cursor:pointer;font-size:13px;font-weight:600;color:var(--g-text-2);transition:background .15s,color .15s}
    .tipo-toggle button.ativo.variavel{background:#dbeafe;color:#1d4ed8}
    .tipo-toggle button.ativo.fixo{background:#dcfce7;color:#166534}
    .qtd-padrao-wrap{margin-top:8px}
    /* ── Coluna tipo na tabela ── */
    .badge-fixo{background:#dcfce7;color:#166534}
    .badge-variavel{background:#dbeafe;color:#1d4ed8}
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
                <input type="text" name="q" placeholder="Buscar por nome, marca, modelo, categoria ou unidade..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off">
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
                        <th>Nome / Categoria</th>
                        <th>Marca / Modelo</th>
                        <th class="text-center">Tipo</th>
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
                    <td>
                        <strong><?php echo htmlspecialchars($ins['nome']); ?></strong>
                        <?php if (!empty($ins['categoria'])): ?>
                        <div style="font-size:11px;color:var(--g-text-3);margin-top:2px"><?php echo htmlspecialchars($ins['categoria']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;color:var(--g-text-2)">
                        <?php
                            $mb = trim(($ins['marca'] ?? '') . ' ' . ($ins['modelo'] ?? ''));
                            echo $mb ? htmlspecialchars($mb) : '<span class="text-muted">—</span>';
                        ?>
                    </td>
                    <td class="text-center">
                        <?php $tipo = $ins['tipo_insumo'] ?? 'variavel'; ?>
                        <span class="badge <?php echo $tipo === 'fixo' ? 'badge-fixo' : 'badge-variavel'; ?>">
                            <?php echo $tipo === 'fixo' ? 'Fixo' : 'Variável'; ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-dark"><?php echo htmlspecialchars($ins['unidade']); ?></span>
                    </td>
                    <td class="text-right"><strong>R$&nbsp;<?php echo number_format((float)$ins['valorunitario'], 2, ',', '.'); ?></strong></td>
                    <td class="text-center">
                        <?php
                            $estoque = (float)$ins['quantidadeestoque'];
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
    <a href="dashboard.php"><span class="material-symbols-outlined nav-icon">dashboard</span>Painel</a>
    <a href="clientes.php"><span class="material-symbols-outlined nav-icon">group</span>Clientes</a>
    <a href="servicos.php"><span class="material-symbols-outlined nav-icon">build</span>Serviços</a>
    <a href="insumos.php" class="active"><span class="material-symbols-outlined nav-icon">inventory_2</span>Insumos</a>
</nav>

<!-- datalist categorias de insumos -->
<datalist id="dl-cats-insumos">
    <?php foreach ($cats_insumos as $ci): ?>
    <option value="<?php echo htmlspecialchars($ci); ?>">
    <?php endforeach; ?>
</datalist>

<!-- MODAL -->
<div class="modal-overlay" id="modal-insumo">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title" id="modal-titulo">Novo Insumo</div>
        <div id="form-insumo">
            <input type="hidden" id="f-id" value="">

            <label class="form-label">NOME DO INSUMO *</label>
            <input class="form-input" type="text" id="f-nome" placeholder="Ex: Encordamento para Guitarra 09-42">

            <label class="form-label" style="margin-top:10px">CATEGORIA</label>
            <input class="form-input" type="text" id="f-categoria" list="dl-cats-insumos" placeholder="Ex: Cordas, Captadores, Madeiras..." autocomplete="off">

            <div style="display:flex;gap:12px;margin-top:10px">
                <div style="flex:1">
                    <label class="form-label">MARCA</label>
                    <input class="form-input" type="text" id="f-marca" placeholder="Ex: D'Addario">
                </div>
                <div style="flex:1">
                    <label class="form-label">MODELO</label>
                    <input class="form-input" type="text" id="f-modelo" placeholder="Ex: EXL110">
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-top:10px">
                <div style="flex:1">
                    <label class="form-label">UNIDADE *</label>
                    <input class="form-input" type="text" id="f-unidade" placeholder="Ex: conjunto, metro, ml">
                </div>
                <div style="flex:1">
                    <label class="form-label">VALOR UNITÁRIO (R$)</label>
                    <input class="form-input" type="number" id="f-valor" min="0" step="0.01" placeholder="0,00">
                </div>
            </div>

            <label class="form-label" style="margin-top:10px">QUANTIDADE EM ESTOQUE</label>
            <input class="form-input" type="number" id="f-estoque" min="0" step="0.001" placeholder="0">

            <!-- Tipo do insumo -->
            <label class="form-label" style="margin-top:14px">TIPO DO INSUMO</label>
            <p style="font-size:12px;color:var(--g-text-3);margin:2px 0 4px">Fixo = já incluso no serviço (sem cobrança extra). Variável = adicionado conforme necessidade.</p>
            <div class="tipo-toggle" id="tipo-toggle">
                <button type="button" class="variavel ativo" onclick="setTipo('variavel')">⚙ Variável</button>
                <button type="button" class="fixo" onclick="setTipo('fixo')">📌 Fixo</button>
            </div>
            <input type="hidden" id="f-tipo" value="variavel">

            <div class="qtd-padrao-wrap" id="qtd-padrao-wrap" style="display:none">
                <label class="form-label" style="margin-top:10px">QUANTIDADE PADRÃO</label>
                <input class="form-input" type="number" id="f-qtd-padrao" min="0.001" step="0.001" placeholder="1" value="1">
            </div>

            <!-- Vínculo com serviços -->
            <label class="form-label" style="margin-top:16px">CATEGORIAS DE SERVIÇOS</label>
            <p style="font-size:12px;color:var(--g-text-3);margin:2px 0 6px">Selecione uma ou mais categorias para exibir os serviços relacionados.</p>
            <div class="cat-chips" id="cat-chips">
                <?php foreach ($categorias as $cat): ?>
                <div class="cat-chip" data-cat="<?php echo htmlspecialchars($cat); ?>" onclick="toggleCategoria(this)">
                    <span class="material-symbols-outlined">add</span>
                    <?php echo htmlspecialchars($cat); ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="bloco-servicos" style="display:none;margin-top:12px">
                <label class="form-label">SERVIÇOS VINCULADOS
                    <span style="font-size:11px;font-weight:400;color:var(--g-text-3);margin-left:6px">defina o tipo e qtd por serviço</span>
                </label>
                <div class="servicos-grupos" id="grupos-servicos"></div>
            </div>

            <label class="form-check" style="margin-top:14px">
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

<script>
const SERVICOS_POR_CAT = <?php echo json_encode($servicos_por_categoria, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
<script>
// vinculosMap: { servico_id: { tipo_vinculo, quantidade_padrao } }
let vinculosMap = {};
let categoriasAtivas = [];

// ── Tipo do insumo (fixo/variável) ──
function setTipo(tipo) {
    document.getElementById('f-tipo').value = tipo;
    document.querySelectorAll('#tipo-toggle button').forEach(b => b.classList.remove('ativo'));
    document.querySelector('#tipo-toggle button.' + tipo).classList.add('ativo');
    document.getElementById('qtd-padrao-wrap').style.display = tipo === 'fixo' ? 'block' : 'none';
}

// ── Chips de categoria ──
function toggleCategoria(chip) {
    const cat = chip.dataset.cat;
    const idx = categoriasAtivas.indexOf(cat);
    if (idx === -1) {
        categoriasAtivas.push(cat);
        chip.classList.add('ativa');
        chip.querySelector('.material-symbols-outlined').textContent = 'check';
    } else {
        categoriasAtivas.splice(idx, 1);
        chip.classList.remove('ativa');
        chip.querySelector('.material-symbols-outlined').textContent = 'add';
    }
    renderGrupos();
}

// ── Render lista de serviços com toggle fixo/variável + qtd ──
function renderGrupos() {
    const bloco  = document.getElementById('bloco-servicos');
    const grupos = document.getElementById('grupos-servicos');
    if (!categoriasAtivas.length) { bloco.style.display = 'none'; grupos.innerHTML = ''; return; }
    grupos.innerHTML = '';
    categoriasAtivas.forEach(cat => {
        const servicos = SERVICOS_POR_CAT[cat] || [];
        if (!servicos.length) return;
        const grupo = document.createElement('div');
        grupo.className = 'grupo-cat';
        let html = `<div class="grupo-cat-titulo">${esc(cat)}</div><div class="grupo-cat-itens">`;
        servicos.forEach(s => {
            const v    = vinculosMap[s.id] || null;
            const chk  = v ? 'checked' : '';
            const tipo = v ? v.tipo_vinculo : 'variavel';
            const qtd  = v ? v.quantidade_padrao : 1;
            const dis  = tipo === 'variavel' ? 'disabled' : '';
            const btnV = tipo === 'variavel' ? 'ativo' : '';
            const btnF = tipo === 'fixo'     ? 'ativo' : '';
            html += `
            <div class="servico-vinculo-item">
                <input type="checkbox" ${chk} onchange="toggleServico(${s.id}, this.checked)" id="sv-${s.id}">
                <label for="sv-${s.id}" style="cursor:pointer;flex:1">${esc(s.nome)}</label>
                <div class="vinculo-toggle">
                    <button type="button" class="${btnV}" onclick="setVinculoTipo(${s.id}, 'variavel', this)">Variável</button>
                    <button type="button" class="${btnF}" onclick="setVinculoTipo(${s.id}, 'fixo', this)">Fixo</button>
                </div>
                <input class="vinculo-qtd" type="number" min="0.001" step="0.001" value="${qtd}" ${dis}
                    onchange="setVinculoQtd(${s.id}, this.value)" title="Quantidade padrão">
            </div>`;
        });
        html += '</div>';
        grupo.innerHTML = html;
        grupos.appendChild(grupo);
    });
    bloco.style.display = 'block';
}

function toggleServico(id, checked) {
    if (checked) {
        if (!vinculosMap[id]) vinculosMap[id] = { tipo_vinculo: 'variavel', quantidade_padrao: 1 };
    } else {
        delete vinculosMap[id];
    }
}

function setVinculoTipo(id, tipo, btn) {
    if (!vinculosMap[id]) vinculosMap[id] = { tipo_vinculo: tipo, quantidade_padrao: 1 };
    vinculosMap[id].tipo_vinculo = tipo;
    // atualiza botões do par
    const par = btn.parentElement;
    par.querySelectorAll('button').forEach(b => b.classList.remove('ativo'));
    btn.classList.add('ativo');
    // habilita/desabilita campo qtd
    const qtdInput = par.nextElementSibling;
    if (qtdInput) qtdInput.disabled = (tipo === 'variavel');
    // auto-marca o checkbox
    const chk = document.getElementById('sv-' + id);
    if (chk) chk.checked = true;
    if (!vinculosMap[id]) vinculosMap[id] = { tipo_vinculo: tipo, quantidade_padrao: 1 };
    vinculosMap[id].tipo_vinculo = tipo;
}

function setVinculoQtd(id, val) {
    if (!vinculosMap[id]) vinculosMap[id] = { tipo_vinculo: 'fixo', quantidade_padrao: parseFloat(val) || 1 };
    vinculosMap[id].quantidade_padrao = parseFloat(val) || 1;
}

function resetModal() {
    vinculosMap = {}; categoriasAtivas = [];
    document.querySelectorAll('.cat-chip').forEach(chip => {
        chip.classList.remove('ativa');
        chip.querySelector('.material-symbols-outlined').textContent = 'add';
    });
    document.getElementById('bloco-servicos').style.display = 'none';
    document.getElementById('grupos-servicos').innerHTML = '';
    setTipo('variavel');
    document.getElementById('f-qtd-padrao').value = 1;
}

function abrirModal() {
    document.getElementById('modal-titulo').textContent = 'Novo Insumo';
    ['f-id','f-nome','f-categoria','f-marca','f-modelo','f-unidade','f-valor','f-estoque'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('f-ativo').checked = true;
    resetModal();
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
            document.getElementById('f-id').value        = ins.id;
            document.getElementById('f-nome').value      = ins.nome;
            document.getElementById('f-categoria').value = ins.categoria || '';
            document.getElementById('f-marca').value     = ins.marca     || '';
            document.getElementById('f-modelo').value    = ins.modelo    || '';
            document.getElementById('f-unidade').value   = ins.unidade;
            document.getElementById('f-valor').value     = parseFloat(ins.valorunitario || 0).toFixed(2);
            document.getElementById('f-estoque').value   = parseFloat(ins.quantidadeestoque || 0);
            document.getElementById('f-ativo').checked   = ins.ativo == 1;
            resetModal();
            setTipo(ins.tipo_insumo || 'variavel');
            document.getElementById('f-qtd-padrao').value = ins.quantidade_padrao || 1;
            // reconstrói vinculosMap a partir de ins.vinculos
            (ins.vinculos || []).forEach(v => {
                vinculosMap[parseInt(v.servico_id)] = {
                    tipo_vinculo:     v.tipo_vinculo     || 'variavel',
                    quantidade_padrao: parseFloat(v.quantidade_padrao) || 1
                };
            });
            // ativa chips das categorias que têm serviços vinculados
            Object.entries(SERVICOS_POR_CAT).forEach(([cat, servicos]) => {
                if (servicos.some(s => vinculosMap[s.id] !== undefined)) {
                    categoriasAtivas.push(cat);
                    const chip = document.querySelector(`.cat-chip[data-cat="${CSS.escape(cat)}"]`);
                    if (chip) { chip.classList.add('ativa'); chip.querySelector('.material-symbols-outlined').textContent = 'check'; }
                }
            });
            renderGrupos();
            document.getElementById('modal-insumo').classList.add('aberto');
        });
}

function salvarInsumo() {
    const id        = document.getElementById('f-id').value;
    const nome      = document.getElementById('f-nome').value.trim();
    const categoria = document.getElementById('f-categoria').value.trim();
    const marca     = document.getElementById('f-marca').value.trim();
    const modelo    = document.getElementById('f-modelo').value.trim();
    const unidade   = document.getElementById('f-unidade').value.trim();
    const valor     = document.getElementById('f-valor').value;
    const estoque   = document.getElementById('f-estoque').value;
    const ativo     = document.getElementById('f-ativo').checked ? 1 : 0;
    const tipo      = document.getElementById('f-tipo').value;
    const qtdPadrao = document.getElementById('f-qtd-padrao').value || 1;

    if (!nome || !unidade) { alert('Nome e unidade são obrigatórios.'); return; }

    // monta array de vinculos
    const vinculos = Object.entries(vinculosMap).map(([sid, v]) => ({
        servico_id:       parseInt(sid),
        tipo_vinculo:     v.tipo_vinculo,
        quantidade_padrao: v.quantidade_padrao
    }));

    const payload = {
        nome, categoria, marca, modelo, unidade,
        valorunitario: valor,
        quantidadeestoque: estoque,
        tipo_insumo: tipo,
        quantidade_padrao: qtdPadrao,
        ativo,
        vinculos
    };

    const method = id ? 'PUT' : 'POST';
    const url    = id ? 'insumos-api.php?id=' + id : 'insumos-api.php';

    fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(r => r.json())
        .then(data => {
            if (data.ok) location.href = 'insumos.php?msg=sucesso:Insumo ' + (id ? 'atualizado' : 'criado') + ' com sucesso!';
            else alert('Erro: ' + data.erro);
        });
}

function toggleAtivo(id, ativo) {
    if (!confirm((ativo ? 'Desativar' : 'Reativar') + ' este insumo?')) return;
    if (ativo) {
        fetch('insumos-api.php?id=' + id, { method: 'DELETE' })
            .then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert('Erro: ' + d.erro); });
    } else {
        fetch('insumos-api.php?id=' + id, { method: 'PUT', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ativo:1,nome:'_reativar_',unidade:'_reativar_'}) })
            .then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert('Erro: ' + d.erro); });
    }
}

function excluirInsumo(id, nome) {
    if (!confirm('Excluir o insumo "' + nome + '" permanentemente? Esta ação não pode ser desfeita.')) return;
    fetch('insumos-api.php?id=' + id + '&excluir=1', { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.ok) location.href = 'insumos.php?msg=sucesso:Insumo excluído com sucesso.';
            else alert('Erro ao excluir: ' + data.erro);
        });
}

function fecharModal() { document.getElementById('modal-insumo').classList.remove('aberto'); }
document.getElementById('modal-insumo').addEventListener('click', function(e) { if (e.target === this) fecharModal(); });
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
</body>
</html>
