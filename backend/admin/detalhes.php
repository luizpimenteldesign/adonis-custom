<?php
/**
 * DETALHES DO PEDIDO — SISTEMA ADONIS
 * Visual: Google / Material Design 3
 * Versão: 6.0 — Sidebar + Layout 2 colunas + Ações condicionais
 */
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header('Location: dashboard.php'); exit; }
$preos_id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("
        SELECT p.*, c.nome as cliente_nome, c.telefone as cliente_telefone,
               c.email as cliente_email, c.endereco as cliente_endereco,
               i.id as instrumento_id, i.tipo as instrumento_tipo, i.marca as instrumento_marca,
               i.modelo as instrumento_modelo, i.referencia as instrumento_referencia,
               i.cor as instrumento_cor, i.numero_serie as instrumento_serie
        FROM pre_os p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN instrumentos i ON p.instrumento_id = i.id
        WHERE p.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $preos_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) { header('Location: dashboard.php?erro=nao_encontrado'); exit; }

    $stmt_s = $conn->prepare("SELECT s.id, s.nome, s.descricao, s.valor_base, s.prazo_base FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id = s.id WHERE ps.pre_os_id = :id");
    $stmt_s->execute([':id' => $preos_id]);
    $servicos = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
    $total_valor = 0; $total_prazo = 0;
    foreach ($servicos as $s) { $total_valor += (float)$s['valor_base']; $total_prazo += (int)$s['prazo_base']; }

    $fotos = [];
    if (!empty($pedido['instrumento_id'])) {
        $stmt_f = $conn->prepare("SELECT caminho, ordem FROM instrumento_fotos WHERE instrumento_id = :id ORDER BY ordem ASC");
        $stmt_f->execute([':id' => $pedido['instrumento_id']]);
        $fotos = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
    }

    $historico = [];
    try {
        $stmt_h = $conn->prepare("SELECT h.status, h.valor_orcamento, h.prazo_orcamento, h.motivo, h.criado_em, a.nome as admin_nome FROM status_historico h LEFT JOIN admins a ON h.admin_id = a.id WHERE h.pre_os_id = :id ORDER BY h.criado_em ASC");
        $stmt_h->execute([':id' => $preos_id]);
        $historico = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    $pagamento_info = null;
    try {
        $stmt_p = $conn->prepare("SELECT forma_pagamento, parcelas, valor_final, por_parcela, descricao_pagamento FROM status_historico WHERE pre_os_id = :id AND status = 'Aprovada' ORDER BY criado_em DESC LIMIT 1");
        $stmt_p->execute([':id' => $preos_id]);
        $pagamento_info = $stmt_p->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

} catch (PDOException $e) {
    header('Location: dashboard.php?erro=banco'); exit;
}

$status_map = [
    'Pre-OS'                        => ['label'=>'Pré-OS',                   'badge'=>'badge-new',     'icone'=>'🗒️'],
    'Em analise'                    => ['label'=>'Em Análise',              'badge'=>'badge-info',    'icone'=>'🔍'],
    'Orcada'                        => ['label'=>'Orçada',                  'badge'=>'badge-warning', 'icone'=>'💰'],
    'Aguardando aprovacao'          => ['label'=>'Aguard. Aprovação',     'badge'=>'badge-warning', 'icone'=>'⏳'],
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

// Ações condicionais por status (igual ao dashboard)
$acoes_por_status = [
    'Pre-OS'               => [['Em analise','Iniciar Análise','btn-info'],['Orcada','Orçar','btn-warning','modal-orc'],['Reprovada','Reprovar','btn-danger','modal-rep'],['Cancelada','Cancelar','btn-dark']],
    'Em analise'           => [['Orcada','Orçar','btn-warning','modal-orc'],['Reprovada','Reprovar','btn-danger','modal-rep'],['Cancelada','Cancelar','btn-dark']],
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
    'Reprovada'            => [['Em analise','Reabrir Análise','btn-info']],
    'Cancelada'            => [['Pre-OS','Reabrir','btn-info']],
];

$status_info  = $status_map[$pedido['status']] ?? ['label'=>$pedido['status'],'badge'=>'badge-secondary','icone'=>'•'];
$acoes_atuais = $acoes_por_status[$pedido['status']] ?? [];
$v = time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?php echo $pedido['id']; ?> — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <style>
    /* ── LAYOUT SIDEBAR (igual ao dashboard) ──────────────── */
    .app-layout { display:flex; min-height:100vh; }

    .sidebar {
      width:240px; flex-shrink:0;
      background:var(--g-surface); border-right:1px solid var(--g-border);
      display:flex; flex-direction:column;
      position:fixed; top:0; left:0; bottom:0;
      z-index:200; overflow-y:auto;
      transform:translateX(-100%); transition:transform .25s ease;
    }
    .sidebar.open { transform:translateX(0); }
    @media (min-width:960px) { .sidebar { transform:translateX(0); position:sticky; top:0; height:100vh; } }

    .sidebar-logo { padding:20px 20px 10px; display:flex; align-items:center; gap:10px; }
    .sidebar-logo img { height:36px; }
    .sidebar-logo-title { font-family:'Google Sans',sans-serif; font-size:16px; font-weight:700; color:var(--g-text); }
    .sidebar-section { padding:8px 0; }
    .sidebar-section-label { font-size:11px; font-weight:600; color:var(--g-text-3); text-transform:uppercase; letter-spacing:.6px; padding:8px 20px 4px; }
    .sidebar-link { display:flex; align-items:center; gap:12px; padding:10px 20px; font-size:14px; font-weight:500; color:var(--g-text-2); text-decoration:none; border-radius:0 24px 24px 0; margin-right:12px; transition:background .15s,color .15s; -webkit-tap-highlight-color:transparent; }
    .sidebar-link:hover { background:var(--g-hover); color:var(--g-text); text-decoration:none; }
    .sidebar-link.active { background:var(--g-blue-light); color:var(--g-blue); }
    .sidebar-link .nav-icon { font-size:18px; flex-shrink:0; width:22px; text-align:center; }
    .sidebar-divider { border:none; border-top:1px solid var(--g-border); margin:8px 0; }
    .sidebar-user { margin-top:auto; padding:16px 20px; border-top:1px solid var(--g-border); display:flex; align-items:center; gap:10px; }
    .sidebar-user-avatar { width:36px; height:36px; border-radius:50%; background:var(--g-blue-light); color:var(--g-blue); display:flex; align-items:center; justify-content:center; font-family:'Google Sans',sans-serif; font-size:14px; font-weight:700; flex-shrink:0; }
    .sidebar-user-info { flex:1; min-width:0; }
    .sidebar-user-name { font-size:13px; font-weight:500; color:var(--g-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar-user-role { font-size:11px; color:var(--g-text-3); }
    .sidebar-logout { color:var(--g-text-3); text-decoration:none; font-size:18px; padding:4px; flex-shrink:0; }
    .sidebar-logout:hover { color:var(--g-red); text-decoration:none; }
    .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:199; }
    .sidebar-overlay.open { display:block; }

    /* MAIN */
    .main-content { flex:1; min-width:0; display:flex; flex-direction:column; }

    /* TOP BAR mobile */
    .topbar { position:sticky; top:0; z-index:100; height:56px; background:var(--g-surface); border-bottom:1px solid var(--g-border); display:flex; align-items:center; padding:0 16px; gap:12px; }
    @media (min-width:960px) { .topbar { display:none; } }
    .topbar-title { font-family:'Google Sans',sans-serif; font-size:17px; font-weight:500; color:var(--g-text); flex:1; }
    .btn-menu { width:40px; height:40px; display:flex; align-items:center; justify-content:center; border:none; background:none; font-size:20px; cursor:pointer; border-radius:50%; color:var(--g-text-2); -webkit-tap-highlight-color:transparent; }
    .btn-menu:hover { background:var(--g-hover); }

    /* CONTENT */
    .page-content { flex:1; padding:20px; }
    @media (min-width:960px) { body { padding-bottom:0; } .bottom-nav { display:none; } }

    /* CABECALHO DA PÁGINA */
    .page-header {
      display:flex; align-items:center; gap:12px;
      margin-bottom:20px;
      padding-bottom:16px;
      border-bottom:1px solid var(--g-border);
    }
    .page-header-back {
      display:flex; align-items:center; justify-content:center;
      width:36px; height:36px; border-radius:50%;
      color:var(--g-text-2); text-decoration:none; font-size:18px;
      transition:background .15s; flex-shrink:0;
    }
    .page-header-back:hover { background:var(--g-hover); text-decoration:none; }
    .page-header-info { flex:1; min-width:0; }
    .page-header-title { font-family:'Google Sans',sans-serif; font-size:18px; font-weight:500; color:var(--g-text); }
    .page-header-sub { font-size:13px; color:var(--g-text-2); margin-top:2px; }

    /* LAYOUT 2 COLUNAS */
    .detalhes-grid {
      display:grid;
      grid-template-columns:1fr;
      gap:16px;
      align-items:start;
    }
    @media (min-width:900px) {
      .detalhes-grid { grid-template-columns:1fr 320px; }
    }

    /* COLUNA ESQUERDA — info */
    .info-col {}

    /* COLUNA DIREITA — ações + histórico */
    .side-col {}

    /* SECTÕES (substitui os cards excessivos) */
    .sect {
      background:var(--g-surface);
      border:1px solid var(--g-border);
      border-radius:var(--g-radius-lg);
      margin-bottom:12px;
      overflow:hidden;
    }
    .sect-title {
      font-family:'Google Sans',sans-serif;
      font-size:14px; font-weight:500;
      color:var(--g-text-2);
      padding:14px 20px 10px;
      border-bottom:1px solid var(--g-border);
      text-transform:uppercase;
      letter-spacing:.4px;
      font-size:11px;
    }

    /* STATUS HERO */
    .status-block {
      padding:16px 20px;
      display:flex; align-items:center; gap:14px;
    }
    .status-block-badge { flex-shrink:0; }
    .status-block-label { font-family:'Google Sans',sans-serif; font-size:17px; font-weight:500; color:var(--g-text); }
    .status-block-meta { font-size:12px; color:var(--g-text-3); margin-top:3px; }

    .orc-banner {
      display:flex; gap:16px; flex-wrap:wrap;
      padding:10px 20px;
      background:#e6f4ea;
      border-top:1px solid #c8e6c9;
      font-size:13px; font-weight:500; color:var(--g-green);
    }
    .rep-banner {
      padding:10px 20px;
      background:#fce8e6;
      border-top:1px solid #f5c6c3;
      font-size:13px; color:var(--g-red);
    }

    /* AÇÕES */
    .acoes-sect { background:var(--g-surface); border:1px solid var(--g-border); border-radius:var(--g-radius-lg); overflow:hidden; margin-bottom:12px; }
    .acoes-title { font-size:11px; font-weight:600; color:var(--g-text-3); text-transform:uppercase; letter-spacing:.5px; padding:14px 20px 8px; }
    .acoes-list { padding:0 16px 16px; display:flex; flex-direction:column; gap:6px; }
    .acoes-list .btn { justify-content:flex-start; border-radius:10px; font-size:13px; }
    .acoes-vazio { padding:12px 20px 16px; font-size:13px; color:var(--g-text-3); }

    /* INFO GRID (dentro das sect) */
    .ig { display:grid; grid-template-columns:1fr 1fr; gap:0; }
    .ig-item { padding:12px 20px; border-bottom:1px solid var(--g-border); }
    .ig-item:last-child, .ig-item:nth-last-child(2):not(.full) { border-bottom:none; }
    .ig-item.full { grid-column:1/-1; }
    .ig-label { font-size:11px; font-weight:600; color:var(--g-text-3); text-transform:uppercase; letter-spacing:.4px; margin-bottom:3px; }
    .ig-value { font-size:14px; color:var(--g-text); word-break:break-word; }
    @media (max-width:480px) { .ig { grid-template-columns:1fr; } .ig-item { border-bottom:1px solid var(--g-border); } }

    /* PAGAMENTO */
    .pgto-block { background:#e6f4ea; overflow:hidden; }
    .pgto-titulo { padding:14px 20px; font-size:13px; font-weight:600; color:#1e8e3e; border-bottom:1px solid #c8e6c9; }
    .pgto-row { display:flex; justify-content:space-between; align-items:center; padding:10px 20px; border-bottom:1px solid #c8e6c9; font-size:13px; }
    .pgto-row:last-child { border-bottom:none; }
    .pgto-lbl { color:#2e7d32; }
    .pgto-val { font-weight:600; color:#1b5e20; }
    .pgto-val.big { font-size:17px; }

    .maq-block { background:#fff8e1; overflow:hidden; }
    .maq-titulo { padding:14px 20px; font-size:13px; font-weight:600; color:#e65100; border-bottom:1px solid #ffe082; }
    .maq-row { display:flex; justify-content:space-between; align-items:center; padding:10px 20px; border-bottom:1px solid #ffe082; font-size:13px; }
    .maq-row:last-child { border-bottom:none; }
    .maq-lbl { color:#795548; }
    .maq-val { font-weight:600; color:#e65100; }
    .maq-val.big { font-size:17px; color:#bf360c; }
    .maq-obs { padding:10px 20px 14px; font-size:12px; color:#795548; }

    /* SERVIÇOS TABLE */
    .srv-table { width:100%; border-collapse:collapse; }
    .srv-table thead th { text-align:left; padding:10px 20px; font-size:11px; font-weight:600; color:var(--g-text-2); text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid var(--g-border); background:var(--g-bg); }
    .srv-table tbody td { padding:12px 20px; font-size:13px; border-bottom:1px solid var(--g-border); color:var(--g-text); vertical-align:top; }
    .srv-table tbody tr:last-child td { border-bottom:none; }
    .srv-table tfoot td { padding:12px 20px; font-weight:600; font-size:13px; border-top:2px solid var(--g-border); background:var(--g-bg); }
    .srv-nome-desc { font-size:11px; color:var(--g-text-2); margin-top:2px; }

    /* FOTOS */
    .fotos-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:4px; padding:4px; }
    .fotos-grid img { width:100%; aspect-ratio:1; object-fit:cover; border-radius:6px; cursor:pointer; transition:opacity .15s; }
    .fotos-grid img:hover { opacity:.85; }

    /* TIMELINE */
    .tl { list-style:none; padding:16px 20px; position:relative; }
    .tl::before { content:''; position:absolute; left:38px; top:16px; bottom:16px; width:2px; background:var(--g-border); }
    .tl-item { display:flex; gap:14px; padding-bottom:20px; }
    .tl-dot { width:38px; height:38px; border-radius:50%; background:var(--g-blue-light); color:var(--g-blue); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; z-index:1; }
    .tl-body { flex:1; padding-top:6px; }
    .tl-status { font-weight:500; font-size:13px; color:var(--g-text); }
    .tl-meta { font-size:12px; color:var(--g-text-3); margin-top:2px; }
    .tl-detalhe { margin-top:5px; padding:5px 10px; border-radius:6px; font-size:12px; display:inline-block; }
    .tl-detalhe.valor { background:#e6f4ea; color:#1e8e3e; }
    .tl-detalhe.motivo { background:#fce8e6; color:#c5221f; }

    /* TOKEN */
    .token-row { display:flex; align-items:center; gap:10px; padding:14px 20px; }
    .token-val { font-family:'Roboto Mono',monospace; font-size:11px; color:var(--g-text-2); background:var(--g-bg); border:1px solid var(--g-border); border-radius:6px; padding:7px 10px; flex:1; word-break:break-all; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="app-layout">
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png" alt="Adonis">
        <span class="sidebar-logo-title">Adonis</span>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-section-label">Principal</div>
        <a href="dashboard.php" class="sidebar-link"><span class="nav-icon">📋</span> Todos os Pedidos</a>
        <a href="dashboard.php?status=Pre-OS" class="sidebar-link"><span class="nav-icon">🗒️</span> Pré-OS</a>
        <a href="dashboard.php?status=Em analise" class="sidebar-link"><span class="nav-icon">🔍</span> Em Análise</a>
        <a href="dashboard.php?status=Orcada" class="sidebar-link"><span class="nav-icon">💰</span> Orçadas</a>
        <a href="dashboard.php?status=Aguardando aprovacao" class="sidebar-link"><span class="nav-icon">⏳</span> Aguard. Aprovação</a>
    </div>
    <hr class="sidebar-divider">
    <div class="sidebar-section">
        <div class="sidebar-section-label">Execução</div>
        <a href="dashboard.php?status=Aprovada" class="sidebar-link"><span class="nav-icon">💳</span> Aguard. Pagamento</a>
        <a href="dashboard.php?status=Instrumento recebido" class="sidebar-link"><span class="nav-icon">📦</span> Instr. Recebido</a>
        <a href="dashboard.php?status=Em desenvolvimento" class="sidebar-link"><span class="nav-icon">⚙️</span> Em Execução</a>
        <a href="dashboard.php?status=Servico finalizado" class="sidebar-link"><span class="nav-icon">🎸</span> Serviço Finalizado</a>
        <a href="dashboard.php?status=Pronto para retirada" class="sidebar-link"><span class="nav-icon">🎉</span> Pronto p/ Retirada</a>
    </div>
    <hr class="sidebar-divider">
    <div class="sidebar-section">
        <div class="sidebar-section-label">Encerrados</div>
        <a href="dashboard.php?status=Entregue" class="sidebar-link"><span class="nav-icon">🏁</span> Entregues</a>
        <a href="dashboard.php?status=Reprovada" class="sidebar-link"><span class="nav-icon">❌</span> Reprovados</a>
        <a href="dashboard.php?status=Cancelada" class="sidebar-link"><span class="nav-icon">🚫</span> Cancelados</a>
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
    <div class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()">☰</button>
        <span class="topbar-title">Pedido #<?php echo $pedido['id']; ?></span>
        <a href="logout.php" style="font-size:20px;color:var(--g-text-2);text-decoration:none">🚪</a>
    </div>

    <div class="page-content">

        <!-- CABEÇALHO -->
        <div class="page-header">
            <a href="dashboard.php" class="page-header-back">&#8592;</a>
            <div class="page-header-info">
                <div class="page-header-title"><?php echo htmlspecialchars($pedido['cliente_nome'] ?? 'Sem nome'); ?> <span style="font-weight:400;color:var(--g-text-2)">#<?php echo $pedido['id']; ?></span></div>
                <div class="page-header-sub"><?php echo htmlspecialchars(trim(($pedido['instrumento_tipo']??'').' '.($pedido['instrumento_marca']??'').' '.($pedido['instrumento_modelo']??'')) ?: 'Instrumento não informado'); ?></div>
            </div>
            <span class="badge <?php echo $status_info['badge']; ?>" id="status-badge"><?php echo $status_info['icone'].' '.$status_info['label']; ?></span>
        </div>

        <!-- GRID 2 COLUNAS -->
        <div class="detalhes-grid">

            <!-- COLUNA ESQUERDA -->
            <div class="info-col">

                <!-- STATUS -->
                <div class="sect">
                    <div class="sect-title">Status atual</div>
                    <div class="status-block">
                        <div>
                            <div class="status-block-label" id="status-label"><?php echo htmlspecialchars($status_info['label']); ?></div>
                            <div class="status-block-meta" id="atualizado-em">Atualizado <?php echo date('d/m/Y H:i', strtotime($pedido['atualizado_em'])); ?></div>
                        </div>
                    </div>
                    <?php if (!empty($pedido['valor_orcamento'])): ?>
                    <div class="orc-banner">
                        <span>R$ <?php echo number_format($pedido['valor_orcamento'],2,',','.'); ?></span>
                        <?php if (!empty($pedido['prazo_orcamento'])): ?>
                        <span><?php echo (int)$pedido['prazo_orcamento']; ?> dias úteis</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pedido['motivo_reprovacao'])): ?>
                    <div class="rep-banner"><strong>Motivo:</strong> <?php echo htmlspecialchars($pedido['motivo_reprovacao']); ?></div>
                    <?php endif; ?>
                </div>

                <!-- PAGAMENTO (se aprovado) -->
                <?php if (!empty($pagamento_info) && !empty($pagamento_info['forma_pagamento'])):
                    $forma    = $pagamento_info['forma_pagamento'];
                    $vf       = (float)($pagamento_info['valor_final'] ?? 0);
                    $parcelas = (int)($pagamento_info['parcelas'] ?? 0);
                    $porparc  = (float)($pagamento_info['por_parcela'] ?? 0);
                    $descpag  = $pagamento_info['descricao_pagamento'] ?? $forma;
                ?>
                <div class="sect">
                    <div class="pgto-block">
                        <div class="pgto-titulo">Pagamento escolhido pelo cliente</div>
                        <div class="pgto-row"><span class="pgto-lbl">Forma</span><span class="pgto-val"><?php echo htmlspecialchars($descpag ?: $forma); ?></span></div>
                        <div class="pgto-row"><span class="pgto-lbl">Valor total</span><span class="pgto-val big">R$ <?php echo number_format($vf,2,',','.'); ?></span></div>
                        <?php if ($parcelas > 0 && stripos($forma,'cart')!==false): ?>
                        <div class="pgto-row"><span class="pgto-lbl">Parcelas</span><span class="pgto-val"><?php echo $parcelas; ?>x de R$ <?php echo number_format($porparc,2,',','.'); ?></span></div>
                        <?php elseif (stripos($forma,'entrada')!==false): ?>
                        <div class="pgto-row"><span class="pgto-lbl">Entrada</span><span class="pgto-val">R$ <?php echo number_format($vf*0.5,2,',','.'); ?></span></div>
                        <div class="pgto-row"><span class="pgto-lbl">Retirada</span><span class="pgto-val">R$ <?php echo number_format($vf*0.5,2,',','.'); ?></span></div>
                        <?php endif; ?>
                    </div>
                    <?php if (stripos($forma,'cart')!==false && $parcelas > 0):
                        $taxa_aprox = $vf > 2000 ? 15.38 : 21.58;
                        $maq_real   = $vf / (1 + $taxa_aprox/100);
                    ?>
                    <div class="maq-block">
                        <div class="maq-titulo">Instrução para a maquininha</div>
                        <div class="maq-row"><span class="maq-lbl">Digite na máquina</span><span class="maq-val big">R$ <?php echo number_format($maq_real,2,',','.'); ?></span></div>
                        <div class="maq-row"><span class="maq-lbl">Parcelas</span><span class="maq-val"><?php echo $parcelas; ?>x</span></div>
                        <div class="maq-obs">Digite <strong>R$ <?php echo number_format($maq_real,2,',','.'); ?></strong> e selecione <strong><?php echo $parcelas; ?>x</strong>. Cliente paga exatamente <strong>R$ <?php echo number_format($vf,2,',','.'); ?></strong>.</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- CLIENTE -->
                <div class="sect">
                    <div class="sect-title">Cliente</div>
                    <div class="ig">
                        <div class="ig-item full"><div class="ig-label">Nome</div><div class="ig-value"><?php echo htmlspecialchars($pedido['cliente_nome']??''); ?></div></div>
                        <div class="ig-item">
                            <div class="ig-label">WhatsApp</div>
                            <div class="ig-value"><a href="https://wa.me/55<?php echo preg_replace('/\D/','',$pedido['cliente_telefone']??''); ?>" target="_blank"><?php echo htmlspecialchars($pedido['cliente_telefone']??''); ?></a></div>
                        </div>
                        <?php if (!empty($pedido['cliente_email'])): ?>
                        <div class="ig-item">
                            <div class="ig-label">E-mail</div>
                            <div class="ig-value"><a href="mailto:<?php echo htmlspecialchars($pedido['cliente_email']); ?>"><?php echo htmlspecialchars($pedido['cliente_email']); ?></a></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($pedido['cliente_endereco'])): ?>
                        <div class="ig-item full"><div class="ig-label">Endereço</div><div class="ig-value"><?php echo nl2br(htmlspecialchars($pedido['cliente_endereco'])); ?></div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- INSTRUMENTO -->
                <div class="sect">
                    <div class="sect-title">Instrumento</div>
                    <div class="ig">
                        <div class="ig-item"><div class="ig-label">Tipo</div><div class="ig-value"><?php echo htmlspecialchars($pedido['instrumento_tipo']??''); ?></div></div>
                        <div class="ig-item"><div class="ig-label">Marca</div><div class="ig-value"><?php echo htmlspecialchars($pedido['instrumento_marca']??''); ?></div></div>
                        <div class="ig-item"><div class="ig-label">Modelo</div><div class="ig-value"><?php echo htmlspecialchars($pedido['instrumento_modelo']??''); ?></div></div>
                        <?php if (!empty($pedido['instrumento_cor'])): ?>
                        <div class="ig-item"><div class="ig-label">Cor</div><div class="ig-value"><?php echo htmlspecialchars($pedido['instrumento_cor']); ?></div></div>
                        <?php endif; ?>
                        <?php if (!empty($pedido['instrumento_referencia'])): ?>
                        <div class="ig-item"><div class="ig-label">Referência</div><div class="ig-value"><?php echo htmlspecialchars($pedido['instrumento_referencia']); ?></div></div>
                        <?php endif; ?>
                        <?php if (!empty($pedido['instrumento_serie'])): ?>
                        <div class="ig-item"><div class="ig-label">Nº de Série</div><div class="ig-value"><?php echo htmlspecialchars($pedido['instrumento_serie']); ?></div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SERVIÇOS -->
                <div class="sect">
                    <div class="sect-title">Serviços solicitados</div>
                    <?php if (empty($servicos)): ?>
                    <div style="padding:20px;text-align:center;color:var(--g-text-3);font-size:13px">Nenhum serviço selecionado</div>
                    <?php else: ?>
                    <div style="overflow-x:auto">
                        <table class="srv-table">
                            <thead><tr><th>Serviço</th><th>Valor base</th><th>Prazo</th></tr></thead>
                            <tbody>
                                <?php foreach ($servicos as $s): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($s['nome']); ?></strong><?php if (!empty($s['descricao'])): ?><div class="srv-nome-desc"><?php echo htmlspecialchars($s['descricao']); ?></div><?php endif; ?></td>
                                    <td>R$ <?php echo number_format($s['valor_base'],2,',','.'); ?></td>
                                    <td><?php echo (int)$s['prazo_base']; ?>d</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Estimativa base</td>
                                    <td style="color:var(--g-green)">R$ <?php echo number_format($total_valor,2,',','.'); ?></td>
                                    <td style="color:var(--g-blue)"><?php echo $total_prazo; ?>d</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- OBSERVAÇÕES -->
                <?php if (!empty($pedido['observacoes'])): ?>
                <div class="sect">
                    <div class="sect-title">Observações</div>
                    <div style="padding:14px 20px;font-size:14px;color:var(--g-text);line-height:1.6;white-space:pre-wrap"><?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></div>
                </div>
                <?php endif; ?>

                <!-- FOTOS -->
                <?php if (!empty($fotos)): ?>
                <div class="sect">
                    <div class="sect-title">Fotos</div>
                    <div class="fotos-grid">
                        <?php foreach ($fotos as $foto): ?>
                        <img src="<?php echo htmlspecialchars($foto['caminho']); ?>" alt="Foto" onclick="window.open(this.src,'_blank')">
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <!-- /COLUNA ESQUERDA -->

            <!-- COLUNA DIREITA -->
            <div class="side-col">

                <!-- AÇÕES -->
                <div class="acoes-sect">
                    <div class="acoes-title">Ações disponíveis</div>
                    <?php if (empty($acoes_atuais)): ?>
                    <div class="acoes-vazio">Nenhuma ação disponível para este status.</div>
                    <?php else: ?>
                    <div class="acoes-list">
                        <?php foreach ($acoes_atuais as $a):
                            [$s_dest, $label_acao, $cls_btn] = $a;
                            $modal_acao = $a[3] ?? null;
                        ?>
                        <?php if ($modal_acao === 'modal-orc'): ?>
                        <button class="btn <?php echo $cls_btn; ?>" onclick="abrirModalOrcamento()"><?php echo htmlspecialchars($label_acao); ?></button>
                        <?php elseif ($modal_acao === 'modal-rep'): ?>
                        <button class="btn <?php echo $cls_btn; ?>" onclick="abrirModalReprovacao()"><?php echo htmlspecialchars($label_acao); ?></button>
                        <?php else: ?>
                        <button class="btn <?php echo $cls_btn; ?>" onclick="atualizarStatus('<?php echo addslashes($s_dest); ?>')"><?php echo htmlspecialchars($label_acao); ?></button>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- LINK PÚBLICO -->
                <div class="sect">
                    <div class="sect-title">Acompanhamento público</div>
                    <div class="token-row">
                        <span class="token-val"><?php echo htmlspecialchars($pedido['public_token']); ?></span>
                        <a href="../../frontend/public/acompanhar.php?token=<?php echo urlencode($pedido['public_token']); ?>" target="_blank" class="btn btn-primary" style="padding:8px 14px;font-size:12px;border-radius:8px">Abrir</a>
                    </div>
                </div>

                <!-- HISTÓRICO -->
                <div class="sect">
                    <div class="sect-title">Histórico</div>
                    <?php if (empty($historico)): ?>
                    <div style="padding:20px;text-align:center;color:var(--g-text-3);font-size:13px">Sem registros ainda</div>
                    <?php else: ?>
                    <ul class="tl">
                        <?php foreach (array_reverse($historico) as $h):
                            $hi = $status_map[$h['status']] ?? ['icone'=>'•','label'=>$h['status']];
                        ?>
                        <li class="tl-item">
                            <div class="tl-dot"><?php echo $hi['icone']; ?></div>
                            <div class="tl-body">
                                <div class="tl-status"><?php echo htmlspecialchars($hi['label']); ?></div>
                                <div class="tl-meta"><?php echo date('d/m/Y H:i',strtotime($h['criado_em'])); ?><?php if (!empty($h['admin_nome'])): ?> &mdash; <?php echo htmlspecialchars($h['admin_nome']); ?><?php endif; ?></div>
                                <?php if (!empty($h['valor_orcamento'])): ?>
                                <div class="tl-detalhe valor">R$ <?php echo number_format($h['valor_orcamento'],2,',','.'); ?><?php if (!empty($h['prazo_orcamento'])): ?> &middot; <?php echo (int)$h['prazo_orcamento']; ?> dias úteis<?php endif; ?></div>
                                <?php endif; ?>
                                <?php if (!empty($h['motivo'])): ?>
                                <div class="tl-detalhe motivo"><?php echo htmlspecialchars($h['motivo']); ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

            </div>
            <!-- /COLUNA DIREITA -->

        </div>
        <!-- /GRID -->

        <div style="height:24px"></div>
    </div>
</main>
</div>

<!-- BOTTOM NAV mobile -->
<nav class="bottom-nav">
    <a href="dashboard.php"><span class="nav-icon">📋</span>Pedidos</a>
    <a href="#" class="active"><span class="nav-icon">📌</span>Este pedido</a>
    <a href="dashboard.php?status=Pre-OS"><span class="nav-icon">⏳</span>Pendentes</a>
    <a href="logout.php"><span class="nav-icon">🚪</span>Sair</a>
</nav>

<!-- MODAL ORÇAMENTO -->
<div class="modal-overlay" id="modal-orcamento">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title">Definir Orçamento</div>
        <label>Valor total dos serviços (R$)</label>
        <input type="number" id="input-valor" min="0" step="0.01" placeholder="Ex: 350.00" oninput="simularValores()">
        <label>Prazo de entrega (dias úteis)</label>
        <input type="number" id="input-prazo" min="1" step="1" placeholder="Ex: 7">
        <div class="modal-hint">Sem sábados, domingos e feriados</div>
        <hr class="sim-sep">
        <div class="sim-titulo">Simulação — escolha o valor</div>
        <div class="sim-cards">
            <div class="sim-card" id="card-base" onclick="escolherValor('base')">
                <div class="sim-card-label">Valor Base</div>
                <div class="sim-card-valor" id="sim-base-valor">&mdash;</div>
                <div class="sim-card-sub">Sem taxa de máquina</div>
            </div>
            <div class="sim-card maquina" id="card-maquina" onclick="escolherValor('maquina')">
                <div class="sim-card-label">Valor Máquina (10x)</div>
                <div class="sim-card-valor" id="sim-maquina-valor">&mdash;</div>
                <div class="sim-card-sub" id="sim-maquina-sub">Pior caso: Elo/Amex 10x</div>
            </div>
        </div>
        <div class="sim-aviso" id="sim-aviso" style="display:none"></div>
        <input type="hidden" id="input-valor-final">
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="fecharModal('modal-orcamento')">Cancelar</button>
            <button class="btn btn-warning" id="btn-confirmar-orc" onclick="confirmarOrcamento()" disabled>Enviar Orçamento</button>
        </div>
    </div>
</div>

<!-- MODAL REPROVAÇÃO -->
<div class="modal-overlay" id="modal-reprovacao">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title">Motivo da Reprovação</div>
        <label for="input-motivo">Descreva o motivo</label>
        <textarea id="input-motivo" placeholder="Ex: Peça indisponível..."></textarea>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="fecharModal('modal-reprovacao')">Cancelar</button>
            <button class="btn btn-danger" onclick="confirmarReprovacao()">Confirmar</button>
        </div>
    </div>
</div>

<!-- MODAL WHATSAPP -->
<div class="modal-overlay" id="modal-wa">
    <div class="modal-box" style="max-width:420px;text-align:center">
        <div class="modal-drag"></div>
        <div class="modal-title">Avisar o cliente?</div>
        <div style="font-size:14px;color:var(--g-text-2);margin-bottom:20px" id="wa-status-texto">Status atualizado!</div>
        <a id="btn-wa-enviar" href="#" target="_blank" class="btn-wa" onclick="_fecharWaEReload()">Enviar no WhatsApp</a>
        <button class="btn-wa-skip" onclick="_fecharWaEReload()">Pular — recarregar</button>
    </div>
</div>

<script>
const _pedidoId  = <?php echo $preos_id; ?>;
const _totalBase = <?php echo (float)$total_valor; ?>;
const _statusMap = <?php echo json_encode(array_map(fn($v)=>$v['icone'].' '.$v['label'], $status_map)); ?>;

// Sidebar
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}

// Orçamento
function taxaMaquina(v) { return v > 2000 ? 15.38 : 21.58; }
function fmt(v) { return 'R$ '+v.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
let valorEscolhido = null;
function simularValores() {
    const v = parseFloat(document.getElementById('input-valor').value);
    if (isNaN(v)||v<=0) { document.getElementById('sim-base-valor').textContent='\u2014'; document.getElementById('sim-maquina-valor').textContent='\u2014'; return; }
    const taxa=taxaMaquina(v), inteiro=Math.ceil(v*(1+taxa/100)), real=inteiro/(1+taxa/100);
    document.getElementById('sim-base-valor').textContent=fmt(v);
    document.getElementById('sim-maquina-valor').textContent=fmt(inteiro);
    document.getElementById('sim-maquina-sub').innerHTML='Elo/Amex 10x ('+taxa.toFixed(2)+'%)<br>Digitar '+fmt(real)+' na máquina';
    if (valorEscolhido==='base') document.getElementById('input-valor-final').value=v.toFixed(2);
    if (valorEscolhido==='maquina') document.getElementById('input-valor-final').value=inteiro.toFixed(2);
    if (valorEscolhido) _atualizarAviso(v);
}
function escolherValor(tipo) {
    const v=parseFloat(document.getElementById('input-valor').value);
    if (isNaN(v)||v<=0) { _toast('Informe o valor primeiro'); return; }
    valorEscolhido=tipo;
    document.getElementById('card-base').classList.toggle('ativo',tipo==='base');
    document.getElementById('card-maquina').classList.toggle('ativo',tipo==='maquina');
    const taxa=taxaMaquina(v), inteiro=Math.ceil(v*(1+taxa/100));
    document.getElementById('input-valor-final').value=(tipo==='base'?v:inteiro).toFixed(2);
    _atualizarAviso(v);
    document.getElementById('btn-confirmar-orc').disabled=false;
}
function _atualizarAviso(v) {
    const taxa=taxaMaquina(v), inteiro=Math.ceil(v*(1+taxa/100)), real=inteiro/(1+taxa/100);
    const el=document.getElementById('sim-aviso'); el.style.display='block';
    el.innerHTML=valorEscolhido==='base'
        ?'<strong>Enviando ao cliente: '+fmt(v)+'</strong>'
        :'<strong>Enviando ao cliente: '+fmt(inteiro)+'</strong><br>Digitar na máquina: <strong>'+fmt(real)+'</strong> em <strong>10x</strong>.';
}
function confirmarOrcamento() {
    const vf=parseFloat(document.getElementById('input-valor-final').value);
    const pr=parseInt(document.getElementById('input-prazo').value);
    if (isNaN(vf)||vf<=0) { _toast('Escolha o valor a enviar'); return; }
    if (isNaN(pr)||pr<=0) { _toast('Informe o prazo'); return; }
    fecharModal('modal-orcamento');
    _enviar('Orcada',{valor_orcamento:vf,prazo_orcamento:pr});
}
function confirmarReprovacao() {
    const m=document.getElementById('input-motivo').value.trim();
    if (!m) { _toast('Informe o motivo'); return; }
    fecharModal('modal-reprovacao'); _enviar('Reprovada',{motivo:m});
}
function atualizarStatus(s) {
    const label=_statusMap[s]||s;
    if (!confirm('Alterar status para "'+label.replace(/^\S+\s/,'')+'"?')) return;
    _enviar(s,{});
}

// Modais
function abrirModal(id) { document.getElementById(id).classList.add('aberto'); }
function fecharModal(id) { document.getElementById(id).classList.remove('aberto'); }
function abrirModalOrcamento() {
    valorEscolhido=null;
    document.getElementById('input-valor').value=_totalBase>0?_totalBase.toFixed(2):'';
    document.getElementById('input-prazo').value='';
    document.getElementById('input-valor-final').value='';
    document.getElementById('btn-confirmar-orc').disabled=true;
    document.getElementById('sim-aviso').style.display='none';
    ['card-base','card-maquina'].forEach(id=>document.getElementById(id).classList.remove('ativo'));
    simularValores(); abrirModal('modal-orcamento');
    setTimeout(()=>document.getElementById('input-valor').focus(),150);
}
function abrirModalReprovacao() { abrirModal('modal-reprovacao'); setTimeout(()=>document.getElementById('input-motivo').focus(),150); }
document.querySelectorAll('.modal-overlay').forEach(o=>{
    o.addEventListener('click',e=>{if(e.target===o) o.classList.remove('aberto');});
});

// WhatsApp
function _fecharWaEReload() { fecharModal('modal-wa'); setTimeout(()=>location.reload(),300); }
function _abrirModalWa(waLink,statusLabel) {
    document.getElementById('wa-status-texto').innerHTML='Status atualizado para <strong>'+statusLabel+'</strong>. Deseja avisar o cliente?';
    document.getElementById('btn-wa-enviar').href=waLink;
    abrirModal('modal-wa');
}

// Fetch
function _toast(msg) {
    const el=document.createElement('div'); el.className='g-toast'; el.textContent=msg;
    document.body.appendChild(el); setTimeout(()=>el.remove(),3000);
}
function _enviar(status,extras) {
    fetch('atualizar_status.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:_pedidoId,status,...extras})})
    .then(r=>r.json())
    .then(data=>{
        if (data.sucesso) {
            const label=(_statusMap[status]||status).replace(/^\S+\s/,'');
            document.getElementById('status-label').textContent=label;
            if (data.atualizado_em) document.getElementById('atualizado-em').textContent='Atualizado '+data.atualizado_em;
            if (data.wa_link) _abrirModalWa(data.wa_link,label);
            else { _toast('Status atualizado!'); setTimeout(()=>location.reload(),1500); }
        } else _toast(data.erro||'Erro desconhecido');
    })
    .catch(()=>_toast('Erro de conexão'));
}
</script>
<script src="assets/js/admin.js?v=<?php echo $v; ?>"></script>
</body>
</html>
