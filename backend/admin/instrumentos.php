<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$busca = trim($_GET['q'] ?? '');
$msg   = isset($_GET['msg']) ? $_GET['msg'] : '';

try {
    $sql = 'SELECT i.*, c.nome as cliente_nome, COUNT(p.id) as total_os
            FROM instrumentos i
            LEFT JOIN clientes c ON i.cliente_id = c.id
            LEFT JOIN pre_os p ON p.instrumento_id = i.id'
         . ($busca ? ' WHERE i.tipo LIKE :q OR i.marca LIKE :q OR i.modelo LIKE :q OR c.nome LIKE :q' : '')
         . ' GROUP BY i.id ORDER BY c.nome, i.tipo';
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q' => '%'.$busca.'%']);
    else $stmt->execute();
    $instrumentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $instrumentos = []; }

$current_page = 'instrumentos.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instrumentos — Adonis Admin</title>
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
        <span class="topbar-title">Instrumentos</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">🎸 Instrumentos</h1>
                <div class="page-subtitle"><?php echo count($instrumentos); ?> instrumento<?php echo count($instrumentos)!==1?'s':''; ?> cadastrado<?php echo count($instrumentos)!==1?'s':''; ?></div>
            </div>
        </div>

        <?php if ($msg): list($tipo,$texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <form method="GET" action="instrumentos.php" style="margin-bottom:16px">
            <div class="search-bar">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" placeholder="Buscar por tipo, marca, modelo ou cliente..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off">
                <?php if ($busca): ?>
                <button type="button" onclick="location.href='instrumentos.php'" style="background:none;border:none;cursor:pointer;font-size:16px;color:var(--g-text-3);padding:0 8px">✕</button>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($instrumentos)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎸</div>
                <div class="empty-state-title">Nenhum instrumento encontrado</div>
                <div class="empty-state-sub"><?php echo $busca ? 'Tente outro termo de busca' : 'Nenhum instrumento cadastrado ainda'; ?></div>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Instrumento</th>
                        <th>Cliente</th>
                        <th>Cor</th>
                        <th>Nº Série</th>
                        <th class="text-center">OS</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($instrumentos as $i):
                    $tipo   = $i['tipo'] === 'Outro' ? ($i['tipo_outro']  ?: 'Outro') : $i['tipo'];
                    $marca  = $i['marca']  === 'Outro' ? ($i['marca_outro']  ?: '') : ($i['marca']  ?: '');
                    $modelo = $i['modelo'] === 'Outro' ? ($i['modelo_outro'] ?: '') : ($i['modelo'] ?: '');
                    $cor    = $i['cor']    === 'Outro' ? ($i['cor_outro']    ?: '') : ($i['cor']    ?: '');
                    $instr_icons = ['Guitarra'=>'🎸','Baixo'=>'🎸','Violão'=>'🎸','Amplificador'=>'🔊','Pedal Pedalboard'=>'🟢','Outro'=>'🎵'];
                    $icon = $instr_icons[$i['tipo']] ?? '🎵';
                ?>
                <tr>
                    <td>
                        <div class="td-instr">
                            <span class="instr-icon"><?php echo $icon; ?></span>
                            <div>
                                <strong><?php echo htmlspecialchars($tipo); ?></strong>
                                <div class="text-muted" style="font-size:12px"><?php echo htmlspecialchars(trim($marca.' '.$modelo)); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="clientes.php?q=<?php echo urlencode($i['cliente_nome']??''); ?>" class="link-inline"><?php echo htmlspecialchars($i['cliente_nome'] ?? '—'); ?></a>
                    </td>
                    <td><?php echo $cor ? htmlspecialchars($cor) : '<span class="text-muted">—</span>'; ?></td>
                    <td style="font-size:12px;font-family:monospace"><?php echo $i['numero_serie'] ? htmlspecialchars($i['numero_serie']) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="text-center">
                        <?php if ($i['total_os'] > 0): ?>
                        <span class="badge badge-info"><?php echo $i['total_os']; ?></span>
                        <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="table-actions">
                            <a href="dashboard.php?q=<?php echo urlencode($i['cliente_nome']??''); ?>" class="btn-icon" title="Ver OS do cliente">📋</a>
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
    <a href="servicos.php"><span>🔧</span>Serviços</a>
    <a href="logout.php"><span>🚪</span>Sair</a>
</nav>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
</body>
</html>
