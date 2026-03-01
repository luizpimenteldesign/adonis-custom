<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$busca = trim($_GET['q'] ?? '');
$msg   = isset($_GET['msg']) ? $_GET['msg'] : '';

// POST: criar / editar / excluir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar' || $acao === 'editar') {
        $nome      = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $valor     = str_replace(',','.', trim($_POST['valor_base'] ?? '0'));
        $ativo     = isset($_POST['ativo']) ? 1 : 0;
        if (!$nome) { header('Location: servicos.php?msg=erro:Nome obrigatório'); exit; }
        if ($acao === 'criar') {
            $conn->prepare('INSERT INTO servicos (nome, descricao, valor_base, ativo) VALUES (?,?,?,?)')->execute([$nome,$descricao,$valor,$ativo]);
            header('Location: servicos.php?msg=sucesso:Serviço criado!'); exit;
        } else {
            $id = (int)$_POST['id'];
            $conn->prepare('UPDATE servicos SET nome=?, descricao=?, valor_base=?, ativo=? WHERE id=?')->execute([$nome,$descricao,$valor,$ativo,$id]);
            header('Location: servicos.php?msg=sucesso:Serviço atualizado!'); exit;
        }
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
    $sql = 'SELECT s.*, COUNT(ps.id) as total_uso FROM servicos s LEFT JOIN pre_os_servicos ps ON ps.servico_id = s.id'
         . ($busca ? ' WHERE s.nome LIKE :q OR s.descricao LIKE :q' : '')
         . ' GROUP BY s.id ORDER BY s.nome';
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q'=>'%'.$busca.'%']);
    else $stmt->execute();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $servicos = []; }

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
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
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
        <button class="btn-menu" onclick="toggleSidebar()">☰</button>
        <span class="topbar-title">Serviços</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">🔧 Serviços</h1>
                <div class="page-subtitle"><?php echo count($servicos); ?> serviço<?php echo count($servicos)!==1?'s':''; ?> cadastrado<?php echo count($servicos)!==1?'s':''; ?></div>
            </div>
            <button class="btn btn-primary" onclick="abrirModalServico()">+ Novo Serviço</button>
        </div>

        <?php if ($msg): list($tipo,$texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <form method="GET" action="servicos.php" style="margin-bottom:16px">
            <div class="search-bar">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" placeholder="Buscar por nome ou descrição..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off">
                <?php if ($busca): ?>
                <button type="button" onclick="location.href='servicos.php'" style="background:none;border:none;cursor:pointer;font-size:16px;color:var(--g-text-3);padding:0 8px">✕</button>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($servicos)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔧</div>
                <div class="empty-state-title">Nenhum serviço encontrado</div>
                <div class="empty-state-sub">Clique em "+ Novo Serviço" para começar</div>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Descrição</th>
                        <th class="text-right">Valor Base</th>
                        <th class="text-center">Usos</th>
                        <th class="text-center">Ativo</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($servicos as $s): ?>
                <tr class="<?php echo !$s['ativo'] ? 'row-inactive' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars($s['nome']); ?></strong></td>
                    <td class="text-muted" style="font-size:13px"><?php echo $s['descricao'] ? htmlspecialchars($s['descricao']) : '<em>sem descrição</em>'; ?></td>
                    <td class="text-right" style="font-family:'Google Sans',sans-serif;font-weight:700;color:var(--g-green)">
                        <?php echo $s['valor_base'] > 0 ? 'R$ '.number_format($s['valor_base'],2,',','.') : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <td class="text-center"><?php echo $s['total_uso']; ?></td>
                    <td class="text-center">
                        <span class="badge <?php echo $s['ativo'] ? 'badge-success' : 'badge-dark'; ?>"><?php echo $s['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
                    </td>
                    <td class="text-center">
                        <div class="table-actions">
                            <button class="btn-icon" onclick='editarServico(<?php echo json_encode($s); ?>)' title="Editar">✏️</button>
                            <?php if ($s['total_uso'] == 0): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir serviço?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="btn-icon danger" title="Excluir">🗑️</button>
                            </form>
                            <?php else: ?>
                            <span class="btn-icon disabled" title="Em uso">🔒</span>
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
    <a href="dashboard.php"><span>🏠</span>Painel</a>
    <a href="clientes.php"><span>👥</span>Clientes</a>
    <a href="servicos.php" class="active"><span>🔧</span>Serviços</a>
    <a href="logout.php"><span>🚪</span>Sair</a>
</nav>

<!-- MODAL SERVIÇO -->
<div class="modal-overlay" id="modal-servico">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title" id="modal-servico-titulo">Novo Serviço</div>
        <form method="POST" action="servicos.php" id="form-servico">
            <input type="hidden" name="acao" id="form-acao" value="criar">
            <input type="hidden" name="id"   id="form-id"   value="">
            <label>Nome do serviço *</label>
            <input type="text" name="nome" id="form-nome" placeholder="Ex: Regulagem completa" required>
            <label>Descrição</label>
            <textarea name="descricao" id="form-descricao" placeholder="Detalhes do serviço..." rows="2"></textarea>
            <label>Valor Base (R$)</label>
            <input type="number" name="valor_base" id="form-valor" step="0.01" min="0" placeholder="Ex: 150.00">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;margin-top:8px">
                <input type="checkbox" name="ativo" id="form-ativo" checked style="width:auto"> Serviço ativo (visível no formulário)
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
function abrirModalServico(){
    document.getElementById('modal-servico-titulo').textContent = 'Novo Serviço';
    document.getElementById('form-acao').value = 'criar';
    document.getElementById('form-id').value   = '';
    document.getElementById('form-nome').value  = '';
    document.getElementById('form-descricao').value = '';
    document.getElementById('form-valor').value = '';
    document.getElementById('form-ativo').checked = true;
    document.getElementById('modal-servico').classList.add('aberto');
    setTimeout(()=>document.getElementById('form-nome').focus(),150);
}
function editarServico(s){
    document.getElementById('modal-servico-titulo').textContent = 'Editar Serviço';
    document.getElementById('form-acao').value = 'editar';
    document.getElementById('form-id').value   = s.id;
    document.getElementById('form-nome').value  = s.nome;
    document.getElementById('form-descricao').value = s.descricao||'';
    document.getElementById('form-valor').value = s.valor_base||'';
    document.getElementById('form-ativo').checked = s.ativo==1;
    document.getElementById('modal-servico').classList.add('aberto');
    setTimeout(()=>document.getElementById('form-nome').focus(),150);
}
function fecharModalServico(){
    document.getElementById('modal-servico').classList.remove('aberto');
}
document.getElementById('modal-servico').addEventListener('click',e=>{
    if(e.target===document.getElementById('modal-servico')) fecharModalServico();
});
</script>
</body>
</html>
