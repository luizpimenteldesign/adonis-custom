<?php
/**
 * DASHBOARD ADMINISTRATIVO - SISTEMA ADONIS
 * Versão: 2.0
 * Data: 27/02/2026
 */

require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

// Filtros via GET
$filtro_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filtro_busca  = isset($_GET['busca'])  ? trim($_GET['busca'])  : '';

$status_validos = ['Pre-OS','Em analise','Orcada','Aguardando aprovacao','Aprovada','Reprovada','Cancelada'];
if (!in_array($filtro_status, $status_validos)) $filtro_status = '';

try {
    // Cards de estatísticas (sempre totais reais, sem filtro)
    $total_preos     = $conn->query("SELECT COUNT(*) FROM pre_os")->fetchColumn();
    $total_pendentes = $conn->query("SELECT COUNT(*) FROM pre_os WHERE status IN ('Pre-OS','Em analise','Orcada','Aguardando aprovacao')")->fetchColumn();
    $total_aprovados = $conn->query("SELECT COUNT(*) FROM pre_os WHERE status = 'Aprovada'")->fetchColumn();
    $total_encerrados= $conn->query("SELECT COUNT(*) FROM pre_os WHERE status IN ('Reprovada','Cancelada')")->fetchColumn();

    // Query base com filtros
    $where  = [];
    $params = [];

    if ($filtro_status !== '') {
        $where[]  = 'p.status = :status';
        $params[':status'] = $filtro_status;
    }

    if ($filtro_busca !== '') {
        $where[]  = '(c.nome LIKE :busca OR i.marca LIKE :busca2 OR i.modelo LIKE :busca3)';
        $params[':busca']  = '%' . $filtro_busca . '%';
        $params[':busca2'] = '%' . $filtro_busca . '%';
        $params[':busca3'] = '%' . $filtro_busca . '%';
    }

    $sql_where = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt_lista = $conn->prepare("
        SELECT 
            p.id, p.status, p.public_token, p.criado_em,
            c.nome as cliente_nome, c.telefone as cliente_telefone,
            i.tipo as instrumento_tipo, i.marca as instrumento_marca, i.modelo as instrumento_modelo
        FROM pre_os p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN instrumentos i ON p.instrumento_id = i.id
        {$sql_where}
        ORDER BY p.criado_em DESC
        LIMIT 100
    ");
    $stmt_lista->execute($params);
    $lista_preos = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Erro dashboard: ' . $e->getMessage());
    $lista_preos = [];
    $total_preos = $total_pendentes = $total_aprovados = $total_encerrados = 0;
}

function formatarStatus($status) {
    $badges = [
        'Pre-OS'               => '<span class="badge badge-new">Novo</span>',
        'Em analise'           => '<span class="badge badge-info">Em Análise</span>',
        'Orcada'               => '<span class="badge badge-warning">Orçada</span>',
        'Aguardando aprovacao' => '<span class="badge badge-warning">Aguardando</span>',
        'Aprovada'             => '<span class="badge badge-success">Aprovada</span>',
        'Reprovada'            => '<span class="badge badge-danger">Reprovada</span>',
        'Cancelada'            => '<span class="badge badge-dark">Cancelada</span>',
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
}

function formatarData($data) {
    return date('d/m/Y H:i', strtotime($data));
}

// URL base para filtros
function urlFiltro($status = '') {
    $params = [];
    if ($status)                              $params['status'] = $status;
    if (!empty($_GET['busca']))               $params['busca']  = $_GET['busca'];
    return 'dashboard.php' . ($params ? '?' . http_build_query($params) : '');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Adonis Custom</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .stat-card { cursor: pointer; transition: transform .15s, box-shadow .15s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
        .stat-card.ativo { outline: 3px solid #667eea; }
        .filtros-bar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
        .filtros-bar input[type=text] { flex:1; min-width:200px; padding:8px 14px; border:1px solid #ddd; border-radius:8px; font-size:14px; }
        .filtros-bar .btn-limpar { color:#888; font-size:13px; text-decoration:none; white-space:nowrap; }
        .filtros-bar .btn-limpar:hover { color:#e53e3e; }
        .resultado-info { font-size:13px; color:#666; margin-bottom:8px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis3.png" alt="Adonis" class="header-logo">
            <h1 class="header-title">Painel Administrativo</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></div>
            </div>
            <a href="logout.php" class="btn-logout">🚪 Sair</a>
        </div>
    </header>

    <div class="container">

        <!-- CARDS CLICAVEIS -->
        <div class="stats-grid">
            <a href="<?php echo urlFiltro(); ?>" style="text-decoration:none">
                <div class="stat-card <?php echo $filtro_status === '' ? 'ativo' : ''; ?>">
                    <div class="stat-label">📋 Total de Pedidos</div>
                    <div class="stat-value"><?php echo $total_preos; ?></div>
                </div>
            </a>
            <a href="<?php echo urlFiltro('Pre-OS'); ?>" style="text-decoration:none">
                <div class="stat-card pendente <?php echo $filtro_status === 'Pre-OS' ? 'ativo' : ''; ?>">
                    <div class="stat-label">⏳ Pendentes</div>
                    <div class="stat-value"><?php echo $total_pendentes; ?></div>
                </div>
            </a>
            <a href="<?php echo urlFiltro('Aprovada'); ?>" style="text-decoration:none">
                <div class="stat-card aprovado <?php echo $filtro_status === 'Aprovada' ? 'ativo' : ''; ?>">
                    <div class="stat-label">✅ Aprovados</div>
                    <div class="stat-value"><?php echo $total_aprovados; ?></div>
                </div>
            </a>
            <a href="<?php echo urlFiltro('Reprovada'); ?>" style="text-decoration:none">
                <div class="stat-card finalizado <?php echo $filtro_status === 'Reprovada' ? 'ativo' : ''; ?>">
                    <div class="stat-label">❌ Reprovados/Cancelados</div>
                    <div class="stat-value"><?php echo $total_encerrados; ?></div>
                </div>
            </a>
        </div>

        <!-- TABELA -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">
                    <?php if ($filtro_status): ?>
                        Pedidos &mdash; <?php echo htmlspecialchars($filtro_status); ?>
                    <?php else: ?>
                        Pedidos Recentes
                    <?php endif; ?>
                </h2>
            </div>

            <!-- BARRA DE BUSCA E FILTRO DE STATUS -->
            <form method="GET" action="dashboard.php" class="filtros-bar" style="padding:0 0 0 0;">
                <input type="text" name="busca" placeholder="🔍 Buscar cliente ou instrumento..." value="<?php echo htmlspecialchars($filtro_busca); ?>">
                <select name="status" onchange="this.form.submit()" style="padding:8px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;background:#fff;">
                    <option value="">Todos os status</option>
                    <?php foreach (['Pre-OS','Em analise','Orcada','Aguardando aprovacao','Aprovada','Reprovada','Cancelada'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $filtro_status === $s ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary" style="padding:8px 18px;">Buscar</button>
                <?php if ($filtro_status || $filtro_busca): ?>
                <a href="dashboard.php" class="btn-limpar">× Limpar filtros</a>
                <?php endif; ?>
            </form>

            <div class="resultado-info">
                <?php echo count($lista_preos); ?> pedido(s) encontrado(s)
                <?php if ($filtro_busca): echo ' para &ldquo;' . htmlspecialchars($filtro_busca) . '&rdquo;'; endif; ?>
            </div>

            <?php if (empty($lista_preos)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                    <p>Nenhum pedido encontrado</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Instrumento</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_preos as $p): ?>
                            <tr>
                                <td><strong>#<?php echo $p['id']; ?></strong></td>
                                <td>
                                    <div><?php echo htmlspecialchars($p['cliente_nome']); ?></div>
                                    <div class="text-muted"><?php echo htmlspecialchars($p['cliente_telefone']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($p['instrumento_tipo']); ?></div>
                                    <div class="text-muted"><?php echo htmlspecialchars($p['instrumento_marca'] . ' ' . $p['instrumento_modelo']); ?></div>
                                </td>
                                <td><?php echo formatarStatus($p['status']); ?></td>
                                <td><?php echo formatarData($p['criado_em']); ?></td>
                                <td><a href="detalhes.php?id=<?php echo $p['id']; ?>" class="btn btn-primary">👁️ Ver</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
