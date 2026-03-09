<?php
/**
 * DETALHES DO PEDIDO — SISTEMA ADONIS
 * Versão 9.0 — colunas BD corrigidas, tipo_vinculo na tabela de insumos
 */
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header('Location: dashboard.php'); exit; }
$preos_id = (int)$_GET['id'];

try {
    // Pedido + cliente + instrumento
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

    // Serviços do pedido
    $stmt_s = $conn->prepare("
        SELECT s.id, s.nome, s.descricao, s.valor_base, s.prazo_padrao_dias
        FROM pre_os_servicos ps
        JOIN servicos s ON ps.servico_id = s.id
        WHERE ps.pre_os_id = :id
    ");
    $stmt_s->execute([':id' => $preos_id]);
    $servicos = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
    $total_valor = 0.0; $total_prazo = 0;
    foreach ($servicos as $s) {
        $total_valor += (float)$s['valor_base'];
        $total_prazo += (int)($s['prazo_padrao_dias'] ?? 0);
    }

    // Insumos vinculados aos serviços deste pedido (c/ tipo_vinculo)
    $insumos_pedido = [];
    try {
        if (!empty($servicos)) {
            $servico_ids  = array_column($servicos, 'id');
            $placeholders = implode(',', array_fill(0, count($servico_ids), '?'));
            $stmt_ins = $conn->prepare("
                SELECT DISTINCT
                    i.id, i.nome, i.unidade, i.valorunitario, i.estoque,
                    i.tipo_insumo, i.categoria,
                    si.tipo_vinculo,
                    GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS servicos_origem
                FROM servicos_insumos si
                JOIN insumos i  ON si.insumo_id  = i.id
                JOIN servicos s ON si.servico_id = s.id
                WHERE si.servico_id IN ($placeholders)
                GROUP BY i.id, si.tipo_vinculo
                ORDER BY si.tipo_vinculo DESC, i.nome
            ");
            $stmt_ins->execute($servico_ids);
            $insumos_pedido = $stmt_ins->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) { $insumos_pedido = []; }

    // Fotos
    $fotos = [];
    if (!empty($pedido['instrumento_id'])) {
        $stmt_f = $conn->prepare("SELECT caminho, ordem FROM instrumento_fotos WHERE instrumento_id = :id ORDER BY ordem ASC");
        $stmt_f->execute([':id' => $pedido['instrumento_id']]);
        $fotos = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
    }

    // Histórico
    $historico = [];
    try {
        $stmt_h = $conn->prepare("SELECT h.status, h.valor_orcamento, h.prazo_orcamento, h.motivo, h.criado_em, a.nome as admin_nome FROM status_historico h LEFT JOIN admins a ON h.admin_id = a.id WHERE h.pre_os_id = :id ORDER BY h.criado_em ASC");
        $stmt_h->execute([':id' => $preos_id]);
        $historico = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // Pagamento
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
    'Pre-OS'                        => ['label'=>'Pré-OS',                  'badge'=>'badge-new',     'icone'=>'note_add'],
    'Em analise'                    => ['label'=>'Em Análise',             'badge'=>'badge-info',    'icone'=>'search'],
    'Orcada'                        => ['label'=>'Orçada',                 'badge'=>'badge-warning', 'icone'=>'request_quote'],
    'Aguardando aprovacao'          => ['label'=>'Aguard. Aprovação',    'badge'=>'badge-warning', 'icone'=>'hourglass_empty'],
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
    'Pre-OS'               => [['Em analise','Iniciar Análise','btn-info','modal-analise'],['Orcada','Orçar','btn-warning','modal-orc'],['Reprovada','Reprovar','btn-danger','modal-rep'],['Cancelada','Cancelar','btn-dark']],
    'Em analise'           => [['Em analise','Rever Insumos','btn-info','modal-analise'],['Orcada','Orçar','btn-warning','modal-orc'],['Reprovada','Reprovar','btn-danger','modal-rep'],['Cancelada','Cancelar','btn-dark']],
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

$status_info  = $status_map[$pedido['status']] ?? ['label'=>$pedido['status'],'badge'=>'badge-secondary','icone'=>'circle'];
$acoes_atuais = $acoes_por_status[$pedido['status']] ?? [];

$current_page = 'detalhes.php';
require_once '_sidebar_data.php';
$v = time();

function iconHtml($name,$size=16){return '<span class="material-symbols-outlined" style="font-size:'.$size.'px;vertical-align:middle">'.$name.'</span>';}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?php echo $pedido['id']; ?> — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo $v; ?>">
    <style>
    .page-content{flex:1;padding:20px}
    .page-header{display:flex;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--g-border)}
    .page-header-back{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;color:var(--g-text-2);text-decoration:none;font-size:18px;transition:background .15s;flex-shrink:0}
    .page-header-back:hover{background:var(--g-hover)}
    .page-header-info{flex:1;min-width:0}
    .page-header-title{font-family:'Google Sans',sans-serif;font-size:18px;font-weight:500;color:var(--g-text)}
    .page-header-sub{font-size:13px;color:var(--g-text-2);margin-top:2px}
    .detalhes-grid{display:grid;grid-template-columns:1fr;gap:16px;align-items:start}
    @media(min-width:900px){.detalhes-grid{grid-template-columns:1fr 320px}}
    .sect{background:var(--g-surface);border:1px solid var(--g-border);border-radius:var(--g-radius-lg);margin-bottom:12px;overflow:hidden}
    .sect-title{font-family:'Google Sans',sans-serif;font-size:11px;font-weight:600;color:var(--g-text-2);padding:14px 20px 10px;border-bottom:1px solid var(--g-border);text-transform:uppercase;letter-spacing:.4px}
    .status-block{padding:16px 20px;display:flex;align-items:center;gap:14px}
    .status-block-label{font-family:'Google Sans',sans-serif;font-size:17px;font-weight:500;color:var(--g-text)}
    .status-block-meta{font-size:12px;color:var(--g-text-3);margin-top:3px}
    .orc-banner{display:flex;gap:16px;flex-wrap:wrap;padding:10px 20px;background:#e6f4ea;border-top:1px solid #c8e6c9;font-size:13px;font-weight:500;color:var(--g-green)}
    .rep-banner{padding:10px 20px;background:#fce8e6;border-top:1px solid #f5c6c3;font-size:13px;color:var(--g-red)}
    .acoes-sect{background:var(--g-surface);border:1px solid var(--g-border);border-radius:var(--g-radius-lg);overflow:hidden;margin-bottom:12px}
    .acoes-title{font-size:11px;font-weight:600;color:var(--g-text-3);text-transform:uppercase;letter-spacing:.5px;padding:14px 20px 8px}
    .acoes-list{padding:0 16px 16px;display:flex;flex-direction:column;gap:6px}
    .acoes-list .btn{justify-content:flex-start;border-radius:10px;font-size:13px}
    .acoes-vazio{padding:12px 20px 16px;font-size:13px;color:var(--g-text-3)}
    .ig{display:grid;grid-template-columns:1fr 1fr;gap:0}
    .ig-item{padding:12px 20px;border-bottom:1px solid var(--g-border)}
    .ig-item.full{grid-column:1/-1}
    .ig-label{font-size:11px;font-weight:600;color:var(--g-text-3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
    .ig-value{font-size:14px;color:var(--g-text);word-break:break-word}
    @media(max-width:480px){.ig{grid-template-columns:1fr}}
    .pgto-block{background:#e6f4ea;overflow:hidden}
    .pgto-titulo{padding:14px 20px;font-size:13px;font-weight:600;color:#1e8e3e;border-bottom:1px solid #c8e6c9}
    .pgto-row{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;border-bottom:1px solid #c8e6c9;font-size:13px}
    .pgto-row:last-child{border-bottom:none}
    .pgto-lbl{color:#2e7d32}.pgto-val{font-weight:600;color:#1b5e20}.pgto-val.big{font-size:17px}
    .maq-block{background:#fff8e1;overflow:hidden}
    .maq-titulo{padding:14px 20px;font-size:13px;font-weight:600;color:#e65100;border-bottom:1px solid #ffe082}
    .maq-row{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;border-bottom:1px solid #ffe082;font-size:13px}
    .maq-row:last-child{border-bottom:none}
    .maq-lbl{color:#795548}.maq-val{font-weight:600;color:#e65100}.maq-val.big{font-size:17px;color:#bf360c}
    .maq-obs{padding:10px 20px 14px;font-size:12px;color:#795548}
    .srv-table{width:100%;border-collapse:collapse}
    .srv-table thead th{text-align:left;padding:10px 20px;font-size:11px;font-weight:600;color:var(--g-text-2);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--g-border);background:var(--g-bg)}
    .srv-table tbody td{padding:12px 20px;font-size:13px;border-bottom:1px solid var(--g-border);color:var(--g-text);vertical-align:top}
    .srv-table tbody tr:last-child td{border-bottom:none}
    .srv-table tfoot td{padding:12px 20px;font-weight:600;font-size:13px;border-top:2px solid var(--g-border);background:var(--g-bg)}
    .srv-nome-desc{font-size:11px;color:var(--g-text-2);margin-top:2px}
    /* tabela insumos */
    .ins-table{width:100%;border-collapse:collapse}
    .ins-table thead th{text-align:left;padding:8px 16px;font-size:10px;font-weight:600;color:var(--g-text-3);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--g-border);background:var(--g-bg)}
    .ins-table tbody td{padding:10px 16px;font-size:13px;border-bottom:1px solid var(--g-border);color:var(--g-text);vertical-align:middle}
    .ins-table tbody tr:last-child td{border-bottom:none}
    .ins-origem{font-size:11px;color:var(--g-text-3);margin-top:2px}
    .estoque-baixo{color:#f59e0b;font-weight:600}
    .estoque-zero{color:#dc2626;font-weight:600}
    .tipo-fixo{background:#dcfce7;color:#166534;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;display:inline-block;white-space:nowrap}
    .tipo-variavel{background:#dbeafe;color:#1d4ed8;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;display:inline-block;white-space:nowrap}
    /* timeline */
    .fotos-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;padding:4px}
    .fotos-grid img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:6px;cursor:pointer;transition:opacity .15s}
    .fotos-grid img:hover{opacity:.85}
    .tl{list-style:none;padding:16px 20px;position:relative}
    .tl::before{content:'';position:absolute;left:38px;top:16px;bottom:16px;width:2px;background:var(--g-border)}
    .tl-item{display:flex;gap:14px;padding-bottom:20px}
    .tl-dot{width:38px;height:38px;border-radius:50%;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;z-index:1}
    .tl-body{flex:1;padding-top:6px}
    .tl-status{font-weight:500;font-size:13px;color:var(--g-text)}
    .tl-meta{font-size:12px;color:var(--g-text-3);margin-top:2px}
    .tl-detalhe{margin-top:5px;padding:5px 10px;border-radius:6px;font-size:12px;display:inline-block}
    .tl-detalhe.valor{background:#e6f4ea;color:#1e8e3e}
    .tl-detalhe.motivo{background:#fce8e6;color:#c5221f}
    .token-row{display:flex;align-items:center;gap:10px;padding:14px 20px}
    .token-val{font-family:'Roboto Mono',monospace;font-size:11px;color:var(--g-text-2);background:var(--g-bg);border:1px solid var(--g-border);border-radius:6px;padding:7px 10px;flex:1;word-break:break-all}
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="app-layout">
<?php require_once '_sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()"><span class="material-symbols-outlined">menu</span></button>
        <div style="display:flex;align-items:center;gap:8px">
            <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png" style="height:26px" alt="Adonis">
        </div>
        <span class="topbar-title">Pedido #<?php echo $pedido['id']; ?></span>
        <a href="logout.php" class="material-symbols-outlined sidebar-logout" title="Sair">logout</a>
    </div>

    <div class="page-content">

        <div class="page-header">
            <a href="dashboard.php" class="page-header-back"><span class="material-symbols-outlined">arrow_back</span></a>
            <div class="page-header-info">
                <div class="page-header-title"><?php echo htmlspecialchars($pedido['cliente_nome'] ?? 'Sem nome'); ?> <span style="font-weight:400;color:var(--g-text-2)">#<?php echo $pedido['id']; ?></span></div>
                <div class="page-header-sub"><?php echo htmlspecialchars(trim(($pedido['instrumento_tipo']??'').' '.($pedido['instrumento_marca']??'').' '.($pedido['instrumento_modelo']??'')) ?: 'Instrumento não informado'); ?></div>
            </div>
            <span class="badge <?php echo $status_info['badge']; ?>" id="status-badge"><?php echo iconHtml($status_info['icone'],11).' '.$status_info['label']; ?></span>
        </div>

        <div class="detalhes-grid">

        <!-- COLUNA ESQUERDA -->
        <div class="info-col">

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
                    <?php if (!empty($pedido['prazo_orcamento'])): ?><span><?php echo (int)$pedido['prazo_orcamento']; ?> dias úteis</span><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($pedido['motivo_reprovacao'])): ?>
                <div class="rep-banner"><strong>Motivo:</strong> <?php echo htmlspecialchars($pedido['motivo_reprovacao']); ?></div>
                <?php endif; ?>
            </div>

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

            <div class="sect">
                <div class="sect-title">Cliente</div>
                <div class="ig">
                    <div class="ig-item full"><div class="ig-label">Nome</div><div class="ig-value"><?php echo htmlspecialchars($pedido['cliente_nome']??''); ?></div></div>
                    <div class="ig-item"><div class="ig-label">WhatsApp</div><div class="ig-value"><a href="https://wa.me/55<?php echo preg_replace('/\D/','',$pedido['cliente_telefone']??''); ?>" target="_blank"><?php echo htmlspecialchars($pedido['cliente_telefone']??''); ?></a></div></div>
                    <?php if (!empty($pedido['cliente_email'])): ?>
                    <div class="ig-item"><div class="ig-label">E-mail</div><div class="ig-value"><a href="mailto:<?php echo htmlspecialchars($pedido['cliente_email']); ?>"><?php echo htmlspecialchars($pedido['cliente_email']); ?></a></div></div>
                    <?php endif; ?>
                    <?php if (!empty($pedido['cliente_endereco'])): ?>
                    <div class="ig-item full"><div class="ig-label">Endereço</div><div class="ig-value"><?php echo nl2br(htmlspecialchars($pedido['cliente_endereco'])); ?></div></div>
                    <?php endif; ?>
                </div>
            </div>

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
                                <td>R$ <?php echo number_format((float)$s['valor_base'],2,',','.'); ?></td>
                                <td><?php echo (int)($s['prazo_padrao_dias'] ?? 0); ?>d.u.</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Estimativa base</td>
                                <td style="color:var(--g-green)">R$ <?php echo number_format($total_valor,2,',','.'); ?></td>
                                <td style="color:var(--g-blue)"><?php echo $total_prazo; ?>d.u.</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- INSUMOS VINCULADOS AOS SERVIÇOS -->
            <?php if (!empty($insumos_pedido)): ?>
            <div class="sect">
                <div class="sect-title"><?php echo iconHtml('inventory_2',13); ?> Insumos vinculados aos serviços</div>
                <div style="overflow-x:auto">
                    <table class="ins-table">
                        <thead>
                            <tr>
                                <th>Insumo</th>
                                <th>Unid.</th>
                                <th>Tipo</th>
                                <th>Estoque</th>
                                <th class="text-right">Valor unit.</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($insumos_pedido as $ins):
                            $est    = (float)($ins['estoque'] ?? 0);
                            $estCls = $est <= 0 ? 'estoque-zero' : ($est < 5 ? 'estoque-baixo' : '');
                            $vinculo = $ins['tipo_vinculo'] ?? 'variavel';
                            $vincCls = $vinculo === 'fixo' ? 'tipo-fixo' : 'tipo-variavel';
                            $vincTxt = $vinculo === 'fixo' ? 'Fixo' : 'Variável';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($ins['nome']); ?></strong>
                                <?php if (!empty($ins['servicos_origem'])): ?>
                                <div class="ins-origem"><?php echo iconHtml('link',10); ?> <?php echo htmlspecialchars($ins['servicos_origem']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($ins['unidade']); ?></td>
                            <td><span class="<?php echo $vincCls; ?>"><?php echo $vincTxt; ?></span></td>
                            <td class="<?php echo $estCls; ?>">
                                <?php echo number_format($est, 3, ',', '.'); ?>
                                <?php if ($est <= 0) echo ' '.iconHtml('warning',14); ?>
                            </td>
                            <td class="text-right">R$ <?php echo number_format((float)$ins['valorunitario'],2,',','.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($pedido['observacoes'])): ?>
            <div class="sect">
                <div class="sect-title">Observações</div>
                <div style="padding:14px 20px;font-size:14px;color:var(--g-text);line-height:1.6;white-space:pre-wrap"><?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></div>
            </div>
            <?php endif; ?>

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
                    <?php if ($modal_acao === 'modal-analise'): ?>
                    <button class="btn <?php echo $cls_btn; ?>" onclick="abrirModalAnalise()"><?php echo htmlspecialchars($label_acao); ?></button>
                    <?php elseif ($modal_acao === 'modal-orc'): ?>
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

            <div class="sect">
                <div class="sect-title">Acompanhamento público</div>
                <div class="token-row">
                    <span class="token-val"><?php echo htmlspecialchars($pedido['public_token']); ?></span>
                    <a href="../../frontend/public/acompanhar.php?token=<?php echo urlencode($pedido['public_token']); ?>" target="_blank" class="btn btn-primary" style="padding:8px 14px;font-size:12px;border-radius:8px">Abrir</a>
                </div>
            </div>

            <div class="sect">
                <div class="sect-title">Histórico</div>
                <?php if (empty($historico)): ?>
                <div style="padding:20px;text-align:center;color:var(--g-text-3);font-size:13px">Sem registros ainda</div>
                <?php else: ?>
                <ul class="tl">
                    <?php foreach (array_reverse($historico) as $h):
                        $hi = $status_map[$h['status']] ?? ['icone'=>'circle','label'=>$h['status']];
                    ?>
                    <li class="tl-item">
                        <div class="tl-dot"><?php echo iconHtml($hi['icone'],16); ?></div>
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

        </div><!-- /detalhes-grid -->
        <div style="height:24px"></div>
    </div>
</main>
</div>

<nav class="bottom-nav">
    <a href="dashboard.php"><span class="material-symbols-outlined nav-icon">dashboard</span>Painel</a>
    <a href="#" class="active"><span class="material-symbols-outlined nav-icon">description</span>Pedido</a>
    <a href="dashboard.php?status=Pre-OS"><span class="material-symbols-outlined nav-icon">note_add</span>Pré-OS</a>
    <a href="logout.php"><span class="material-symbols-outlined nav-icon">logout</span>Sair</a>
</nav>

<!-- MODAL ANÁLISE DE INSUMOS -->
<div class="modal-overlay" id="modal-analise">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-drag"></div>
        <div class="modal-title">Análise de Insumos</div>
        <div id="analise-corpo"><div class="analise-loading">Carregando insumos...</div></div>
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
        <input type="number" id="input-valor" min="0" step="0.01" placeholder="Ex: 350.00" oninput="simularValores()">
        <div id="orc-breakdown" style="font-size:12px;color:var(--g-text-3);margin:-8px 0 8px;padding:0 2px"></div>
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
        <a id="btn-wa-enviar" href="#" target="_blank" class="btn-wa" onclick="_fecharWaEReload()">
            <span class="material-symbols-outlined">chat</span> WhatsApp
        </a>
        <button class="btn-wa-skip" onclick="_fecharWaEReload()">Pular — recarregar</button>
    </div>
</div>

<script>
const _pedidoId  = <?php echo $preos_id; ?>;
const _totalBase = <?php echo (float)$total_valor; ?>;
const _statusMap = <?php echo json_encode(array_map(fn($v)=>iconHtml($v['icone'],11).' '.$v['label'], $status_map), JSON_UNESCAPED_UNICODE); ?>;

function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar(){
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}

function toggleGroup(id){
    const toggle=document.getElementById('toggle-'+id);
    const sub=document.getElementById('sub-'+id);
    toggle.classList.toggle('open'); sub.classList.toggle('open');
    const e=JSON.parse(localStorage.getItem('nav_grupos')||'{}');
    e[id]=toggle.classList.contains('open');
    localStorage.setItem('nav_grupos',JSON.stringify(e));
}
document.addEventListener('DOMContentLoaded',()=>{
    const e=JSON.parse(localStorage.getItem('nav_grupos')||'{}');
    for(const[id,aberto] of Object.entries(e)){
        const t=document.getElementById('toggle-'+id);
        const s=document.getElementById('sub-'+id);
        if(!t||!s)continue;
        if(aberto&&!t.classList.contains('open')){t.classList.add('open');s.classList.add('open');}
    }
});

function fmt(v){return 'R\u00a0'+v.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
function taxaMaquina(v){return v>2000?15.38:21.58;}
function _toast(msg){
    const el=document.createElement('div');
    el.className='g-toast'; el.textContent=msg;
    document.body.appendChild(el); setTimeout(()=>el.remove(),3000);
}

function abrirModal(id){document.getElementById(id).classList.add('aberto');}
function fecharModal(id){document.getElementById(id).classList.remove('aberto');}
document.querySelectorAll('.modal-overlay').forEach(o=>{
    o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('aberto');});
});

let valorEscolhido=null;

function _abrirOrcamentoComValor(totalOrc,totalSrv,totalIns){
    valorEscolhido=null;
    document.getElementById('input-valor').value=totalOrc>0?totalOrc.toFixed(2):'';
    document.getElementById('input-prazo').value='';
    document.getElementById('input-valor-final').value='';
    document.getElementById('btn-confirmar-orc').disabled=true;
    document.getElementById('sim-aviso').style.display='none';
    ['card-base','card-maquina'].forEach(id=>document.getElementById(id).classList.remove('ativo'));
    let bd=document.getElementById('orc-breakdown');
    if(totalSrv>=0&&totalIns>=0){bd.innerHTML='Serviços: '+fmt(totalSrv)+' + Insumos: '+fmt(totalIns);}
    else{bd.textContent='';}
    simularValores();
    abrirModal('modal-orcamento');
    setTimeout(()=>document.getElementById('input-valor').focus(),150);
}

function abrirModalOrcamento(){_abrirOrcamentoComValor(_totalBase,_totalBase,0);}

function simularValores(){
    const v=parseFloat(document.getElementById('input-valor').value);
    if(isNaN(v)||v<=0){document.getElementById('sim-base-valor').textContent='\u2014';document.getElementById('sim-maquina-valor').textContent='\u2014';return;}
    const taxa=taxaMaquina(v),inteiro=Math.ceil(v*(1+taxa/100)),real=inteiro/(1+taxa/100);
    document.getElementById('sim-base-valor').textContent=fmt(v);
    document.getElementById('sim-maquina-valor').textContent=fmt(inteiro);
    document.getElementById('sim-maquina-sub').innerHTML='Elo/Amex 10x ('+taxa.toFixed(2)+'%)<br>Digitar '+fmt(real)+' na máquina';
    if(valorEscolhido==='base')document.getElementById('input-valor-final').value=v.toFixed(2);
    if(valorEscolhido==='maquina')document.getElementById('input-valor-final').value=inteiro.toFixed(2);
    if(valorEscolhido)_atualizarAviso(v);
}

function escolherValor(tipo){
    const v=parseFloat(document.getElementById('input-valor').value);
    if(isNaN(v)||v<=0){_toast('Informe o valor primeiro');return;}
    valorEscolhido=tipo;
    document.getElementById('card-base').classList.toggle('ativo',tipo==='base');
    document.getElementById('card-maquina').classList.toggle('ativo',tipo==='maquina');
    const taxa=taxaMaquina(v),inteiro=Math.ceil(v*(1+taxa/100));
    document.getElementById('input-valor-final').value=(tipo==='base'?v:inteiro).toFixed(2);
    _atualizarAviso(v);
    document.getElementById('btn-confirmar-orc').disabled=false;
}

function _atualizarAviso(v){
    const taxa=taxaMaquina(v),inteiro=Math.ceil(v*(1+taxa/100)),real=inteiro/(1+taxa/100);
    const el=document.getElementById('sim-aviso');el.style.display='block';
    el.innerHTML=valorEscolhido==='base'
        ?'<strong>Enviando ao cliente: '+fmt(v)+'</strong>'
        :'<strong>Enviando ao cliente: '+fmt(inteiro)+'</strong><br>Digitar na máquina: <strong>'+fmt(real)+'</strong> em <strong>10x</strong>.';
}

function confirmarOrcamento(){
    const vf=parseFloat(document.getElementById('input-valor-final').value);
    const pr=parseInt(document.getElementById('input-prazo').value);
    if(isNaN(vf)||vf<=0){_toast('Escolha o valor a enviar');return;}
    if(isNaN(pr)||pr<=0){_toast('Informe o prazo');return;}
    fecharModal('modal-orcamento');
    _enviar('Orcada',{valor_orcamento:vf,prazo_orcamento:pr});
}

function abrirModalReprovacao(){abrirModal('modal-reprovacao');setTimeout(()=>document.getElementById('input-motivo').focus(),150);}

function confirmarReprovacao(){
    const m=document.getElementById('input-motivo').value.trim();
    if(!m){_toast('Informe o motivo');return;}
    fecharModal('modal-reprovacao');_enviar('Reprovada',{motivo:m});
}

function atualizarStatus(s){
    const label=(_statusMap[s]||s).replace(/<[^>]+>/g,'');
    if(!confirm('Alterar status para "'+label+'"?'))return;
    _enviar(s,{});
}

function _fecharWaEReload(){fecharModal('modal-wa');setTimeout(()=>location.reload(),300);}
function _abrirModalWa(waLink,statusLabel){
    document.getElementById('wa-status-texto').innerHTML='Status atualizado para <strong>'+statusLabel+'</strong>. Deseja avisar o cliente?';
    document.getElementById('btn-wa-enviar').href=waLink;
    abrirModal('modal-wa');
}

function _enviar(status,extras){
    fetch('atualizar_status.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:_pedidoId,status,...extras})})
    .then(r=>r.json())
    .then(data=>{
        if(data.sucesso){
            const label=(_statusMap[status]||status).replace(/<[^>]+>/g,'');
            document.getElementById('status-label').textContent=label;
            if(data.atualizado_em)document.getElementById('atualizado-em').textContent='Atualizado '+data.atualizado_em;
            if(data.wa_link)_abrirModalWa(data.wa_link,label);
            else{_toast('Status atualizado!');setTimeout(()=>location.reload(),1500);}
        }else _toast(data.erro||'Erro desconhecido');
    })
    .catch(()=>_toast('Erro de conexão'));
}
</script>
<script src="assets/js/admin.js?v=<?php echo $v; ?>"></script>
<script src="assets/js/analise_insumos_v3.js?v=<?php echo $v; ?>"></script>
</body>
</html>
