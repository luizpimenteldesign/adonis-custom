<?php
/**
 * DASHBOARD — SISTEMA ADONIS
 * Visual: Google / Material Design 3
 * Ícones: Material Symbols Outlined
 * Versão: 10.0 — CSS externo
 */
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$filtro_status = $_GET['status'] ?? '';
$busca         = trim($_GET['q'] ?? '');

try {
    $rows = $conn->query("SELECT status, COUNT(*) as total FROM pre_os GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    $stats_map = [];
    foreach ($rows as $r) $stats_map[$r['status']] = (int)$r['total'];
    $stats['total']     = array_sum($stats_map);
    $stats['pendentes'] = ($stats_map['Pre-OS'] ?? 0) + ($stats_map['Em analise'] ?? 0);
    $stats['orcadas']   = ($stats_map['Orcada'] ?? 0) + ($stats_map['Aguardando aprovacao'] ?? 0);
    $stats['execucao']  = ($stats_map['Aprovada'] ?? 0) + ($stats_map['Pagamento recebido'] ?? 0)
                        + ($stats_map['Instrumento recebido'] ?? 0) + ($stats_map['Servico iniciado'] ?? 0)
                        + ($stats_map['Em desenvolvimento'] ?? 0);
} catch (Exception $e) {
    $stats = ['total'=>0,'pendentes'=>0,'orcadas'=>0,'execucao'=>0];
    $stats_map = [];
}

try {
    $where  = [];
    $params = [];
    if ($filtro_status) { $where[] = 'p.status = :status'; $params[':status'] = $filtro_status; }
    if ($busca) {
        $where[]      = '(c.nome LIKE :q OR c.telefone LIKE :q OR i.tipo LIKE :q OR i.marca LIKE :q OR i.modelo LIKE :q OR p.id LIKE :q)';
        $params[':q'] = '%'.$busca.'%';
    }
    $sql = "SELECT p.id, p.status, p.criado_em, p.atualizado_em, p.valor_orcamento, p.prazo_orcamento,
                   c.nome as cliente_nome, c.telefone,
                   i.tipo as instr_tipo, i.marca as instr_marca, i.modelo as instr_modelo
            FROM pre_os p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN instrumentos i ON p.instrumento_id = i.id"
           .($where ? ' WHERE '.implode(' AND ',$where) : '')
           ." ORDER BY p.atualizado_em DESC LIMIT 200";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pedidos = [];
}

$totais_servicos = [];
try {
    $ts = $conn->query("SELECT ps.pre_os_id, SUM(s.valor_base) as total FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id = s.id GROUP BY ps.pre_os_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ts as $t) $totais_servicos[$t['pre_os_id']] = (float)$t['total'];
} catch (Exception $e) {}

// icone = nome do Material Symbol
$status_map = [
    'Pre-OS'                        => ['label'=>'Pré-OS',                 'badge'=>'badge-new',     'icone'=>'note_add'],
    'Em analise'                    => ['label'=>'Em Análise',             'badge'=>'badge-info',    'icone'=>'search'],
    'Orcada'                        => ['label'=>'Orçada',                 'badge'=>'badge-warning', 'icone'=>'request_quote'],
    'Aguardando aprovacao'          => ['label'=>'Aguard. Aprovação',     'badge'=>'badge-warning', 'icone'=>'hourglass_empty'],
    'Aprovada'                      => ['label'=>'Aguard. Pagamento',      'badge'=>'badge-success', 'icone'=>'credit_card'],
    'Pagamento recebido'            => ['label'=>'Pagamento Recebido',     'badge'=>'badge-success', 'icone'=>'check_circle'],
    'Instrumento recebido'          => ['label'=>'Instrumento Recebido',   'badge'=>'badge-success', 'icone'=>'inventory_2'],
    'Servico iniciado'              => ['label'=>'Serviço Iniciado',       'badge'=>'badge-purple',  'icone'=>'build'],
    'Em desenvolvimento'            => ['label'=>'Em Desenvolvimento',     'badge'=>'badge-purple',  'icone'=>'settings'],
    'Servico finalizado'            => ['label'=>'Serviço Finalizado',     'badge'=>'badge-success', 'icone'=>'done_all'],
    'Pronto para retirada'          => ['label'=>'Pronto p/ Retirada',     'badge'=>'badge-warning', 'icone'=>'store'],
    'Aguardando pagamento retirada' => ['label'=>'Pag. Pendente Retirada', 'badge'=>'badge-warning', 'icone'=>'payments'],
    'Entregue'                      => ['label'=>'Entregue',               'badge'=>'badge-dark',    'icone'=>'verified'],
    'Reprovada'                     => ['label'=>'Reprovada',              'badge'=>'badge-danger',  'icone'=>'cancel'],
    'Cancelada'                     => ['label'=>'Cancelada',              'badge'=>'badge-dark',    'icone'=>'block'],
];

$acoes_por_status = [
    'Pre-OS'               => [['Em analise','Iniciar Análise','btn-info','modal-analise'],['Reprovada','Reprovar','btn-danger','modal-rep'],['Cancelada','Cancelar','btn-dark']],
    'Em analise'           => [['Em analise','Rever Insumos','btn-info','modal-analise'],['Reprovada','Reprovar','btn-danger','modal-rep'],['Cancelada','Cancelar','btn-dark']],
    'Orcada'               => [['Aguardando aprovacao','Aguardar Aprovação','btn-warning'],['Reprovada','Reprovar','btn-danger','modal-rep'],['Cancelada','Cancelar','btn-dark']],
    'Aguardando aprovacao' => [['Aprovada','Marcar Aprovada','btn-success'],['Reprovada','Reprovar','btn-danger','modal-rep'],['Cancelada','Cancelar','btn-dark']],
    'Aprovada'             => [['Pagamento recebido','Pagamento Recebido','btn-success'],['Cancelada','Cancelar','btn-dark']],
    'Pagamento recebido'   => [['Instrumento recebido','Instrumento Recebido','btn-success'],['Cancelada','Cancelar','btn-dark']],
    'Instrumento recebido' => [['Servico iniciado','Iniciar Serviço','btn-purple']],
    'Servico iniciado'     => [['Em desenvolvimento','Em Desenvolvimento','btn-purple']],
    'Em desenvolvimento'   => [['Servico finalizado','Serviço Finalizado','btn-success']],
    'Servico finalizado'   => [['Pronto para retirada','Pronto p/ Retirada','btn-warning'],['Aguardando pagamento retirada','Aguardar Pag. Retirada','btn-warning']],
    'Pronto para retirada' => [['Entregue','Entregue','btn-dark']],
    'Aguardando pagamento retirada' => [['Entregue','Entregue','btn-dark']],
    'Entregue'             => [],
    'Reprovada'            => [['Em analise','Reabrir — Rever Insumos','btn-info','modal-analise']],
    'Cancelada'            => [['Pre-OS','Reabrir','btn-info']],
];

$filtros_chips = [
    ''                     => 'Todos',
    'Pre-OS'               => 'Pré-OS',
    'Em analise'           => 'Em Análise',
    'Orcada'               => 'Orçadas',
    'Aguardando aprovacao' => 'Aguard. Aprovação',
    'Aprovada'             => 'Aguard. Pgto',
    'Em desenvolvimento'   => 'Em Execução',
    'Pronto para retirada' => 'Pronto',
    'Entregue'             => 'Entregues',
    'Reprovada'            => 'Reprovadas',
    'Cancelada'            => 'Canceladas',
];

$current_page = 'dashboard.php';
require_once '_sidebar_data.php';

function badge($s,$m){
    $i = $m[$s] ?? ['label'=>$s,'badge'=>'badge-secondary','icone'=>'circle'];
    return '<span class="badge '.$i['badge'].'"><span class="material-symbols-outlined" style="font-size:11px;vertical-align:middle">'.$i['icone'].'</span> '.$i['label'].'</span>';
}
function iniciais($nome){$parts=array_filter(explode(' ',trim($nome??'')));if(count($parts)>=2) return strtoupper(substr($parts[0],0,1).substr(end($parts),0,1));return strtoupper(substr($parts[0]??'?',0,2));}

$v = time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adonis Admin — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo $v; ?>">
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="app-layout">

<?php require_once '_sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <div style="display:flex;align-items:center;gap:8px">
            <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png" style="height:26px" alt="Adonis">
        </div>
        <span class="topbar-title">Painel</span>
        <a href="logout.php" class="material-symbols-outlined sidebar-logout" title="Sair">logout</a>
    </div>

    <div class="page-content">

        <!-- STATS -->
        <div class="stats-row">
            <div class="stat-chip blue" onclick="location.href='dashboard.php'">
                <span class="material-symbols-outlined stat-chip-icon">list_alt</span>
                <div><div class="stat-chip-val"><?php echo $stats['total']; ?></div><div class="stat-chip-lbl">Total</div></div>
            </div>
            <div class="stat-chip yellow" onclick="location.href='dashboard.php?status=Pre-OS'">
                <span class="material-symbols-outlined stat-chip-icon">pending_actions</span>
                <div><div class="stat-chip-val"><?php echo $stats['pendentes']; ?></div><div class="stat-chip-lbl">Pendentes</div></div>
            </div>
            <div class="stat-chip orange" onclick="location.href='dashboard.php?status=Orcada'">
                <span class="material-symbols-outlined stat-chip-icon">request_quote</span>
                <div><div class="stat-chip-val"><?php echo $stats['orcadas']; ?></div><div class="stat-chip-lbl">Orçadas</div></div>
            </div>
            <div class="stat-chip green" onclick="location.href='dashboard.php?status=Em desenvolvimento'">
                <span class="material-symbols-outlined stat-chip-icon">build</span>
                <div><div class="stat-chip-val"><?php echo $stats['execucao']; ?></div><div class="stat-chip-lbl">Em Execução</div></div>
            </div>
        </div>

        <!-- BUSCA -->
        <div style="margin-bottom:12px">
            <div class="search-bar">
                <span class="search-icon material-symbols-outlined">search</span>
                <input type="text"
                       id="input-busca"
                       placeholder="Buscar por cliente, instrumento, ID..."
                       value="<?php echo htmlspecialchars($busca); ?>"
                       autocomplete="off">
                <div class="search-loading" id="search-spinner"></div>
                <?php if ($busca): ?>
                <button type="button" onclick="limparBusca()" style="background:none;border:none;cursor:pointer;color:var(--g-text-3);padding:0 4px;display:flex;align-items:center" title="Limpar">
                    <span class="material-symbols-outlined" style="font-size:18px">close</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- CHIPS DE FILTRO -->
        <div class="chips-row" style="margin-bottom:16px">
            <?php foreach ($filtros_chips as $val => $label): ?>
            <a href="dashboard.php?status=<?php echo urlencode($val); ?><?php echo $busca ? '&q='.urlencode($busca) : ''; ?>"
               class="chip<?php echo ($filtro_status === $val) ? ' active' : ''; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <!-- LISTA + PAINEL -->
        <div class="dashboard-grid">
            <div class="pedido-list-wrap">
                <div class="pedido-list-header">
                    <?php echo count($pedidos); ?> pedido<?php echo count($pedidos) !== 1 ? 's' : ''; ?>
                    <?php if ($filtro_status): ?> &middot; <strong><?php echo htmlspecialchars($filtros_chips[$filtro_status] ?? $filtro_status); ?></strong><?php endif; ?>
                    <?php if ($busca): ?> &middot; &ldquo;<?php echo htmlspecialchars($busca); ?>&rdquo;<?php endif; ?>
                </div>

                <?php if (empty($pedidos)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><span class="material-symbols-outlined">inbox</span></div>
                    <div class="empty-state-title">Nenhum pedido encontrado</div>
                    <div class="empty-state-sub"><?php echo ($busca || $filtro_status) ? 'Tente outro filtro ou termo de busca' : 'Nenhuma pré-OS cadastrada ainda'; ?></div>
                </div>
                <?php else: ?>
                <?php foreach ($pedidos as $p):
                    $info   = $status_map[$p['status']] ?? ['label'=>$p['status'],'badge'=>'badge-secondary','icone'=>'circle'];
                    $ini    = iniciais($p['cliente_nome'] ?? '?');
                    $instr  = trim(($p['instr_tipo']??'').' '.($p['instr_marca']??'').' '.($p['instr_modelo']??''));
                    $data   = $p['atualizado_em'] ? date('d/m', strtotime($p['atualizado_em'])) : '–';
                    $tv     = $totais_servicos[$p['id']] ?? 0;
                    $orc    = (float)($p['valor_orcamento']??0);
                    // Dados serializados com segurança em data-* attributes
                    $data_payload = htmlspecialchars(json_encode([
                        'id'       => (int)$p['id'],
                        'nome'     => $p['cliente_nome'] ?? 'Sem nome',
                        'instr'    => $instr ?: '–',
                        'status'   => $p['status'],
                        'acoes'    => $acoes_por_status[$p['status']] ?? [],
                        'tv'       => $tv,
                        'orc'      => $orc,
                        'telefone' => $p['telefone'] ?? '',
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                ?>
                <div class="pedido-item" id="pedido-<?php echo $p['id']; ?>" data-pedido="<?php echo $data_payload; ?>">
                    <div class="pedido-avatar"><?php echo $ini; ?></div>
                    <div class="pedido-body">
                        <div class="pedido-row1">
                            <span class="pedido-nome"><?php echo htmlspecialchars($p['cliente_nome'] ?? 'Sem nome'); ?></span>
                            <span class="pedido-data"><?php echo $data; ?></span>
                        </div>
                        <div class="pedido-row2">
                            <span class="pedido-instrumento"><?php echo htmlspecialchars($instr ?: 'Instrumento não informado'); ?></span>
                            <?php echo badge($p['status'], $status_map); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="acao-panel" id="acao-panel">
                <div id="acao-panel-content"></div>
            </div>
        </div>

        <div style="height:24px"></div>
    </div>
</main>
</div>

<!-- BOTTOM NAV mobile -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="<?php echo $filtro_status==='' ? 'active' : ''; ?>">
        <span class="material-symbols-outlined nav-icon">dashboard</span>Painel
    </a>
    <a href="dashboard.php?status=Pre-OS" class="<?php echo $filtro_status==='Pre-OS' ? 'active' : ''; ?>">
        <span class="material-symbols-outlined nav-icon">note_add</span>Pré-OS
    </a>
    <a href="dashboard.php?status=Em desenvolvimento" class="<?php echo $filtro_status==='Em desenvolvimento' ? 'active' : ''; ?>">
        <span class="material-symbols-outlined nav-icon">build</span>Execução
    </a>
    <a href="logout.php">
        <span class="material-symbols-outlined nav-icon">logout</span>Sair
    </a>
</nav>

<!-- SHEET MOBILE -->
<div class="acao-sheet" id="acao-sheet">
    <div class="acao-sheet-overlay" onclick="fecharSheet()"></div>
    <div class="acao-sheet-box">
        <div class="acao-sheet-drag"></div>
        <div id="acao-sheet-content" style="padding:4px 0 8px"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL ANÁLISE DE INSUMOS
═══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-analise">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-drag"></div>
        <div class="modal-title">Análise de Insumos</div>
        <div id="analise-corpo">
            <div class="analise-loading">Carregando insumos...</div>
        </div>
        <div class="modal-actions" id="analise-acoes" style="display:none">
            <button class="btn btn-secondary" onclick="fecharModal('modal-analise')">Cancelar</button>
            <button class="btn btn-info" id="btn-confirmar-analise" onclick="confirmarAnalise()">Confirmar e Orçar →</button>
        </div>
    </div>
</div>

<!-- MODAL ORÇAMENTO -->
<div class="modal-overlay" id="modal-orcamento">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title">Definir Orçamento</div>
        <label>Valor total (serviços + insumos) — R$</label>
        <input type="number" id="modal-input-valor" min="0" step="0.01" placeholder="Ex: 350.00" oninput="simularModal()">
        <div id="orc-breakdown" style="font-size:12px;color:var(--g-text-3);margin:4px 0 12px;padding:0 2px"></div>
        <label>Prazo (dias úteis)</label>
        <input type="number" id="modal-input-prazo" min="1" step="1" placeholder="Ex: 7">
        <div class="modal-hint">Sem sábados, domingos e feriados</div>
        <hr class="sim-sep">
        <div class="sim-titulo">Simulação — escolha o valor</div>
        <div class="sim-cards">
            <div class="sim-card" id="modal-card-base" onclick="escolherModalValor('base')"><div class="sim-card-label">Valor Base</div><div class="sim-card-valor" id="modal-sim-base">&mdash;</div><div class="sim-card-sub">Sem taxa de máquina</div></div>
            <div class="sim-card maquina" id="modal-card-maquina" onclick="escolherModalValor('maquina')"><div class="sim-card-label">Valor Máquina (10x)</div><div class="sim-card-valor" id="modal-sim-maq">&mdash;</div><div class="sim-card-sub" id="modal-sim-maq-sub">Pior caso: Elo/Amex 10x</div></div>
        </div>
        <div class="sim-aviso" id="modal-sim-aviso" style="display:none"></div>
        <input type="hidden" id="modal-valor-final">
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="fecharModal('modal-orcamento')">Cancelar</button>
            <button class="btn btn-warning" id="modal-btn-orc" onclick="confirmarModalOrcamento()" disabled>Enviar Orçamento</button>
        </div>
    </div>
</div>

<!-- MODAL REPROVAÇÃO -->
<div class="modal-overlay" id="modal-reprovacao">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title">Motivo da Reprovação</div>
        <label>Descreva o motivo</label>
        <textarea id="modal-motivo" placeholder="Ex: Peça indisponível..."></textarea>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="fecharModal('modal-reprovacao')">Cancelar</button>
            <button class="btn btn-danger" onclick="confirmarModalReprovacao()">Confirmar</button>
        </div>
    </div>
</div>

<!-- MODAL WHATSAPP -->
<div class="modal-overlay" id="modal-wa">
    <div class="modal-box" style="max-width:420px;text-align:center">
        <div class="modal-drag"></div>
        <div class="modal-title">Avisar o cliente?</div>
        <div style="font-size:14px;color:var(--g-text-2);margin-bottom:20px" id="wa-texto">Status atualizado!</div>
        <a id="btn-wa" href="#" target="_blank" class="btn-wa" onclick="_waReload()">
            <span class="material-symbols-outlined">chat</span> WhatsApp
        </a>
        <button class="btn-wa-skip" onclick="_waReload()">Pular — recarregar</button>
    </div>
</div>

<script>
const _statusMap = <?php echo json_encode(array_map(fn($v)=>$v['label'], $status_map), JSON_UNESCAPED_UNICODE); ?>;
const _badgeMap  = <?php echo json_encode(array_map(fn($v)=>['badge'=>$v['badge'],'icone'=>$v['icone'],'label'=>$v['label']], $status_map), JSON_UNESCAPED_UNICODE); ?>;

function iconHtml(name, size){
    return `<span class="material-symbols-outlined" style="font-size:${size||16}px;vertical-align:middle">${name}</span>`;
}

// ─ Sidebar mobile
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar(){
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}

// ─ Grupos colapsáveis
function toggleGroup(id){
    const toggle = document.getElementById('toggle-' + id);
    const sub    = document.getElementById('sub-'    + id);
    toggle.classList.toggle('open');
    sub.classList.toggle('open');
    const estado = JSON.parse(localStorage.getItem('nav_grupos') || '{}');
    estado[id] = toggle.classList.contains('open');
    localStorage.setItem('nav_grupos', JSON.stringify(estado));
}

document.addEventListener('DOMContentLoaded', () => {
    // Restaurar estado dos grupos
    const estado = JSON.parse(localStorage.getItem('nav_grupos') || '{}');
    for (const [id, aberto] of Object.entries(estado)) {
        const toggle = document.getElementById('toggle-' + id);
        const sub    = document.getElementById('sub-'    + id);
        if (!toggle || !sub) continue;
        if (aberto && !toggle.classList.contains('open')) {
            toggle.classList.add('open');
            sub.classList.add('open');
        }
    }

    // ─ Event delegation para clique nos pedidos (evita quebra por caracteres especiais)
    document.querySelector('.pedido-list-wrap')?.addEventListener('click', function(e){
        const item = e.target.closest('.pedido-item[data-pedido]');
        if (!item) return;
        try {
            const d = JSON.parse(item.dataset.pedido);
            abrirAcoes(d.id, d.nome, d.instr, d.status, d.acoes, d.tv, d.orc, d.telefone);
        } catch(err) {
            console.error('Erro ao parsear pedido:', err);
        }
    });
});

// ─ BUSCA LIVE — debounce 400ms + Enter imediato
(function() {
    const input   = document.getElementById('input-busca');
    const spinner = document.getElementById('search-spinner');
    const status  = <?php echo json_encode($filtro_status); ?>;
    let timer = null;

    function doSearch(q) {
        spinner.classList.add('active');
        let url = 'dashboard.php';
        const params = [];
        if (status)        params.push('status=' + encodeURIComponent(status));
        if (q.trim())      params.push('q='      + encodeURIComponent(q.trim()));
        if (params.length) url += '?' + params.join('&');
        window.location.href = url;
    }

    input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(() => doSearch(this.value), 400);
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            doSearch(this.value);
        }
    });
})();

function limparBusca(){
    const s = <?php echo json_encode($filtro_status); ?>;
    location.href = 'dashboard.php' + (s ? '?status=' + encodeURIComponent(s) : '');
}

// ─ Painel de ações
let _idAtual = null, _telefoneAtual = '';
let _orcEscolhido = null;

function abrirAcoes(id, nome, instr, status, acoes, totalBase, orcAtual, telefone){
    document.querySelectorAll('.pedido-item.selected').forEach(el=>el.classList.remove('selected'));
    document.getElementById('pedido-'+id)?.classList.add('selected');
    _idAtual = id;
    _telefoneAtual = telefone;

    const bmap = _badgeMap[status] ?? {badge:'badge-secondary',icone:'circle',label:status};
    const badgeHtml = `<span class="badge ${bmap.badge}">${iconHtml(bmap.icone,11)} ${bmap.label}</span>`;
    const orcHtml   = orcAtual > 0 ? `<div class="acao-panel-orc">${iconHtml('payments',16)} R$ ${fmt(orcAtual)}</div>` : '';
    const acoesHtml = renderAcoes(acoes, totalBase, status);

    document.getElementById('acao-panel-content').innerHTML = `
        <div class="acao-panel-header">
            <div>
                <div class="acao-panel-nome">${escHtml(nome)}</div>
                <div class="acao-panel-instr">${escHtml(instr)}</div>
            </div>
            <button class="acao-panel-close" onclick="fecharPainel()">${iconHtml('close',16)}</button>
        </div>
        <div class="acao-panel-status">${badgeHtml}</div>
        ${orcHtml}
        ${acoesHtml}
        <div class="acao-panel-link"><a href="detalhes.php?id=${id}">Ver detalhes completos →</a></div>`;
    document.getElementById('acao-panel').classList.add('visible');

    if(window.innerWidth < 960){
        document.getElementById('acao-sheet-content').innerHTML = `
            <div style="padding:16px 20px 8px">
                <div style="font-family:'Google Sans',sans-serif;font-size:16px;font-weight:500;color:var(--g-text)">${escHtml(nome)}</div>
                <div style="font-size:12px;color:var(--g-text-2);margin:2px 0 10px">${escHtml(instr)}</div>
                ${badgeHtml}
                ${orcAtual > 0 ? `<div style="margin-top:8px;font-size:13px;color:var(--g-green);font-weight:500">${iconHtml('payments',14)} R$ ${fmt(orcAtual)}</div>` : ''}
            </div>
            ${renderAcoes(acoes, totalBase, status)}
            <div style="padding:12px 20px">
                <a href="detalhes.php?id=${id}" class="btn btn-secondary" style="width:100%;justify-content:center">Ver detalhes →</a>
            </div>`;
        document.getElementById('acao-sheet').classList.add('open');
    }
}

function fecharPainel(){
    document.getElementById('acao-panel').classList.remove('visible');
    document.querySelectorAll('.pedido-item.selected').forEach(el=>el.classList.remove('selected'));
    _idAtual = null;
}
function fecharSheet(){
    document.getElementById('acao-sheet').classList.remove('open');
    document.querySelectorAll('.pedido-item.selected').forEach(el=>el.classList.remove('selected'));
    _idAtual = null;
}

function renderAcoes(acoes, totalBase, status){
    if(!acoes||acoes.length===0)
        return `<div class="acao-section"><div style="font-size:13px;color:var(--g-text-3);padding:8px 0">Nenhuma ação disponível.</div></div>`;
    let html=`<div class="acao-section"><div class="acao-section-label">Ações disponíveis</div><div class="acao-btns">`;
    for(const a of acoes){
        const[s,label,cls,modal]=a;
        if(modal==='modal-analise') html+=`<button class="btn ${cls}" onclick="_abrirAnalise()">${label}</button>`;
        else if(modal==='modal-rep') html+=`<button class="btn ${cls}" onclick="_abrirReprovacao()">${label}</button>`;
        else                         html+=`<button class="btn ${cls}" onclick="_enviar('${s.replace(/'/g,"\\\\'")}')">${label}</button>`;
    }
    html+=`</div></div>`;
    return html;
}

// ── MODAL ANÁLISE ────────────────────────────────────────────
let _insumos = [];

function _abrirAnalise() {
    if (!_idAtual) return;
    document.getElementById('analise-corpo').innerHTML = '<div class="analise-loading">Carregando insumos...</div>';
    document.getElementById('analise-acoes').style.display = 'none';
    document.getElementById('modal-analise').classList.add('aberto');

    fetch('analise_insumos.php?pre_os_id=' + _idAtual)
        .then(r => r.json())
        .then(data => {
            if (!data.sucesso) { _toast(data.erro || 'Erro ao carregar'); fecharModal('modal-analise'); return; }
            _renderizarAnalise(data);
        })
        .catch(() => { _toast('Erro de conexão'); fecharModal('modal-analise'); });
}

function _renderizarAnalise(data) {
    _insumos = (data.insumos || []).map(i => ({ ...i }));

    let html = '';

    // Resumo do pedido
    html += '<div class="analise-resumo">';
    html += '<div class="analise-resumo-title">Serviços do pedido</div>';
    html += '<div class="analise-resumo-tags">';
    if (data.servicos && data.servicos.length) {
        data.servicos.forEach(s => { html += '<span class="analise-tag">' + escHtml(s.nome) + '</span>'; });
    } else {
        html += '<span style="font-size:13px;color:var(--g-text-3)">Nenhum serviço</span>';
    }
    html += '</div></div>';
    html += '<hr class="analise-sep">';

    // Lista de insumos
    html += '<div class="analise-insumos-title">Insumos necessários</div>';

    if (!_insumos.length) {
        html += '<div class="analise-vazio">Nenhum insumo vinculado a estes serviços. Você pode prosseguir para o orçamento.</div>';
    } else {
        _insumos.forEach((ins, idx) => {
            const semEstoque = parseFloat(ins.quantidade_estoque) <= 0;
            const cf = ins.cliente_fornece == 1;
            const valorTotal = parseFloat(ins.valor_unitario) * parseFloat(ins.quantidade || 1);

            html += `<div class="analise-insumo-row" id="row-ins-${idx}">`;

            // Top: info + qtd + valor
            html += `<div class="analise-insumo-top">`;
            html += `<div class="analise-insumo-info">`;
            html += `<div class="analise-insumo-nome">${escHtml(ins.nome)}</div>`;
            html += `<div class="analise-insumo-meta">`;
            if (ins.servicos_origem) html += `Vinculado: ${escHtml(ins.servicos_origem)} &bull; `;
            html += `${escHtml(ins.unidade)}`;
            if (semEstoque) html += ` &bull; <span class="sem-estoque">Sem estoque</span>`;
            html += `</div></div>`;

            html += `<input type="number" class="analise-qtd" value="${parseFloat(ins.quantidade||1)}" min="0.001" step="0.001"
                onchange="_mudarQtd(${idx}, this.value)" title="Quantidade">`;

            html += `<div class="analise-insumo-valor ${cf ? 'riscado' : ''}" id="val-ins-${idx}">${fmt(cf ? 0 : valorTotal)}</div>`;
            html += `</div>`; // top

            // Bottom: checkbox cliente fornece
            html += `<div class="analise-insumo-bottom">`;
            html += `<label class="analise-cf-toggle">`;
            html += `<input type="checkbox" onchange="_toggleCF(${idx}, this.checked)" ${cf ? 'checked' : ''}>`;
            html += `<span>Cliente fornece</span></label>`;
            html += `</div>`; // bottom

            html += `</div>`; // row
        });
    }

    // Footer com total
    html += '<div class="analise-footer">';
    html += '<div class="analise-total-bloco">Insumos a cobrar:<strong id="analise-total-ins">—</strong></div>';
    html += '</div>';

    document.getElementById('analise-corpo').innerHTML = html;
    document.getElementById('analise-acoes').style.display = 'flex';
    _recalcularTotalAnalise();
}

function _toggleCF(idx, checked) {
    _insumos[idx].cliente_fornece = checked ? 1 : 0;
    const valEl = document.getElementById('val-ins-' + idx);
    if (valEl) {
        const total = parseFloat(_insumos[idx].valor_unitario) * parseFloat(_insumos[idx].quantidade || 1);
        valEl.textContent = checked ? fmt(0) : fmt(total);
        valEl.classList.toggle('riscado', checked);
    }
    _recalcularTotalAnalise();
}

function _mudarQtd(idx, val) {
    const qtd = Math.max(0.001, parseFloat(val) || 1);
    _insumos[idx].quantidade = qtd;
    const cf = _insumos[idx].cliente_fornece == 1;
    const total = parseFloat(_insumos[idx].valor_unitario) * qtd;
    const valEl = document.getElementById('val-ins-' + idx);
    if (valEl) { valEl.textContent = cf ? fmt(0) : fmt(total); }
    _recalcularTotalAnalise();
}

function _recalcularTotalAnalise() {
    let total = 0;
    _insumos.forEach(ins => {
        if (!ins.cliente_fornece) total += parseFloat(ins.valor_unitario) * parseFloat(ins.quantidade || 1);
    });
    const el = document.getElementById('analise-total-ins');
    if (el) el.textContent = fmt(total);
}

function confirmarAnalise() {
    const btn = document.getElementById('btn-confirmar-analise');
    btn.disabled = true; btn.textContent = 'Salvando...';

    fetch('analise_insumos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pre_os_id: _idAtual, insumos: _insumos })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.sucesso) { _toast(data.erro || 'Erro ao salvar'); btn.disabled = false; btn.textContent = 'Confirmar e Orçar →'; return; }
        fecharModal('modal-analise');
        // Abre modal orçamento já com valor pré-calculado
        _abrirOrcamentoComValor(data.total_orcamento, data.total_servicos, data.total_insumos);
    })
    .catch(() => { _toast('Erro de conexão'); btn.disabled = false; btn.textContent = 'Confirmar e Orçar →'; });
}

// ─ Orçamento
function taxaMaq(v){ return v>2000?15.38:21.58; }
function fmt(v){ return 'R$\u00a0'+Number(v).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
let _orcTipoEscolhido=null;

function _abrirOrcamentoComValor(totalOrc, totalSrv, totalIns) {
    _orcTipoEscolhido=null;
    document.getElementById('modal-input-valor').value=totalOrc>0?Number(totalOrc).toFixed(2):'';
    document.getElementById('modal-input-prazo').value='';
    document.getElementById('modal-valor-final').value='';
    document.getElementById('modal-btn-orc').disabled=true;
    document.getElementById('modal-sim-aviso').style.display='none';
    ['modal-card-base','modal-card-maquina'].forEach(id=>document.getElementById(id)?.classList.remove('ativo'));

    // Mostra breakdown
    let bd = document.getElementById('orc-breakdown');
    if (totalSrv >= 0 && totalIns >= 0) {
        bd.innerHTML = 'Serviços: ' + fmt(totalSrv) + ' + Insumos: ' + fmt(totalIns);
    } else { bd.textContent = ''; }

    simularModal();
    document.getElementById('modal-orcamento').classList.add('aberto');
    setTimeout(()=>document.getElementById('modal-input-valor').focus(),150);
}

function simularModal(){
    const v=parseFloat(document.getElementById('modal-input-valor').value);
    if(isNaN(v)||v<=0){document.getElementById('modal-sim-base').textContent='—';document.getElementById('modal-sim-maq').textContent='—';return;}
    const taxa=taxaMaq(v); const inteiro=Math.ceil(v*(1+taxa/100)); const real=inteiro/(1+taxa/100);
    document.getElementById('modal-sim-base').textContent=fmt(v);
    document.getElementById('modal-sim-maq').textContent=fmt(inteiro);
    document.getElementById('modal-sim-maq-sub').innerHTML=`Elo/Amex 10x (${taxa.toFixed(2)}%)<br>Digitar ${fmt(real)} na máquina`;
    if(_orcTipoEscolhido==='base') document.getElementById('modal-valor-final').value=v.toFixed(2);
    if(_orcTipoEscolhido==='maquina') document.getElementById('modal-valor-final').value=inteiro.toFixed(2);
    if(_orcTipoEscolhido) _atualizarAvisoModal(v);
}

function escolherModalValor(tipo){
    const v=parseFloat(document.getElementById('modal-input-valor').value);
    if(isNaN(v)||v<=0){_toast('Informe o valor primeiro');return;}
    _orcTipoEscolhido=tipo;
    document.getElementById('modal-card-base').classList.toggle('ativo',tipo==='base');
    document.getElementById('modal-card-maquina').classList.toggle('ativo',tipo==='maquina');
    const taxa=taxaMaq(v); const inteiro=Math.ceil(v*(1+taxa/100));
    document.getElementById('modal-valor-final').value=(tipo==='base'?v:inteiro).toFixed(2);
    _atualizarAvisoModal(v);
    document.getElementById('modal-btn-orc').disabled=false;
}

function _atualizarAvisoModal(v){
    const taxa=taxaMaq(v); const inteiro=Math.ceil(v*(1+taxa/100)); const real=inteiro/(1+taxa/100);
    const el=document.getElementById('modal-sim-aviso'); el.style.display='block';
    el.innerHTML=_orcTipoEscolhido==='base'
        ?`<strong>Enviando ao cliente: ${fmt(v)}</strong>`
        :`<strong>Enviando ao cliente: ${fmt(inteiro)}</strong><br>Digitar na máquina: <strong>${fmt(real)}</strong> em <strong>10x</strong>.`;
}

function confirmarModalOrcamento(){
    const vf=parseFloat(document.getElementById('modal-valor-final').value);
    const pr=parseInt(document.getElementById('modal-input-prazo').value);
    if(isNaN(vf)||vf<=0){_toast('Escolha o valor a enviar');return;}
    if(isNaN(pr)||pr<=0){_toast('Informe o prazo');return;}
    fecharModal('modal-orcamento');
    _enviar('Orcada',{valor_orcamento:vf,prazo_orcamento:pr});
}

function _abrirReprovacao(){
    document.getElementById('modal-motivo').value='';
    document.getElementById('modal-reprovacao').classList.add('aberto');
    setTimeout(()=>document.getElementById('modal-motivo').focus(),150);
}

function confirmarModalReprovacao(){
    const m=document.getElementById('modal-motivo').value.trim();
    if(!m){_toast('Informe o motivo');return;}
    fecharModal('modal-reprovacao');
    _enviar('Reprovada',{motivo:m});
}

function fecharModal(id){document.getElementById(id).classList.remove('aberto');}
document.querySelectorAll('.modal-overlay').forEach(o=>{
    o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('aberto');});
});

// ─ WhatsApp
function _waReload(){fecharModal('modal-wa');setTimeout(()=>location.reload(),300);}
function _abrirWa(link,label){
    document.getElementById('wa-texto').innerHTML=`Status atualizado para <strong>${escHtml(label)}</strong>. Avisar o cliente?`;
    document.getElementById('btn-wa').href=link;
    document.getElementById('modal-wa').classList.add('aberto');
}

// ─ Toast
function _toast(msg){
    const el=document.createElement('div'); el.className='g-toast'; el.textContent=msg;
    document.body.appendChild(el); setTimeout(()=>el.remove(),3000);
}

function _enviar(status,extras={}){
    if(!_idAtual)return;
    const label=_statusMap[status]||status;
    fetch('atualizar_status.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({id:_idAtual,status,...extras})
    })
    .then(r=>r.json())
    .then(data=>{
        if(data.sucesso){
            if(data.wa_link)_abrirWa(data.wa_link,label);
            else{_toast('Status atualizado!');setTimeout(()=>location.reload(),1200);}
        } else _toast((data.erro||'Erro desconhecido'));
    })
    .catch(()=>_toast('Erro de conexão'));
}

function escHtml(s){
    if(!s)return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<script src="assets/js/admin.js?v=<?php echo $v; ?>"></script>
</body>
</html>
