<?php
/**
 * DASHBOARD — SISTEMA ADONIS
 * Visual: Google / Material Design 3
 * Versão: 6.0 — Sidebar + Painel de Ações Inline
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
}

try {
    $where  = [];
    $params = [];
    if ($filtro_status) { $where[] = 'p.status = :status'; $params[':status'] = $filtro_status; }
    if ($busca) {
        $where[]      = '(c.nome LIKE :q OR c.telefone LIKE :q OR i.tipo LIKE :q OR i.marca LIKE :q OR i.modelo LIKE :q)';
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

// Buscar total de serviços por pré-OS para o modal de orçamento
$totais_servicos = [];
try {
    $ts = $conn->query("SELECT pre_os_id, SUM(s.valor_base) as total FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id = s.id GROUP BY pre_os_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ts as $t) $totais_servicos[$t['pre_os_id']] = (float)$t['total'];
} catch (Exception $e) {}

$status_map = [
    'Pre-OS'                        => ['label'=>'Pré-OS',                  'badge'=>'badge-new',     'icone'=>'🗒️'],
    'Em analise'                    => ['label'=>'Em Análise',              'badge'=>'badge-info',    'icone'=>'🔍'],
    'Orcada'                        => ['label'=>'Orçada',                  'badge'=>'badge-warning', 'icone'=>'💰'],
    'Aguardando aprovacao'          => ['label'=>'Aguard. Aprovação',      'badge'=>'badge-warning', 'icone'=>'⏳'],
    'Aprovada'                      => ['label'=>'Aguard. Pagamento',       'badge'=>'badge-success', 'icone'=>'💳'],
    'Pagamento recebido'            => ['label'=>'Pagamento Recebido',      'badge'=>'badge-success', 'icone'=>'✅'],
    'Instrumento recebido'          => ['label'=>'Instrumento Recebido',    'badge'=>'badge-success', 'icone'=>'📦'],
    'Servico iniciado'              => ['label'=>'Serviço Iniciado',        'badge'=>'badge-purple',  'icone'=>'🔧'],
    'Em desenvolvimento'            => ['label'=>'Em Desenvolvimento',      'badge'=>'badge-purple',  'icone'=>'⚙️'],
    'Servico finalizado'            => ['label'=>'Serviço Finalizado',      'badge'=>'badge-success', 'icone'=>'🎸'],
    'Pronto para retirada'          => ['label'=>'Pronto p/ Retirada',      'badge'=>'badge-warning', 'icone'=>'🎉'],
    'Aguardando pagamento retirada' => ['label'=>'Pag. Pendente Retirada',  'badge'=>'badge-warning', 'icone'=>'💵'],
    'Entregue'                      => ['label'=>'Entregue',                'badge'=>'badge-dark',    'icone'=>'🏁'],
    'Reprovada'                     => ['label'=>'Reprovada',               'badge'=>'badge-danger',  'icone'=>'❌'],
    'Cancelada'                     => ['label'=>'Cancelada',               'badge'=>'badge-dark',    'icone'=>'🚫'],
];

// Ações disponíveis por status atual (progressão condicional)
$acoes_por_status = [
    'Pre-OS'               => [['Em analise','🔍 Iniciar Análise','btn-info'],['Orcada','💰 Orçar','btn-warning','modal-orc'],['Reprovada','❌ Reprovar','btn-danger','modal-rep'],['Cancelada','🚫 Cancelar','btn-dark']],
    'Em analise'           => [['Orcada','💰 Orçar','btn-warning','modal-orc'],['Reprovada','❌ Reprovar','btn-danger','modal-rep'],['Cancelada','🚫 Cancelar','btn-dark']],
    'Orcada'               => [['Aguardando aprovacao','⏳ Aguardar Aprovação','btn-warning'],['Reprovada','❌ Reprovar','btn-danger','modal-rep'],['Cancelada','🚫 Cancelar','btn-dark']],
    'Aguardando aprovacao' => [['Aprovada','💳 Marcar Aprovada','btn-success'],['Reprovada','❌ Reprovar','btn-danger','modal-rep'],['Cancelada','🚫 Cancelar','btn-dark']],
    'Aprovada'             => [['Pagamento recebido','✅ Pagamento Recebido','btn-success'],['Cancelada','🚫 Cancelar','btn-dark']],
    'Pagamento recebido'   => [['Instrumento recebido','📦 Instrumento Recebido','btn-success'],['Cancelada','🚫 Cancelar','btn-dark']],
    'Instrumento recebido' => [['Servico iniciado','🔧 Iniciar Serviço','btn-purple']],
    'Servico iniciado'     => [['Em desenvolvimento','⚙️ Em Desenvolvimento','btn-purple']],
    'Em desenvolvimento'   => [['Servico finalizado','🎸 Serviço Finalizado','btn-success']],
    'Servico finalizado'   => [['Pronto para retirada','🎉 Pronto p/ Retirada','btn-warning'],['Aguardando pagamento retirada','💵 Aguardar Pag. Retirada','btn-warning']],
    'Pronto para retirada' => [['Entregue','🏁 Entregue','btn-dark']],
    'Aguardando pagamento retirada' => [['Entregue','🏁 Entregue','btn-dark']],
    'Entregue'             => [],
    'Reprovada'            => [['Em analise','🔄 Reabrir Análise','btn-info']],
    'Cancelada'            => [['Pre-OS','🔄 Reabrir','btn-info']],
];

$filtros_chips = [
    ''                    => 'Todos',
    'Pre-OS'              => 'Pré-OS',
    'Em analise'          => 'Em Análise',
    'Orcada'              => 'Orçadas',
    'Aguardando aprovacao'=> 'Aguard. Aprovação',
    'Aprovada'            => 'Aguard. Pgto',
    'Em desenvolvimento'  => 'Em Execução',
    'Pronto para retirada'=> 'Pronto',
    'Entregue'            => 'Entregues',
    'Reprovada'           => 'Reprovadas',
    'Cancelada'           => 'Canceladas',
];

function badge($s,$m){$i=$m[$s]??['label'=>$s,'badge'=>'badge-secondary','icone'=>'•'];return '<span class="badge '.$i['badge'].'">'.$i['icone'].' '.$i['label'].'</span>';}
function iniciais($nome){$parts=array_filter(explode(' ',trim($nome)));if(count($parts)>=2) return strtoupper(substr($parts[0],0,1).substr(end($parts),0,1));return strtoupper(substr($parts[0]??'?',0,2));}

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
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <style>
    /* ── LAYOUT SIDEBAR ─────────────────────────────── */
    .app-layout {
      display: flex;
      min-height: 100vh;
    }

    /* SIDEBAR */
    .sidebar {
      width: 240px;
      flex-shrink: 0;
      background: var(--g-surface);
      border-right: 1px solid var(--g-border);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      z-index: 200;
      overflow-y: auto;
      transform: translateX(-100%);
      transition: transform .25s ease;
    }
    .sidebar.open { transform: translateX(0); }
    @media (min-width: 960px) {
      .sidebar { transform: translateX(0); position: sticky; top: 0; height: 100vh; }
    }

    .sidebar-logo {
      padding: 20px 20px 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .sidebar-logo img { height: 36px; }
    .sidebar-logo-title {
      font-family: 'Google Sans', sans-serif;
      font-size: 16px;
      font-weight: 700;
      color: var(--g-text);
    }

    .sidebar-section {
      padding: 8px 0;
    }
    .sidebar-section-label {
      font-size: 11px;
      font-weight: 600;
      color: var(--g-text-3);
      text-transform: uppercase;
      letter-spacing: .6px;
      padding: 8px 20px 4px;
    }
    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 500;
      color: var(--g-text-2);
      text-decoration: none;
      border-radius: 0 24px 24px 0;
      margin-right: 12px;
      transition: background .15s, color .15s;
      -webkit-tap-highlight-color: transparent;
    }
    .sidebar-link:hover { background: var(--g-hover); color: var(--g-text); text-decoration: none; }
    .sidebar-link.active {
      background: var(--g-blue-light);
      color: var(--g-blue);
    }
    .sidebar-link .nav-icon { font-size: 18px; flex-shrink: 0; width: 22px; text-align: center; }
    .sidebar-link .nav-badge {
      margin-left: auto;
      background: var(--g-blue);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 10px;
      min-width: 20px;
      text-align: center;
    }

    .sidebar-divider { border: none; border-top: 1px solid var(--g-border); margin: 8px 0; }

    .sidebar-user {
      margin-top: auto;
      padding: 16px 20px;
      border-top: 1px solid var(--g-border);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .sidebar-user-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: var(--g-blue-light);
      color: var(--g-blue);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Google Sans', sans-serif;
      font-size: 14px;
      font-weight: 700;
      flex-shrink: 0;
    }
    .sidebar-user-info { flex: 1; min-width: 0; }
    .sidebar-user-name {
      font-size: 13px;
      font-weight: 500;
      color: var(--g-text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .sidebar-user-role { font-size: 11px; color: var(--g-text-3); }
    .sidebar-logout {
      color: var(--g-text-3);
      text-decoration: none;
      font-size: 18px;
      padding: 4px;
      flex-shrink: 0;
    }
    .sidebar-logout:hover { color: var(--g-red); text-decoration: none; }

    /* OVERLAY sidebar mobile */
    .sidebar-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.35);
      z-index: 199;
    }
    .sidebar-overlay.open { display: block; }

    /* MAIN (conteúdo) */
    .main-content {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
    }

    /* TOP BAR mobile */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 100;
      height: 56px;
      background: var(--g-surface);
      border-bottom: 1px solid var(--g-border);
      display: flex;
      align-items: center;
      padding: 0 16px;
      gap: 12px;
    }
    @media (min-width: 960px) { .topbar { display: none; } }
    .topbar-logo img { height: 28px; }
    .topbar-title {
      font-family: 'Google Sans', sans-serif;
      font-size: 17px;
      font-weight: 500;
      color: var(--g-text);
      flex: 1;
    }
    .btn-menu {
      width: 40px; height: 40px;
      display: flex; align-items: center; justify-content: center;
      border: none; background: none;
      font-size: 22px; cursor: pointer;
      border-radius: 50%;
      color: var(--g-text-2);
      -webkit-tap-highlight-color: transparent;
    }
    .btn-menu:hover { background: var(--g-hover); }

    /* CONTENT AREA */
    .page-content {
      flex: 1;
      padding: 20px;
      max-width: 1400px; /* largura generosa, sem forçar estreito */
    }
    @media (min-width: 960px) {
      body { padding-bottom: 0; }
      .bottom-nav { display: none; }
    }

    /* STATS */
    .stats-row {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .stat-chip {
      display: flex;
      align-items: center;
      gap: 10px;
      background: var(--g-surface);
      border: 1px solid var(--g-border);
      border-radius: 12px;
      padding: 12px 18px;
      cursor: pointer;
      transition: box-shadow .15s, border-color .15s;
      -webkit-tap-highlight-color: transparent;
      flex: 1;
      min-width: 120px;
    }
    .stat-chip:hover { box-shadow: var(--g-shadow-md); border-color: var(--g-blue); }
    .stat-chip-val {
      font-family: 'Google Sans', sans-serif;
      font-size: 26px;
      font-weight: 700;
      line-height: 1;
    }
    .stat-chip-lbl { font-size: 12px; color: var(--g-text-2); font-weight: 500; }
    .stat-chip.blue  .stat-chip-val { color: var(--g-blue); }
    .stat-chip.yellow .stat-chip-val { color: var(--g-yellow); }
    .stat-chip.orange .stat-chip-val { color: #e37400; }
    .stat-chip.green  .stat-chip-val { color: var(--g-green); }

    /* LISTA + PAINEL LADO A LADO no desktop */
    .dashboard-grid {
      display: flex;
      gap: 0;
      align-items: flex-start;
    }

    .pedido-list-wrap {
      flex: 1;
      min-width: 0;
      background: var(--g-surface);
      border-radius: var(--g-radius-lg);
      border: 1px solid var(--g-border);
      overflow: hidden;
    }

    /* PAINEL DE AÇÕES (drawer direito no desktop) */
    .acao-panel {
      width: 320px;
      flex-shrink: 0;
      background: var(--g-surface);
      border: 1px solid var(--g-border);
      border-left: none;
      border-radius: 0 var(--g-radius-lg) var(--g-radius-lg) 0;
      display: none;
      flex-direction: column;
      position: sticky;
      top: 20px;
      max-height: calc(100vh - 40px);
      overflow-y: auto;
    }
    .acao-panel.visible { display: flex; }
    @media (max-width: 959px) {
      .acao-panel {
        display: none !important; /* no mobile usa modal/sheet */
      }
    }

    .acao-panel-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--g-border);
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px;
    }
    .acao-panel-nome {
      font-family: 'Google Sans', sans-serif;
      font-size: 15px;
      font-weight: 500;
      color: var(--g-text);
    }
    .acao-panel-instr {
      font-size: 12px;
      color: var(--g-text-2);
      margin-top: 2px;
    }
    .acao-panel-close {
      width: 28px; height: 28px;
      border-radius: 50%;
      background: var(--g-hover);
      border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; color: var(--g-text-2);
      flex-shrink: 0;
    }
    .acao-panel-close:hover { background: var(--g-border); }

    .acao-panel-status {
      padding: 12px 20px;
      border-bottom: 1px solid var(--g-border);
    }

    .acao-panel-orc {
      padding: 10px 20px;
      background: #e6f4ea;
      border-bottom: 1px solid #c8e6c9;
      font-size: 13px;
      color: var(--g-green);
      font-weight: 500;
      display: flex; gap: 12px; flex-wrap: wrap;
    }

    .acao-section {
      padding: 14px 20px 4px;
    }
    .acao-section-label {
      font-size: 11px;
      font-weight: 600;
      color: var(--g-text-3);
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 8px;
    }
    .acao-btns {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .acao-btns .btn {
      width: 100%;
      justify-content: flex-start;
      border-radius: 10px;
      padding: 9px 14px;
      font-size: 13px;
    }

    .acao-panel-link {
      padding: 12px 20px;
      border-top: 1px solid var(--g-border);
      margin-top: auto;
    }
    .acao-panel-link a {
      display: block;
      text-align: center;
      font-size: 13px;
      color: var(--g-text-2);
      padding: 8px;
      border-radius: 8px;
      transition: background .15s;
    }
    .acao-panel-link a:hover { background: var(--g-hover); text-decoration: none; }

    /* MODAL AÇÕES no mobile */
    .acao-sheet {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 500;
    }
    .acao-sheet.open { display: flex; flex-direction: column; justify-content: flex-end; }
    .acao-sheet-overlay {
      position: absolute; inset: 0;
      background: rgba(0,0,0,.4);
    }
    .acao-sheet-box {
      position: relative;
      background: var(--g-surface);
      border-radius: 20px 20px 0 0;
      max-height: 85vh;
      overflow-y: auto;
      padding-bottom: 32px;
    }
    .acao-sheet-drag {
      width: 40px; height: 4px;
      background: var(--g-border);
      border-radius: 2px;
      margin: 12px auto 0;
    }

    /* PEDIDO SELECIONADO */
    .pedido-item.selected {
      background: var(--g-blue-light);
    }

    /* ORÇAMENTO INLINE no painel */
    .orc-inline { padding: 0 20px 16px; }
    .orc-inline label { font-size: 11px; font-weight: 600; color: var(--g-text-2); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; display: block; margin-top: 12px; }
    .orc-inline input {
      width: 100%; padding: 9px 12px;
      border: 1px solid var(--g-border); border-radius: 8px;
      font-size: 13px; color: var(--g-text);
      background: var(--g-bg); font-family: inherit; outline: none;
    }
    .orc-inline input:focus { border-color: var(--g-blue); background: var(--g-surface); }
    .sim-row { display: flex; gap: 8px; margin: 10px 0; }
    .sim-mini {
      flex: 1; border: 2px solid var(--g-border); border-radius: 10px;
      padding: 10px; cursor: pointer; text-align: center;
      transition: border-color .15s, background .15s;
      background: var(--g-surface);
    }
    .sim-mini:hover { border-color: var(--g-blue); }
    .sim-mini.ativo { border-color: var(--g-blue); background: var(--g-blue-light); }
    .sim-mini-label { font-size: 9px; font-weight: 700; text-transform: uppercase; color: var(--g-text-3); margin-bottom: 4px; letter-spacing: .4px; }
    .sim-mini-valor { font-family: 'Google Sans', sans-serif; font-size: 15px; font-weight: 700; color: var(--g-green); }
    .sim-mini.maq .sim-mini-valor { color: #e65100; }
    .sim-mini-sub { font-size: 10px; color: var(--g-text-3); margin-top: 3px; }
    .orc-aviso {
      font-size: 11px; color: var(--g-text-2);
      background: var(--g-bg); border-radius: 8px;
      padding: 8px 12px; border-left: 3px solid var(--g-blue);
      line-height: 1.5; margin: 8px 0;
    }

    /* MOTIVO REPROVAÇÃO inline */
    .rep-inline { padding: 0 20px 12px; }
    .rep-inline textarea {
      width: 100%; padding: 9px 12px;
      border: 1px solid var(--g-border); border-radius: 8px;
      font-size: 13px; font-family: inherit; resize: vertical; min-height: 70px;
      background: var(--g-bg); outline: none;
    }
    .rep-inline textarea:focus { border-color: var(--g-red); background: var(--g-surface); }
    </style>
</head>
<body>

<!-- SIDEBAR OVERLAY (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<div class="app-layout">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png" alt="Adonis">
        <span class="sidebar-logo-title">Adonis</span>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Principal</div>
        <a href="dashboard.php" class="sidebar-link active">
            <span class="nav-icon">📋</span> Todos os Pedidos
            <?php if ($stats['total'] > 0): ?><span class="nav-badge"><?php echo $stats['total']; ?></span><?php endif; ?>
        </a>
        <a href="dashboard.php?status=Pre-OS" class="sidebar-link">
            <span class="nav-icon">🗒️</span> Pré-OS
            <?php if (($stats_map['Pre-OS']??0) > 0): ?><span class="nav-badge" style="background:#e37400"><?php echo $stats_map['Pre-OS']??0; ?></span><?php endif; ?>
        </a>
        <a href="dashboard.php?status=Em analise" class="sidebar-link">
            <span class="nav-icon">🔍</span> Em Análise
        </a>
        <a href="dashboard.php?status=Orcada" class="sidebar-link">
            <span class="nav-icon">💰</span> Orçadas
        </a>
        <a href="dashboard.php?status=Aguardando aprovacao" class="sidebar-link">
            <span class="nav-icon">⏳</span> Aguard. Aprovação
        </a>
    </div>

    <hr class="sidebar-divider">

    <div class="sidebar-section">
        <div class="sidebar-section-label">Execução</div>
        <a href="dashboard.php?status=Aprovada" class="sidebar-link">
            <span class="nav-icon">💳</span> Aguard. Pagamento
        </a>
        <a href="dashboard.php?status=Instrumento recebido" class="sidebar-link">
            <span class="nav-icon">📦</span> Instr. Recebido
        </a>
        <a href="dashboard.php?status=Em desenvolvimento" class="sidebar-link">
            <span class="nav-icon">⚙️</span> Em Execução
            <?php if ($stats['execucao'] > 0): ?><span class="nav-badge" style="background:#7b1fa2"><?php echo $stats['execucao']; ?></span><?php endif; ?>
        </a>
        <a href="dashboard.php?status=Servico finalizado" class="sidebar-link">
            <span class="nav-icon">🎸</span> Serviço Finalizado
        </a>
        <a href="dashboard.php?status=Pronto para retirada" class="sidebar-link">
            <span class="nav-icon">🎉</span> Pronto p/ Retirada
        </a>
    </div>

    <hr class="sidebar-divider">

    <div class="sidebar-section">
        <div class="sidebar-section-label">Encerrados</div>
        <a href="dashboard.php?status=Entregue" class="sidebar-link">
            <span class="nav-icon">🏁</span> Entregues
        </a>
        <a href="dashboard.php?status=Reprovada" class="sidebar-link">
            <span class="nav-icon">❌</span> Reprovados
        </a>
        <a href="dashboard.php?status=Cancelada" class="sidebar-link">
            <span class="nav-icon">🚫</span> Cancelados
        </a>
    </div>

    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?php echo strtoupper(substr($_SESSION['admin_nome']??'A',0,1)); ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['admin_nome']??'Admin'); ?></div>
            <div class="sidebar-user-role">Administrador</div>
        </div>
        <a href="logout.php" class="sidebar-logout" title="Sair">🚪</a>
    </div>
</aside>

<!-- CONTEÚDO PRINCIPAL -->
<main class="main-content">

    <!-- TOP BAR mobile -->
    <div class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()">☰</button>
        <div class="topbar-logo"><img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png" alt="Adonis"></div>
        <span class="topbar-title">Painel</span>
        <a href="logout.php" style="font-size:20px;color:var(--g-text-2);text-decoration:none">🚪</a>
    </div>

    <div class="page-content">

        <!-- STATS -->
        <div class="stats-row">
            <div class="stat-chip blue" onclick="location.href='dashboard.php'">
                <div><div class="stat-chip-val"><?php echo $stats['total']; ?></div><div class="stat-chip-lbl">Total</div></div>
            </div>
            <div class="stat-chip yellow" onclick="location.href='dashboard.php?status=Pre-OS'">
                <div><div class="stat-chip-val"><?php echo $stats['pendentes']; ?></div><div class="stat-chip-lbl">Pendentes</div></div>
            </div>
            <div class="stat-chip orange" onclick="location.href='dashboard.php?status=Orcada'">
                <div><div class="stat-chip-val"><?php echo $stats['orcadas']; ?></div><div class="stat-chip-lbl">Orçadas</div></div>
            </div>
            <div class="stat-chip green" onclick="location.href='dashboard.php?status=Em desenvolvimento'">
                <div><div class="stat-chip-val"><?php echo $stats['execucao']; ?></div><div class="stat-chip-lbl">Em Execução</div></div>
            </div>
        </div>

        <!-- BUSCA -->
        <form method="GET" action="" id="form-busca" style="margin-bottom:12px">
            <div class="search-bar">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" placeholder="Buscar por cliente, instrumento..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filtro_status); ?>">
            </div>
        </form>

        <!-- CHIPS -->
        <div class="chips-row" style="margin-bottom:16px">
            <?php foreach ($filtros_chips as $val => $label): ?>
            <a href="?status=<?php echo urlencode($val); ?>&q=<?php echo urlencode($busca); ?>" class="chip<?php echo ($filtro_status === $val) ? ' active' : ''; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <!-- LISTA + PAINEL -->
        <div class="dashboard-grid">
            <div class="pedido-list-wrap">
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
                    $info   = $status_map[$p['status']] ?? ['label'=>$p['status'],'badge'=>'badge-secondary','icone'=>'•'];
                    $ini    = iniciais($p['cliente_nome'] ?? '?');
                    $instr  = trim(($p['instr_tipo']??'').' '.($p['instr_marca']??'').' '.($p['instr_modelo']??''));
                    $data   = date('d/m', strtotime($p['atualizado_em']));
                    $acoes  = json_encode($acoes_por_status[$p['status']] ?? []);
                    $tv     = $totais_servicos[$p['id']] ?? 0;
                    $orc    = (float)($p['valor_orcamento']??0);
                ?>
                <div class="pedido-item" id="pedido-<?php echo $p['id']; ?>"
                     onclick="abrirAcoes(<?php echo $p['id']; ?>, <?php echo htmlspecialchars(json_encode($p['cliente_nome']??''), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($instr), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($p['status']), ENT_QUOTES); ?>, <?php echo $acoes; ?>, <?php echo $tv; ?>, <?php echo $orc; ?>, <?php echo htmlspecialchars(json_encode($p['telefone']??''), ENT_QUOTES); ?>)">
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

            <!-- PAINEL DE AÇÕES (desktop) -->
            <div class="acao-panel" id="acao-panel">
                <div id="acao-panel-content">
                    <!-- preenchido via JS -->
                </div>
            </div>
        </div>

        <div style="height:24px"></div>
    </div>
</main>
</div>

<!-- BOTTOM NAV mobile -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="active"><span class="nav-icon">📋</span>Pedidos</a>
    <a href="dashboard.php?status=Pre-OS"><span class="nav-icon">⏳</span>Pendentes</a>
    <a href="dashboard.php?status=Em desenvolvimento"><span class="nav-icon">⚙️</span>Execução</a>
    <a href="logout.php"><span class="nav-icon">🚪</span>Sair</a>
</nav>

<!-- SHEET DE AÇÕES (mobile) -->
<div class="acao-sheet" id="acao-sheet">
    <div class="acao-sheet-overlay" onclick="fecharSheet()"></div>
    <div class="acao-sheet-box">
        <div class="acao-sheet-drag"></div>
        <div id="acao-sheet-content" style="padding:4px 0 8px"></div>
    </div>
</div>

<!-- MODALS (compartilhados) -->
<div class="modal-overlay" id="modal-orcamento">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title">💰 Definir Orçamento</div>
        <label>Valor dos serviços (R$)</label>
        <input type="number" id="modal-input-valor" min="0" step="0.01" placeholder="Ex: 350.00" oninput="simularModal()">
        <label>Prazo (dias úteis)</label>
        <input type="number" id="modal-input-prazo" min="1" step="1" placeholder="Ex: 7">
        <div class="modal-hint">💡 Sem sábados, domingos e feriados</div>
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

<div class="modal-overlay" id="modal-reprovacao">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title">❌ Motivo da Reprovação</div>
        <label>Descreva o motivo</label>
        <textarea id="modal-motivo" placeholder="Ex: Peça indisponível..."></textarea>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="fecharModal('modal-reprovacao')">Cancelar</button>
            <button class="btn btn-danger" onclick="confirmarModalReprovacao()">Confirmar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-wa">
    <div class="modal-box" style="max-width:420px;text-align:center">
        <div class="modal-drag"></div>
        <div class="modal-title">📲 Avisar o cliente?</div>
        <div style="font-size:14px;color:var(--g-text-2);margin-bottom:20px" id="wa-texto">Status atualizado!</div>
        <a id="btn-wa" href="#" target="_blank" class="btn-wa" onclick="_waReload()">💬 WhatsApp</a>
        <button class="btn-wa-skip" onclick="_waReload()">Pular — recarregar</button>
    </div>
</div>

<script>
// ── Dados dos pedidos para o painel ──────────────────────────
const _statusMap = <?php echo json_encode(array_map(fn($v)=>$v['icone'].' '.$v['label'], $status_map)); ?>;
const _badgeMap  = <?php echo json_encode(array_map(fn($v)=>['badge'=>$v['badge'],'icone'=>$v['icone'],'label'=>$v['label']], $status_map)); ?>;

// ── Sidebar mobile ───────────────────────────────────────────
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}

