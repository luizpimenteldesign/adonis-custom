<?php
/**
 * DASHBOARD — SISTEMA ADONIS
 * Visual: Google / Gmail style
 */
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

// Filtros
$filtro_status = $_GET['status'] ?? '';
$busca         = trim($_GET['q'] ?? '');

// Estatísticas
try {
    $stats = [];
    $rows  = $conn->query("SELECT status, COUNT(*) as total FROM pre_os GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
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
}

// Pedidos
try {
    $where  = [];
    $params = [];
    if ($filtro_status) { $where[] = 'p.status = :status'; $params[':status'] = $filtro_status; }
    if ($busca) {
        $where[]      = '(c.nome LIKE :q OR c.telefone LIKE :q OR i.tipo LIKE :q OR i.marca LIKE :q OR i.modelo LIKE :q)';
        $params[':q'] = '%'.$busca.'%';
    }
    $sql = "SELECT p.id, p.status, p.criado_em, p.atualizado_em,
                   c.nome as cliente_nome, c.telefone,
                   i.tipo as instr_tipo, i.marca as instr_marca, i.modelo as instr_modelo
            FROM pre_os p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN instrumentos i ON p.instrumento_id = i.id".($where ? ' WHERE '.implode(' AND ',$where) : '')." ORDER BY p.atualizado_em DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pedidos = [];
}

$status_map = [
    'Pre-OS'                        => ['label'=>'Pré-OS',                    'badge'=>'badge-new',     'icone'=>'🗒️'],
    'Em analise'                    => ['label'=>'Em Análise',               'badge'=>'badge-info',    'icone'=>'🔍'],
    'Orcada'                        => ['label'=>'Orçada',                   'badge'=>'badge-warning', 'icone'=>'💰'],
    'Aguardando aprovacao'          => ['label'=>'Aguard. Aprovação',       'badge'=>'badge-warning', 'icone'=>'⏳'],
    'Aprovada'                      => ['label'=>'Aguard. Pagamento',        'badge'=>'badge-success', 'icone'=>'💳'],
    'Pagamento recebido'            => ['label'=>'Pagamento Recebido',       'badge'=>'badge-success', 'icone'=>'✅'],
    'Instrumento recebido'          => ['label'=>'Instrumento Recebido',     'badge'=>'badge-success', 'icone'=>'📦'],
    'Servico iniciado'              => ['label'=>'Serviço Iniciado',         'badge'=>'badge-purple',  'icone'=>'🔧'],
    'Em desenvolvimento'            => ['label'=>'Em Desenvolvimento',       'badge'=>'badge-purple',  'icone'=>'⚙️'],
    'Servico finalizado'            => ['label'=>'Serviço Finalizado',       'badge'=>'badge-success', 'icone'=>'🎸'],
    'Pronto para retirada'          => ['label'=>'Pronto p/ Retirada',       'badge'=>'badge-warning', 'icone'=>'🎉'],
    'Aguardando pagamento retirada' => ['label'=>'Pag. Pendente Retirada',   'badge'=>'badge-warning', 'icone'=>'💵'],
    'Entregue'                      => ['label'=>'Entregue',                 'badge'=>'badge-dark',    'icone'=>'🏁'],
    'Reprovada'                     => ['label'=>'Reprovada',                'badge'=>'badge-danger',  'icone'=>'❌'],
    'Cancelada'                     => ['label'=>'Cancelada',                'badge'=>'badge-dark',    'icone'=>'🚫'],
];

function badge($s,$m){$i=$m[$s]??['label'=>$s,'badge'=>'badge-secondary','icone'=>'•'];return '<span class="badge '.$i['badge'].'">'.$i['icone'].' '.$i['label'].'</span>';}
function iniciais($nome){$parts=array_filter(explode(' ',trim($nome)));if(count($parts)>=2) return strtoupper(substr($parts[0],0,1).substr(end($parts),0,1));return strtoupper(substr($parts[0]??'?',0,2));}

$filtros_chips = [
    '' => 'Todos',
    'Pre-OS' => 'Pré-OS',
    'Em analise' => 'Em Análise',
    'Orcada' => 'Orçadas',
    'Aguardando aprovacao' => 'Aguard. Aprovação',
    'Aprovada' => 'Aguard. Pgto',
    'Em desenvolvimento' => 'Em Execução',
    'Pronto para retirada' => 'Pronto',
    'Entregue' => 'Entregues',
];

$v = time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
</head>
<body>

<!-- TOP BAR -->
<header class="header">
    <div class="header-left">
        <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png" alt="Adonis" class="header-logo">
        <span class="header-title">Painel</span>
    </div>
    <div class="header-right">
        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></span>
        <a href="logout.php" class="btn btn-logout">🚶 Sair</a>
    </div>
</header>

<div class="container">

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card blue" onclick="filtrarStatus('')">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
        </div>
        <div class="stat-card yellow" onclick="filtrarStatus('Pre-OS')">
            <div class="stat-label">Pendentes</div>
            <div class="stat-value"><?php echo $stats['pendentes']; ?></div>
        </div>
        <div class="stat-card" style="--c:#e37400" onclick="filtrarStatus('Orcada')">
            <div class="stat-label">Orçadas</div>
            <div class="stat-value" style="color:#e37400"><?php echo $stats['orcadas']; ?></div>
        </div>
        <div class="stat-card green" onclick="filtrarStatus('Em desenvolvimento')">
            <div class="stat-label">Em Execução</div>
            <div class="stat-value"><?php echo $stats['execucao']; ?></div>
        </div>
    </div>

    <!-- BUSCA -->
    <form method="GET" action="" id="form-busca">
        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <input type="text" name="q" placeholder="Buscar por cliente, instrumento..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off" id="input-busca">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filtro_status); ?>" id="hidden-status">
        </div>
    </form>

    <!-- CHIPS DE FILTRO -->
    <div class="chips-row" id="chips-row">
        <?php foreach ($filtros_chips as $val => $label): ?>
        <a href="?status=<?php echo urlencode($val); ?>&q=<?php echo urlencode($busca); ?>" class="chip<?php echo ($filtro_status === $val) ? ' active' : ''; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>

    <!-- LISTA DE PEDIDOS -->
    <div class="pedido-list">
        <div class="pedido-list-header">
            <?php echo count($pedidos); ?> pedido<?php echo count($pedidos) !== 1 ? 's' : ''; ?>
            <?php if ($filtro_status): ?> · <strong><?php echo htmlspecialchars($filtros_chips[$filtro_status] ?? $filtro_status); ?></strong><?php endif; ?>
            <?php if ($busca): ?> · &ldquo;<?php echo htmlspecialchars($busca); ?>&rdquo;<?php endif; ?>
        </div>

        <?php if (empty($pedidos)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📥</div>
            <div class="empty-state-title">Nenhum pedido encontrado</div>
            <div class="empty-state-sub">Tente outro filtro ou termo de busca</div>
        </div>
        <?php else: ?>
        <?php foreach ($pedidos as $p):
            $info    = $status_map[$p['status']] ?? ['label'=>$p['status'],'badge'=>'badge-secondary','icone'=>'•'];
            $ini     = iniciais($p['cliente_nome'] ?? '?');
            $instr   = trim(($p['instr_tipo']??'').' '.($p['instr_marca']??'').' '.($p['instr_modelo']??''));
            $data    = date('d/m', strtotime($p['atualizado_em']));
        ?>
        <a href="detalhes.php?id=<?php echo $p['id']; ?>" class="pedido-item">
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
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="height:16px"></div>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="active">
        <span class="nav-icon">📋</span>
        Pedidos
    </a>
    <a href="dashboard.php?status=Pre-OS">
        <span class="nav-icon">⏳</span>
        Pendentes
    </a>
    <a href="dashboard.php?status=Em desenvolvimento">
        <span class="nav-icon">⚙️</span>
        Execução
    </a>
    <a href="logout.php">
        <span class="nav-icon">🚶</span>
        Sair
    </a>
</nav>

<script>
function filtrarStatus(s) {
    document.getElementById('hidden-status').value = s;
    document.getElementById('form-busca').submit();
}
</script>
<script src="assets/js/admin.js?v=<?php echo $v; ?>"></script>
</body>
</html>
