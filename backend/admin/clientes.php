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
    $sql = 'SELECT c.*, COUNT(p.id) as total_pedidos FROM clientes c LEFT JOIN pre_os p ON p.cliente_id = c.id'
         . ($busca ? ' WHERE c.nome LIKE :q OR c.email LIKE :q OR c.telefone LIKE :q' : '')
         . ' GROUP BY c.id ORDER BY c.nome';
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q' => '%'.$busca.'%']);
    else $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $clientes = []; }

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
        <span class="topbar-title">Clientes</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">👥 Clientes</h1>
                <div class="page-subtitle"><?php echo count($clientes); ?> cliente<?php echo count($clientes)!==1?'s':''; ?> cadastrado<?php echo count($clientes)!==1?'s':''; ?></div>
            </div>
        </div>

        <?php if ($msg): list($tipo,$texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <form method="GET" action="clientes.php" style="margin-bottom:16px">
            <div class="search-bar">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" placeholder="Buscar por nome, e-mail ou telefone..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off">
                <?php if ($busca): ?>
                <button type="button" onclick="location.href='clientes.php'" style="background:none;border:none;cursor:pointer;font-size:16px;color:var(--g-text-3);padding:0 8px">✕</button>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($clientes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
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
                    <td><?php echo $c['telefone'] ? '<a href="https://wa.me/55'.preg_replace('/\D/','',$c['telefone']).'" target="_blank" class="link-wa">💬 '.htmlspecialchars($c['telefone']).'</a>' : '<span class="text-muted">—</span>'; ?></td>
                    <td><?php echo $c['email'] ? htmlspecialchars($c['email']) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="text-center">
                        <?php if ($c['total_pedidos'] > 0): ?>
                        <a href="dashboard.php?q=<?php echo urlencode($c['nome']); ?>" class="badge badge-info"><?php echo $c['total_pedidos']; ?> pedido<?php echo $c['total_pedidos']!=1?'s':''; ?></a>
                        <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                    </td>
                    <td class="text-center text-muted" style="font-size:12px"><?php echo date('d/m/Y', strtotime($c['criado_em'])); ?></td>
                    <td class="text-center">
                        <div class="table-actions">
                            <a href="dashboard.php?q=<?php echo urlencode($c['nome']); ?>" class="btn-icon" title="Ver pedidos">📋</a>
                            <?php if ($c['total_pedidos'] == 0): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir cliente <?php echo htmlspecialchars(addslashes($c['nome'])); ?>?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                <button type="submit" class="btn-icon danger" title="Excluir">🗑️</button>
                            </form>
                            <?php else: ?>
                            <span class="btn-icon disabled" title="Possui pedidos">🔒</span>
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
    <a href="clientes.php" class="active"><span>👥</span>Clientes</a>
    <a href="servicos.php"><span>🔧</span>Serviços</a>
    <a href="logout.php"><span>🚪</span>Sair</a>
</nav>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
</body>
</html>