// ── Estado do painel ─────────────────────────────────────────
let _idAtual = null, _telefoneAtual = '';
let _orcEscolhido = null;

function abrirAcoes(id, nome, instr, status, acoes, totalBase, orcAtual, telefone) {
    // remove selected anterior
    document.querySelectorAll('.pedido-item.selected').forEach(el => el.classList.remove('selected'));
    document.getElementById('pedido-'+id)?.classList.add('selected');

    _idAtual = id;
    _telefoneAtual = telefone;

    const bmap = _badgeMap[status] ?? {badge:'badge-secondary', icone:'•', label: status};
    const badgeHtml = `<span class="badge ${bmap.badge}">${bmap.icone} ${bmap.label}</span>`;
    const orcHtml = orcAtual > 0 ? `<div class="acao-panel-orc">💰 R$ ${fmt(orcAtual)}</div>` : '';

    const acoesHtml = renderAcoes(acoes, totalBase, status, false);

    const content = `
        <div class="acao-panel-header">
            <div>
                <div class="acao-panel-nome">${escHtml(nome)}</div>
                <div class="acao-panel-instr">${escHtml(instr) || 'Instrumento não informado'}</div>
            </div>
            <button class="acao-panel-close" onclick="fecharPainel()">✕</button>
        </div>
        <div class="acao-panel-status">${badgeHtml}</div>
        ${orcHtml}
        ${acoesHtml}
        <div class="acao-panel-link">
            <a href="detalhes.php?id=${id}">Ver detalhes completos →</a>
        </div>
    `;

    document.getElementById('acao-panel-content').innerHTML = content;
    document.getElementById('acao-panel').classList.add('visible');

    // mobile: abrir sheet
    if (window.innerWidth < 960) {
        const sheetContent = `
            <div style="padding:16px 20px 8px">
                <div style="font-family:'Google Sans',sans-serif;font-size:16px;font-weight:500;color:var(--g-text)">${escHtml(nome)}</div>
                <div style="font-size:12px;color:var(--g-text-2);margin:2px 0 10px">${escHtml(instr)||'Instrumento não informado'}</div>
                ${badgeHtml}
                ${orcAtual > 0 ? `<div style="margin-top:8px;font-size:13px;color:var(--g-green);font-weight:500">💰 R$ ${fmt(orcAtual)}</div>` : ''}
            </div>
            ${renderAcoes(acoes, totalBase, status, true)}
            <div style="padding:12px 20px">
                <a href="detalhes.php?id=${id}" class="btn btn-secondary" style="width:100%;justify-content:center">Ver detalhes →</a>
            </div>`;
        document.getElementById('acao-sheet-content').innerHTML = sheetContent;
        document.getElementById('acao-sheet').classList.add('open');
    }
}

