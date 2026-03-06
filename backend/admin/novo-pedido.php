<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// Carrega listas para os selects
try {
    $clientes = $conn->query('SELECT id, nome, telefone, email FROM clientes ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $clientes = []; }

try {
    $instrumentos = $conn->query('SELECT id, cliente_id, tipo, marca, modelo, cor, numero_serie FROM instrumentos ORDER BY tipo, marca')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $instrumentos = []; }

try {
    $servicos = $conn->query('SELECT id, nome, descricao, valor_base, prazo_padrao_dias FROM servicos WHERE ativo = 1 ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $servicos = []; }

$status_opcoes = [
    'Pre-OS', 'Em analise', 'Orcada', 'Aguardando aprovacao', 'Aprovada',
    'Instrumento recebido', 'Em desenvolvimento', 'Servico finalizado',
    'Pronto para retirada', 'Entregue', 'Reprovada', 'Cancelada'
];

$current_page = 'novo-pedido.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Pedido — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
    <style>
    .form-section{background:var(--g-surface);border:1px solid var(--g-border);border-radius:12px;padding:20px 24px;margin-bottom:16px}
    .form-section-title{font-size:15px;font-weight:600;color:var(--g-text);margin-bottom:16px;display:flex;align-items:center;gap:8px}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    @media (max-width:640px){.form-grid{grid-template-columns:1fr}}
    .servico-item{background:var(--g-bg);border:1px solid var(--g-border);border-radius:10px;padding:12px 14px;display:flex;align-items:flex-start;gap:12px;cursor:pointer;transition:all .2s}
    .servico-item:hover{border-color:var(--g-primary);background:var(--g-hover)}
    .servico-item.selected{border-color:var(--g-primary);background:var(--g-primary-bg)}
    .servico-check{width:20px;height:20px;border:2px solid var(--g-border);border-radius:4px;flex-shrink:0;margin-top:2px;display:flex;align-items:center;justify-content:center;transition:all .2s}
    .servico-item.selected .servico-check{background:var(--g-primary);border-color:var(--g-primary)}
    .servico-check-icon{display:none;font-size:14px;color:white}
    .servico-item.selected .servico-check-icon{display:block}
    .servico-body{flex:1;min-width:0}
    .servico-nome{font-size:14px;font-weight:500;color:var(--g-text)}
    .servico-desc{font-size:12px;color:var(--g-text-2);margin-top:2px}
    .servico-meta{font-size:12px;color:var(--g-text-3);margin-top:4px;display:flex;gap:12px}
    .cliente-option{display:flex;align-items:center;gap:8px;padding:4px 0}
    .mini-avatar-select{width:32px;height:32px;border-radius:50%;background:var(--g-primary);color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0}
    .cliente-info{flex:1;min-width:0}
    .cliente-nome{font-size:14px;font-weight:500;color:var(--g-text)}
    .cliente-contato{font-size:12px;color:var(--g-text-2)}
    select.form-input{cursor:pointer}
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
        <span class="topbar-title">Novo Pedido</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px">add_circle</span>Criar Ordem de Serviço
                </h1>
                <div class="page-subtitle">Cadastro manual de pedido no painel admin</div>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">arrow_back</span>
                Voltar
            </a>
        </div>

        <?php if ($msg): list($tipo, $texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <form method="POST" action="processar_novo_pedido.php" id="form-pedido">
            <!-- SEÇÃO 1: CLIENTE -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="material-symbols-outlined" style="color:var(--g-primary)">person</span>
                    Cliente
                </div>
                <label class="form-label">CLIENTE *</label>
                <select class="form-input" name="cliente_id" id="select-cliente" required onchange="carregarInstrumentos()">
                    <option value="">Selecione um cliente cadastrado</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" data-nome="<?php echo htmlspecialchars($c['nome']); ?>" data-telefone="<?php echo htmlspecialchars($c['telefone']); ?>" data-email="<?php echo htmlspecialchars($c['email']); ?>">
                        <?php echo htmlspecialchars($c['nome']); ?> • <?php echo htmlspecialchars($c['telefone'] ?: $c['email']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Se o cliente não estiver na lista, cadastre-o primeiro em <a href="#" style="color:var(--g-primary)">Clientes</a></div>
            </div>

            <!-- SEÇÃO 2: INSTRUMENTO -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="material-symbols-outlined" style="color:var(--g-primary)">piano</span>
                    Instrumento
                </div>
                <label class="form-label">INSTRUMENTO *</label>
                <select class="form-input" name="instrumento_id" id="select-instrumento" required>
                    <option value="">Selecione um cliente primeiro</option>
                </select>
                <div class="form-hint" id="hint-instrumento">Os instrumentos serão carregados após selecionar o cliente</div>
            </div>

            <!-- SEÇÃO 3: SERVIÇOS -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="material-symbols-outlined" style="color:var(--g-primary)">build</span>
                    Serviços Solicitados
                </div>
                <?php if (empty($servicos)): ?>
                <div class="alert alert-warning">Nenhum serviço ativo cadastrado. <a href="servicos.php" style="color:var(--g-primary)">Cadastre serviços primeiro</a>.</div>
                <?php else: ?>
                <div id="lista-servicos" style="display:flex;flex-direction:column;gap:10px">
                    <?php foreach ($servicos as $s): ?>
                    <label class="servico-item" data-id="<?php echo $s['id']; ?>">
                        <div class="servico-check">
                            <span class="material-symbols-outlined servico-check-icon">check</span>
                        </div>
                        <div class="servico-body">
                            <div class="servico-nome"><?php echo htmlspecialchars($s['nome']); ?></div>
                            <?php if ($s['descricao']): ?>
                            <div class="servico-desc"><?php echo htmlspecialchars($s['descricao']); ?></div>
                            <?php endif; ?>
                            <div class="servico-meta">
                                <span><strong>R$ <?php echo number_format((float)$s['valor_base'], 2, ',', '.'); ?></strong></span>
                                <?php if ($s['prazo_padrao_dias']): ?>
                                <span>• <?php echo $s['prazo_padrao_dias']; ?> d.u.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <input type="checkbox" name="servicos[]" value="<?php echo $s['id']; ?>" style="display:none">
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-hint" style="margin-top:12px">Selecione um ou mais serviços clicando nos cards acima</div>
                <?php endif; ?>
            </div>

            <!-- SEÇÃO 4: OBSERVAÇÕES E STATUS -->
            <div class="form-section">
                <div class="form-section-title">
                    <span class="material-symbols-outlined" style="color:var(--g-primary)">description</span>
                    Detalhes Adicionais
                </div>
                <label class="form-label">OBSERVAÇÕES INTERNAS</label>
                <textarea class="form-input" name="observacoes" rows="4" placeholder="Anotações, requisições especiais, histórico de atendimento..." style="resize:vertical"></textarea>
                <div class="form-hint">Essas observações são privadas e não aparecem para o cliente</div>

                <div class="form-grid" style="margin-top:16px">
                    <div>
                        <label class="form-label">STATUS INICIAL</label>
                        <select class="form-input" name="status_inicial" required>
                            <?php foreach ($status_opcoes as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $st === 'Pre-OS' ? 'selected' : ''; ?>><?php echo str_replace(['Pre-OS', 'Orcada', 'Servico'], ['Pré-OS', 'Orçada', 'Serviço'], $st); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Normalmente deve ser "Pré-OS"</div>
                    </div>
                    <div>
                        <label class="form-label">PRAZO ESTIMADO (DIAS ÚTEIS)</label>
                        <input class="form-input" type="number" name="prazo_estimado" min="1" step="1" placeholder="Ex: 7" id="input-prazo">
                        <div class="form-hint">Calculado automaticamente com base nos serviços</div>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px">
                <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px">add_circle</span>
                    Criar Ordem de Serviço
                </button>
            </div>
        </form>
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
    <a href="logout.php">
        <span class="material-symbols-outlined nav-icon">logout</span>Sair
    </a>
</nav>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
<script>
const todosInstrumentos = <?php echo json_encode($instrumentos); ?>;
const todosServicos = <?php echo json_encode($servicos); ?>;

// Toggle visual dos serviços
document.querySelectorAll('.servico-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        this.classList.toggle('selected');
        const chk = this.querySelector('input[type="checkbox"]');
        chk.checked = this.classList.contains('selected');
        calcularPrazoTotal();
    });
});

// Carregar instrumentos do cliente selecionado
function carregarInstrumentos() {
    const clienteId = parseInt(document.getElementById('select-cliente').value);
    const selectInstr = document.getElementById('select-instrumento');
    const hint = document.getElementById('hint-instrumento');

    selectInstr.innerHTML = '<option value="">Carregando...</option>';

    if (!clienteId) {
        selectInstr.innerHTML = '<option value="">Selecione um cliente primeiro</option>';
        hint.textContent = 'Os instrumentos serão carregados após selecionar o cliente';
        return;
    }

    const instrumentosCliente = todosInstrumentos.filter(i => i.cliente_id == clienteId);

    if (instrumentosCliente.length === 0) {
        selectInstr.innerHTML = '<option value="">Este cliente não tem instrumentos cadastrados</option>';
        hint.innerHTML = 'Cadastre um instrumento em <a href="instrumentos.php" style="color:var(--g-primary)">Catálogo</a> primeiro';
        return;
    }

    selectInstr.innerHTML = '<option value="">Selecione o instrumento</option>';
    instrumentosCliente.forEach(i => {
        const tipo   = i.tipo   === 'Outro' ? (i.tipo_outro   || 'Outro') : (i.tipo   || '—');
        const marca  = i.marca  === 'Outro' ? (i.marca_outro  || '')      : (i.marca  || '');
        const modelo = i.modelo === 'Outro' ? (i.modelo_outro || '')      : (i.modelo || '');
        const label  = [tipo, marca, modelo].filter(Boolean).join(' • ');
        const opt = document.createElement('option');
        opt.value = i.id;
        opt.textContent = label;
        selectInstr.appendChild(opt);
    });
    hint.textContent = `${instrumentosCliente.length} instrumento${instrumentosCliente.length !== 1 ? 's' : ''} disponível${instrumentosCliente.length !== 1 ? 'eis' : ''}`;
}

// Calcular prazo total com base nos serviços selecionados
function calcularPrazoTotal() {
    const selecionados = Array.from(document.querySelectorAll('.servico-item.selected')).map(el => parseInt(el.dataset.id));
    let prazoTotal = 0;
    selecionados.forEach(id => {
        const srv = todosServicos.find(s => s.id == id);
        if (srv && srv.prazo_padrao_dias) prazoTotal += parseInt(srv.prazo_padrao_dias);
    });
    const inputPrazo = document.getElementById('input-prazo');
    if (prazoTotal > 0) inputPrazo.value = prazoTotal;
}

// Validação antes de enviar
document.getElementById('form-pedido').addEventListener('submit', function(e) {
    const selecionados = document.querySelectorAll('.servico-item.selected').length;
    if (selecionados === 0) {
        e.preventDefault();
        alert('Selecione pelo menos um serviço!');
        return false;
    }
});
</script>
</body>
</html>
