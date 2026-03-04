<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$busca = trim($_GET['q'] ?? '');
$msg   = '';

// Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'excluir') {
        $id = (int)$_POST['id'];
        try {
            $conn->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
            $msg = 'sucesso:Cliente removido.';
        } catch (Exception $e) { $msg = 'erro:Não é possível excluir — cliente possui pedidos vinculados.'; }
    }
    header('Location: clientes.php' . ($msg ? '?msg='.urlencode($msg) : ''));
    exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

try {
    $sql = 'SELECT c.*, COUNT(DISTINCT p.id) as total_pedidos, COUNT(DISTINCT i.id) as total_instrumentos
            FROM clientes c
            LEFT JOIN pre_os p ON p.cliente_id = c.id
            LEFT JOIN instrumentos i ON i.cliente_id = c.id'
         . ($busca ? ' WHERE c.nome LIKE :q OR c.email LIKE :q OR c.telefone LIKE :q' : '')
         . ' GROUP BY c.id ORDER BY c.nome';
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q' => '%'.$busca.'%']);
    else $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $clientes = []; }

try {
    $instr_rows = $conn->query(
        'SELECT i.*, c.nome as cliente_nome
         FROM instrumentos i
         LEFT JOIN clientes c ON i.cliente_id = c.id
         ORDER BY i.tipo, i.marca'
    )->fetchAll(PDO::FETCH_ASSOC);
    $instr_por_cliente = [];
    foreach ($instr_rows as $r) {
        $instr_por_cliente[$r['cliente_id']][] = $r;
    }
} catch (Exception $e) { $instr_por_cliente = []; }