function fecharPainel() {
    document.getElementById('acao-panel').classList.remove('visible');
    document.querySelectorAll('.pedido-item.selected').forEach(el => el.classList.remove('selected'));
    _idAtual = null;
}
function fecharSheet() {
    document.getElementById('acao-sheet').classList.remove('open');
    document.querySelectorAll('.pedido-item.selected').forEach(el => el.classList.remove('selected'));
    _idAtual = null;
}

function renderAcoes(acoes, totalBase, status, isMobile) {
    if (!acoes || acoes.length === 0) {
        return `<div class="acao-section"><div style="font-size:13px;color:var(--g-text-3);padding:8px 0">Nenhuma ação disponível para este status.</div></div>`;
    }

    let html = `<div class="acao-section"><div class="acao-section-label">Ações disponíveis</div><div class="acao-btns">`;
    for (const a of acoes) {
        const [s, label, cls, modal] = a;
        if (modal === 'modal-orc') {
            html += `<button class="btn ${cls}" onclick="_abrirOrcamento(${totalBase})">${label}</button>`;
        } else if (modal === 'modal-rep') {
            html += `<button class="btn ${cls}" onclick="_abrirReprovacao()">${label}</button>`;
        } else {
            html += `<button class="btn ${cls}" onclick="_enviar('${s.replace(/'/g,"\\'")}')"><span>${label}</span></button>`;
        }
    }
    html += `</div></div>`;
    return html;
}

// ── Orçamento ────────────────────────────────────────────────
function taxaMaq(v) { return v > 2000 ? 15.38 : 21.58; }
function fmt(v) { return 'R$ '+Number(v).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
let _orcTipoEscolhido = null;

function _abrirOrcamento(totalBase) {
    _orcTipoEscolhido = null;
    document.getElementById('modal-input-valor').value = totalBase > 0 ? Number(totalBase).toFixed(2) : '';
    document.getElementById('modal-input-prazo').value = '';
    document.getElementById('modal-valor-final').value = '';
    document.getElementById('modal-btn-orc').disabled = true;
    document.getElementById('modal-sim-aviso').style.display = 'none';
    ['modal-card-base','modal-card-maquina'].forEach(id => document.getElementById(id)?.classList.remove('ativo'));
    simularModal();
    document.getElementById('modal-orcamento').classList.add('aberto');
    setTimeout(() => document.getElementById('modal-input-valor').focus(), 150);
}

function simularModal() {
    const v = parseFloat(document.getElementById('modal-input-valor').value);
    if (isNaN(v) || v <= 0) {
        document.getElementById('modal-sim-base').textContent = '—';
        document.getElementById('modal-sim-maq').textContent = '—';
        return;
    }
    const taxa = taxaMaq(v);
    const inteiro = Math.ceil(v * (1 + taxa/100));
    const real = inteiro / (1 + taxa/100);
    document.getElementById('modal-sim-base').textContent = fmt(v);
    document.getElementById('modal-sim-maq').textContent = fmt(inteiro);
    document.getElementById('modal-sim-maq-sub').innerHTML = `Elo/Amex 10x (${taxa.toFixed(2)}%)<br>Digitar ${fmt(real)} na máquina`;
    if (_orcTipoEscolhido === 'base') document.getElementById('modal-valor-final').value = v.toFixed(2);
    if (_orcTipoEscolhido === 'maquina') document.getElementById('modal-valor-final').value = inteiro.toFixed(2);
    if (_orcTipoEscolhido) _atualizarAvisoModal(v);
}

function escolherModalValor(tipo) {
    const v = parseFloat(document.getElementById('modal-input-valor').value);
    if (isNaN(v) || v <= 0) { _toast('Informe o valor primeiro', false); return; }
    _orcTipoEscolhido = tipo;
    document.getElementById('modal-card-base').classList.toggle('ativo', tipo==='base');
    document.getElementById('modal-card-maquina').classList.toggle('ativo', tipo==='maquina');
    const taxa = taxaMaq(v);
    const inteiro = Math.ceil(v * (1 + taxa/100));
    document.getElementById('modal-valor-final').value = (tipo==='base' ? v : inteiro).toFixed(2);
    _atualizarAvisoModal(v);
    document.getElementById('modal-btn-orc').disabled = false;
}

function _atualizarAvisoModal(v) {
    const taxa = taxaMaq(v);
    const inteiro = Math.ceil(v * (1 + taxa/100));
    const real = inteiro / (1 + taxa/100);
    const el = document.getElementById('modal-sim-aviso');
    el.style.display = 'block';
    el.innerHTML = _orcTipoEscolhido === 'base'
        ? `<strong>Enviando ao cliente: ${fmt(v)}</strong>`
        : `<strong>Enviando ao cliente: ${fmt(inteiro)}</strong><br>🖳️ Digitar na máquina: <strong>${fmt(real)}</strong> em <strong>10x</strong>.`;
}

function confirmarModalOrcamento() {
    const vf = parseFloat(document.getElementById('modal-valor-final').value);
    const pr = parseInt(document.getElementById('modal-input-prazo').value);
    if (isNaN(vf) || vf <= 0) { _toast('Escolha o valor a enviar', false); return; }
    if (isNaN(pr) || pr <= 0) { _toast('Informe o prazo', false); return; }
    fecharModal('modal-orcamento');
    _enviar('Orcada', {valor_orcamento: vf, prazo_orcamento: pr});
}

function _abrirReprovacao() {
    document.getElementById('modal-motivo').value = '';
    document.getElementById('modal-reprovacao').classList.add('aberto');
    setTimeout(() => document.getElementById('modal-motivo').focus(), 150);
}

function confirmarModalReprovacao() {
    const m = document.getElementById('modal-motivo').value.trim();
    if (!m) { _toast('Informe o motivo', false); return; }
    fecharModal('modal-reprovacao');
    _enviar('Reprovada', {motivo: m});
}

// ── Modais ────────────────────────────────────────────────────
function fecharModal(id) { document.getElementById(id).classList.remove('aberto'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('aberto'); });
});