$current_page = 'clientes.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
    <style>
    .instr-list{display:flex;flex-direction:column;gap:10px;margin-top:4px}
    .instr-card{background:var(--g-bg);border:1px solid var(--g-border);border-radius:10px;padding:12px 14px;display:flex;align-items:flex-start;gap:12px}
    .instr-card-icon{flex-shrink:0;margin-top:2px;color:var(--g-text-2)}
    .instr-card-body{flex:1;min-width:0}
    .instr-card-title{font-size:14px;font-weight:500;color:var(--g-text)}
    .instr-card-sub{font-size:12px;color:var(--g-text-2);margin-top:2px}
    .instr-card-tags{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
    .instr-tag{font-size:11px;padding:2px 8px;border-radius:10px;background:var(--g-hover);color:var(--g-text-2);border:1px solid var(--g-border);display:flex;align-items:center;gap:3px}
    .modal-section-title{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--g-text-3);margin:16px 0 8px}
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
        <span class="topbar-title">Clientes</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px">group</span>Clientes
                </h1>
                <div class="page-subtitle"><?php echo count($clientes); ?> cliente<?php echo count($clientes)!==1?'s':''; ?> cadastrado<?php echo count($clientes)!==1?'s':''; ?></div>
            </div>
        </div>

        <?php if ($msg): list($tipo,$texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <form method="GET" action="clientes.php" style="margin-bottom:16px">
            <div class="search-bar">
                <span class="search-icon material-symbols-outlined">search</span>
                <input type="text" name="q" placeholder="Buscar por nome, e-mail ou telefone..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off">
                <?php if ($busca): ?>
                <button type="button" onclick="location.href='clientes.php'" style="background:none;border:none;cursor:pointer;color:var(--g-text-3);padding:0 4px;display:flex;align-items:center" title="Limpar">
                    <span class="material-symbols-outlined" style="font-size:18px">close</span>
                </button>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($clientes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><span class="material-symbols-outlined">group</span></div>
                <div class="empty-state-title">Nenhum cliente encontrado</div>
                <div class="empty-state-sub"><?php echo $busca ? 'Tente outro termo de busca' : 'Nenhum cliente cadastrado ainda'; ?></div>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>E-mail</th>
                        <th class="text-center">Pedidos</th>
                        <th class="text-center">Instrumentos</th>
                        <th class="text-center">Cadastro</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td>
                        <div class="td-with-avatar">
                            <div class="mini-avatar"><?php
                                $parts = array_filter(explode(' ', trim($c['nome']??'')));
                                echo count($parts)>=2 ? strtoupper(substr($parts[0],0,1).substr(end($parts),0,1)) : strtoupper(substr($parts[0]??'?',0,2));
                            ?></div>
                            <strong><?php echo htmlspecialchars($c['nome']); ?></strong>
                        </div>
                    </td>
                    <td><?php echo $c['telefone'] ? '<a href="https://wa.me/55'.preg_replace('/\D/','',$c['telefone']).'" target="_blank" class="link-wa"><span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">chat</span> '.htmlspecialchars($c['telefone']).'</a>' : '<span class="text-muted">—</span>'; ?></td>
                    <td><?php echo $c['email'] ? htmlspecialchars($c['email']) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="text-center">
                        <?php if ($c['total_pedidos'] > 0): ?>
                        <a href="dashboard.php?q=<?php echo urlencode($c['nome']); ?>" class="badge badge-info"><?php echo $c['total_pedidos']; ?> pedido<?php echo $c['total_pedidos']!=1?'s':''; ?></a>
                        <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php
                            $qtd_instr = (int)$c['total_instrumentos'];
                            $instr_data = $instr_por_cliente[$c['id']] ?? [];
                        ?>
                        <?php if ($qtd_instr > 0): ?>
                        <button class="badge badge-info" style="border:none;cursor:pointer"
                            onclick='abrirInstrumentos(<?php echo $c["id"]; ?>, <?php echo htmlspecialchars(json_encode($c["nome"]), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($instr_data), ENT_QUOTES); ?>)'>
                            <?php echo $qtd_instr; ?> instrumento<?php echo $qtd_instr!=1?'s':''; ?>
                        </button>
                        <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                    </td>
                    <td class="text-center text-muted" style="font-size:12px"><?php echo date('d/m/Y', strtotime($c['criado_em'])); ?></td>
                    <td class="text-center">
                        <div class="table-actions">
                            <a href="dashboard.php?q=<?php echo urlencode($c['nome']); ?>" class="btn-icon" title="Ver pedidos">
                                <span class="material-symbols-outlined">receipt_long</span>
                            </a>
                            <?php if ($qtd_instr > 0): ?>
                            <button class="btn-icon" title="Ver instrumentos"
                                onclick='abrirInstrumentos(<?php echo $c["id"]; ?>, <?php echo htmlspecialchars(json_encode($c["nome"]), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($instr_data), ENT_QUOTES); ?>)'>
                                <span class="material-symbols-outlined">piano</span>
                            </button>
                            <?php endif; ?>
                            <?php if ($c['total_pedidos'] == 0): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir cliente <?php echo htmlspecialchars(addslashes($c['nome'])); ?>?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                <button type="submit" class="btn-icon danger" title="Excluir">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="btn-icon disabled" title="Possui pedidos">
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
    <a href="dashboard.php">
        <span class="material-symbols-outlined nav-icon">dashboard</span>Painel
    </a>
    <a href="clientes.php" class="active">
        <span class="material-symbols-outlined nav-icon">group</span>Clientes
    </a>
    <a href="servicos.php">
        <span class="material-symbols-outlined nav-icon">build</span>Serviços
    </a>
    <a href="logout.php">
        <span class="material-symbols-outlined nav-icon">logout</span>Sair
    </a>
</nav>

<!-- MODAL: INSTRUMENTOS DO CLIENTE -->
<div class="modal-overlay" id="modal-instrumentos">
    <div class="modal-box" style="max-width:520px">
        <div class="modal-drag"></div>
        <div class="modal-title" id="modal-instr-titulo">Instrumentos do Cliente</div>
        <div id="modal-instr-body" style="margin-top:4px"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="fecharModalInstr()">Fechar</button>
            <a href="#" id="modal-instr-link" class="btn btn-primary">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">receipt_long</span> Ver Pedidos
            </a>
        </div>
    </div>
</div>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
<script>
function iconHtml(name, size) {
    return `<span class="material-symbols-outlined" style="font-size:${size||20}px">${name}</span>`;
}

// mapa tipo → ícone Material Symbol
const instr_icons = {
    'Guitarra'        : 'piano',
    'Baixo'           : 'piano',
    'Violão'          : 'piano',
    'Amplificador'    : 'speaker',
    'Pedal Pedalboard': 'toggle_on',
    'Outro'           : 'music_note'
};

function abrirInstrumentos(clienteId, clienteNome, instrumentos) {
    document.getElementById('modal-instr-titulo').textContent = 'Instrumentos — ' + clienteNome;
    document.getElementById('modal-instr-link').href = 'dashboard.php?q=' + encodeURIComponent(clienteNome);

    let html = '';
    if (!instrumentos || instrumentos.length === 0) {
        html = `<div class="empty-state" style="padding:24px 0">
                    <div class="empty-state-icon">${iconHtml('piano',32)}</div>
                    <div class="empty-state-title">Nenhum instrumento cadastrado</div>
                </div>`;
    } else {
        html = '<div class="instr-list">';
        for (const i of instrumentos) {
            const tipo   = i.tipo   === 'Outro' ? (i.tipo_outro   || 'Outro') : (i.tipo   || '—');
            const marca  = i.marca  === 'Outro' ? (i.marca_outro  || '')      : (i.marca  || '');
            const modelo = i.modelo === 'Outro' ? (i.modelo_outro || '')      : (i.modelo || '');
            const cor    = i.cor    === 'Outro' ? (i.cor_outro    || '')      : (i.cor    || '');
            const serie  = i.numero_serie || '';
            const icn    = instr_icons[i.tipo] || 'music_note';
            const subtitulo = [marca, modelo].filter(Boolean).join(' · ');

            html += `<div class="instr-card">
                <div class="instr-card-icon">${iconHtml(icn, 24)}</div>
                <div class="instr-card-body">
                    <div class="instr-card-title">${esc(tipo)}</div>
                    ${subtitulo ? `<div class="instr-card-sub">${esc(subtitulo)}</div>` : ''}
                    <div class="instr-card-tags">
                        ${cor   ? `<span class="instr-tag">${iconHtml('palette',11)} ${esc(cor)}</span>` : ''}
                        ${serie ? `<span class="instr-tag">${iconHtml('tag',11)} ${esc(serie)}</span>` : ''}
                    </div>
                </div>
            </div>`;
        }
        html += '</div>';
    }

    document.getElementById('modal-instr-body').innerHTML = html;
    document.getElementById('modal-instrumentos').classList.add('aberto');
}

function fecharModalInstr() {
    document.getElementById('modal-instrumentos').classList.remove('aberto');
}

document.getElementById('modal-instrumentos').addEventListener('click', function(e) {
    if (e.target === this) fecharModalInstr();
});

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