// ── WhatsApp ─────────────────────────────────────────────────
function _waReload() { fecharModal('modal-wa'); setTimeout(() => location.reload(), 300); }
function _abrirWa(link, label) {
    document.getElementById('wa-texto').innerHTML = `✅ Status atualizado para <strong>${label}</strong>. Avisar o cliente?`;
    document.getElementById('btn-wa').href = link;
    document.getElementById('modal-wa').classList.add('aberto');
}

// ── Fetch ─────────────────────────────────────────────────────
function _toast(msg, ok) {
    const el = document.createElement('div');
    el.className = 'g-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

function _enviar(status, extras = {}) {
    if (!_idAtual) return;
    const label = _statusMap[status] || status;
    fetch('atualizar_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: _idAtual, status, ...extras})
    })
    .then(r => r.json())
    .then(data => {
        if (data.sucesso) {
            if (data.wa_link) _abrirWa(data.wa_link, label);
            else { _toast('✅ Status atualizado!', true); setTimeout(() => location.reload(), 1200); }
        } else _toast('❌ ' + (data.erro || 'Erro desconhecido'), false);
    })
    .catch(() => _toast('❌ Erro de conexão', false));
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<script src="assets/js/admin.js?v=<?php echo $v; ?>"></script>
</body>
</html>
