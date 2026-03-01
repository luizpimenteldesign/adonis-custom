<?php
/**
 * DETALHES DO PEDIDO — SISTEMA ADONIS
 * Visual: Google / Material Design 3
 * Versão: 5.0
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
    error_log('Erro detalhes: '.$e->getMessage());
    header('Location: dashboard.php?erro=banco'); exit;
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

$status_info = $status_map[$pedido['status']] ?? ['label'=>$pedido['status'],'badge'=>'badge-secondary','icone'=>'•'];
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
        /* Extras específicos desta página */
        .pgto-aprovado-card { border-radius:16px; border:1px solid #a8d5b5; background:#e6f4ea; overflow:hidden; margin-bottom:12px; }
        .pgto-aprovado-titulo { padding:14px 20px; font-family:'Google Sans',sans-serif; font-size:14px; font-weight:500; color:#1e8e3e; border-bottom:1px solid #a8d5b5; display:flex; align-items:center; gap:8px; }
        .pgto-aprovado-linha { display:flex; justify-content:space-between; align-items:center; padding:10px 20px; border-bottom:1px solid #c8e6c9; font-size:13px; }
        .pgto-aprovado-linha:last-child { border-bottom:none; }
        .pgto-aprovado-lbl { color:#2e7d32; }
        .pgto-aprovado-val { font-weight:600; color:#1b5e20; }
        .pgto-aprovado-val.destaque { font-size:17px; }
        .maq-card-body { padding:0; }
        .maq-linha { display:flex; justify-content:space-between; align-items:center; padding:10px 20px; border-bottom:1px solid #ffe082; font-size:13px; }
        .maq-linha:last-child { border-bottom:none; }
        .maq-lbl { color:#795548; }
        .maq-val { font-weight:600; color:#e65100; }
        .maq-val.destaque { font-size:17px; color:#bf360c; }
        .maq-obs { padding:10px 20px 14px; font-size:12px; color:#795548; background:#fff3e0; }
    </style>
</head>
<body>

<!-- TOP BAR -->
<header class="header">
    <div class="header-left">
        <a href="dashboard.php" class="back-button">← Voltar</a>
        <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png" alt="Adonis" class="header-logo">
        <span class="header-title">Pedido #<?php echo $pedido['id']; ?></span>
    </div>
    <div class="header-right">
        <a href="logout.php" class="btn btn-logout">Sair</a>
    </div>
</header>

<!-- ABAS -->
<div class="tabs">
    <button class="tab-btn active" onclick="abrirTab('tab-status', this)">Status</button>
    <button class="tab-btn" onclick="abrirTab('tab-cliente', this)">Cliente</button>
    <button class="tab-btn" onclick="abrirTab('tab-servicos', this)">Serviços</button>
    <button class="tab-btn" onclick="abrirTab('tab-historico', this)">Histórico</button>
</div>

<div class="container">

    <!-- ======= TAB: STATUS ======= -->
    <div id="tab-status" class="tab-panel active">

        <!-- Card hero status -->
        <div class="card mb-0">
            <div class="status-hero">
                <div class="status-hero-icon" id="status-icone"><?php echo $status_info['icone']; ?></div>
                <div class="status-hero-info">
                    <div class="status-hero-label" id="status-label"><?php echo htmlspecialchars($status_info['label']); ?></div>
                    <div class="status-hero-meta" id="atualizado-em">Atualizado <?php echo date('d/m/Y H:i', strtotime($pedido['atualizado_em'])); ?></div>
                </div>
            </div>

            <?php if (!empty($pedido['valor_orcamento'])): ?>
            <div class="status-orcamento">
                <span>💰 R$ <?php echo number_format($pedido['valor_orcamento'],2,',','.'); ?></span>
                <?php if (!empty($pedido['prazo_orcamento'])): ?>
                <span>📅 <?php echo (int)$pedido['prazo_orcamento']; ?> dias úteis</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($pedido['motivo_reprovacao'])): ?>
            <div class="status-reprovacao">
                ❌ <strong>Motivo:</strong> <?php echo htmlspecialchars($pedido['motivo_reprovacao']); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Card pagamento aprovado -->
        <?php if (!empty($pagamento_info) && !empty($pagamento_info['forma_pagamento'])):
            $forma    = $pagamento_info['forma_pagamento'];
            $vf       = (float)($pagamento_info['valor_final'] ?? 0);
            $parcelas = (int)($pagamento_info['parcelas'] ?? 0);
            $porparc  = (float)($pagamento_info['por_parcela'] ?? 0);
            $descpag  = $pagamento_info['descricao_pagamento'] ?? $forma;
            $ico_pgto = (stripos($forma,'pix')!==false || stripos($forma,'dinheiro')!==false) ? '🟢' : ((stripos($forma,'entrada')!==false) ? '🔑' : '🖳️');
        ?>
        <div class="pgto-aprovado-card">
            <div class="pgto-aprovado-titulo"><?php echo $ico_pgto; ?> Pagamento escolhido pelo cliente</div>
            <div class="pgto-aprovado-linha"><span class="pgto-aprovado-lbl">Forma</span><span class="pgto-aprovado-val"><?php echo htmlspecialchars($descpag ?: $forma); ?></span></div>
            <div class="pgto-aprovado-linha"><span class="pgto-aprovado-lbl">Valor total</span><span class="pgto-aprovado-val destaque">R$ <?php echo number_format($vf,2,',','.'); ?></span></div>
            <?php if ($parcelas > 0 && stripos($forma,'cart')!==false): ?>
            <div class="pgto-aprovado-linha"><span class="pgto-aprovado-lbl">Parcelas</span><span class="pgto-aprovado-val"><?php echo $parcelas; ?>x de R$ <?php echo number_format($porparc,2,',','.'); ?></span></div>
            <?php elseif (stripos($forma,'entrada')!==false): ?>
            <div class="pgto-aprovado-linha"><span class="pgto-aprovado-lbl">Entrada</span><span class="pgto-aprovado-val">R$ <?php echo number_format($vf*0.5,2,',','.'); ?></span></div>
            <div class="pgto-aprovado-linha"><span class="pgto-aprovado-lbl">Retirada</span><span class="pgto-aprovado-val">R$ <?php echo number_format($vf*0.5,2,',','.'); ?></span></div>
            <?php endif; ?>
        </div>
        <?php if (stripos($forma,'cart')!==false && $parcelas > 0):
            $taxa_aprox = $vf > 2000 ? 15.38 : 21.58;
            $maq_real   = $vf / (1 + $taxa_aprox/100);
        ?>
        <div class="maq-card">
            <div class="maq-card-header">🖳️ Instrução para a maquininha</div>
            <div class="maq-card-body">
                <div class="maq-linha"><span class="maq-lbl">Digite na máquina</span><span class="maq-val destaque">R$ <?php echo number_format($maq_real,2,',','.'); ?></span></div>
                <div class="maq-linha"><span class="maq-lbl">Parcelas</span><span class="maq-val"><?php echo $parcelas; ?>x</span></div>
            </div>
            <div class="maq-obs">⚠️ Digite <strong>R$ <?php echo number_format($maq_real,2,',','.'); ?></strong> e selecione <strong><?php echo $parcelas; ?>x</strong>. Cliente paga exatamente <strong>R$ <?php echo number_format($vf,2,',','.'); ?></strong>.</div>
        </div>
        <?php endif; endif; ?>

        <!-- Card token -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">🔑 Acompanhamento</h2>
                <a href="../../frontend/public/acompanhar.php?token=<?php echo urlencode($pedido['public_token']); ?>" target="_blank" class="btn btn-primary btn-sm">Ver página</a>
            </div>
            <div class="token-box"><?php echo htmlspecialchars($pedido['public_token']); ?></div>
        </div>

    </div>

    <!-- ======= TAB: CLIENTE ======= -->
    <div id="tab-cliente" class="tab-panel">
        <div class="card">
            <div class="card-header"><h2 class="card-title">👤 Dados do Cliente</h2></div>
            <div class="info-grid">
                <div class="info-item full"><div class="info-label">Nome</div><div class="info-value"><?php echo htmlspecialchars($pedido['cliente_nome']); ?></div></div>
                <div class="info-item">
                    <div class="info-label">WhatsApp</div>
                    <div class="info-value">
                        <a href="https://wa.me/55<?php echo preg_replace('/\D/','',$pedido['cliente_telefone']); ?>" target="_blank">
                            📞 <?php echo htmlspecialchars($pedido['cliente_telefone']); ?>
                        </a>
                    </div>
                </div>
                <?php if (!empty($pedido['cliente_email'])): ?>
                <div class="info-item">
                    <div class="info-label">E-mail</div>
                    <div class="info-value"><a href="mailto:<?php echo htmlspecialchars($pedido['cliente_email']); ?>"><?php echo htmlspecialchars($pedido['cliente_email']); ?></a></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($pedido['cliente_endereco'])): ?>
                <div class="info-item full"><div class="info-label">Endereço</div><div class="info-value"><?php echo nl2br(htmlspecialchars($pedido['cliente_endereco'])); ?></div></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">🎸 Instrumento</h2></div>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Tipo</div><div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_tipo']); ?></div></div>
                <div class="info-item"><div class="info-label">Marca</div><div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_marca']); ?></div></div>
                <div class="info-item"><div class="info-label">Modelo</div><div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_modelo']); ?></div></div>
                <?php if (!empty($pedido['instrumento_cor'])): ?>
                <div class="info-item"><div class="info-label">Cor</div><div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_cor']); ?></div></div>
                <?php endif; ?>
                <?php if (!empty($pedido['instrumento_referencia'])): ?>
                <div class="info-item"><div class="info-label">Referência</div><div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_referencia']); ?></div></div>
                <?php endif; ?>
                <?php if (!empty($pedido['instrumento_serie'])): ?>
                <div class="info-item"><div class="info-label">Nº de Série</div><div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_serie']); ?></div></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($pedido['observacoes'])): ?>
        <div class="card">
            <div class="card-header"><h2 class="card-title">📝 Observações</h2></div>
            <div class="observacoes"><?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($fotos)): ?>
        <div class="card">
            <div class="card-header"><h2 class="card-title">📷 Fotos</h2></div>
            <div class="photos-grid">
                <?php foreach ($fotos as $foto): ?>
                <div class="photo-item"><img src="<?php echo htmlspecialchars($foto['caminho']); ?>" alt="Foto" onclick="window.open(this.src,'_blank')"></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ======= TAB: SERVIÇOS ======= -->
    <div id="tab-servicos" class="tab-panel">
        <div class="card">
            <div class="card-header"><h2 class="card-title">🔧 Serviços Solicitados</h2></div>
            <?php if (empty($servicos)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔧</div>
                <div class="empty-state-title">Nenhum serviço</div>
                <div class="empty-state-sub">Nenhum serviço foi selecionado</div>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Serviço</th><th>Valor base</th><th>Prazo</th></tr></thead>
                    <tbody>
                        <?php foreach ($servicos as $s): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($s['nome']); ?></strong>
                                <?php if (!empty($s['descricao'])): ?>
                                <div style="font-size:12px;color:var(--g-text-2);margin-top:2px"><?php echo htmlspecialchars($s['descricao']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?php echo number_format($s['valor_base'],2,',','.'); ?></td>
                            <td><?php echo (int)$s['prazo_base']; ?>d</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Estimativa base</td>
                            <td class="total-valor">R$ <?php echo number_format($total_valor,2,',','.'); ?><span class="total-obs">soma base</span></td>
                            <td class="total-prazo"><?php echo $total_prazo; ?>d<span class="total-obs">acumulado</span></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ======= TAB: HISTÓRICO ======= -->
    <div id="tab-historico" class="tab-panel">
        <div class="card">
            <div class="card-header"><h2 class="card-title">🕓 Histórico</h2></div>
            <?php if (empty($historico)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📜</div>
                <div class="empty-state-title">Sem registros</div>
                <div class="empty-state-sub">Nenhuma alteração registrada ainda</div>
            </div>
            <?php else: ?>
            <div class="p-20">
                <ul class="timeline">
                <?php foreach ($historico as $h):
                    $hi = $status_map[$h['status']] ?? ['icone'=>'•','label'=>$h['status']];
                ?>
                <li class="timeline-item">
                    <div class="timeline-dot"><?php echo $hi['icone']; ?></div>
                    <div class="timeline-content">
                        <div class="timeline-status"><?php echo htmlspecialchars($hi['label']); ?></div>
                        <div class="timeline-meta"><?php echo date('d/m/Y H:i',strtotime($h['criado_em'])); ?><?php if (!empty($h['admin_nome'])): ?> — <?php echo htmlspecialchars($h['admin_nome']); ?><?php endif; ?></div>
                        <?php if (!empty($h['valor_orcamento'])): ?>
                        <div class="timeline-detalhe valor">💰 R$ <?php echo number_format($h['valor_orcamento'],2,',','.'); ?><?php if (!empty($h['prazo_orcamento'])): ?> · <?php echo (int)$h['prazo_orcamento']; ?> dias úteis<?php endif; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($h['motivo'])): ?>
                        <div class="timeline-detalhe motivo">Motivo: <?php echo htmlspecialchars($h['motivo']); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="height:80px"></div>
</div>

<!-- FAB MENU DE AÇÕES -->
<div class="fab-menu" id="fab-menu">

    <div class="fab-menu-item">
        <span class="fab-menu-label">Cancelar</span>
        <button class="fab-mini btn-dark" onclick="toggleFab();atualizarStatus('Cancelada')">🚫</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Entregue</span>
        <button class="fab-mini btn-dark" onclick="toggleFab();atualizarStatus('Entregue')">🏁</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Pronto p/ Retirada</span>
        <button class="fab-mini btn-warning" onclick="toggleFab();atualizarStatus('Pronto para retirada')">🎉</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Serviço Finalizado</span>
        <button class="fab-mini btn-success" onclick="toggleFab();atualizarStatus('Servico finalizado')">🎸</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Em Desenvolvimento</span>
        <button class="fab-mini btn-purple" onclick="toggleFab();atualizarStatus('Em desenvolvimento')">⚙️</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Serviço Iniciado</span>
        <button class="fab-mini btn-purple" onclick="toggleFab();atualizarStatus('Servico iniciado')">🔧</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Instrumento Recebido</span>
        <button class="fab-mini btn-success" onclick="toggleFab();atualizarStatus('Instrumento recebido')">📦</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Pagamento Recebido</span>
        <button class="fab-mini btn-success" onclick="toggleFab();atualizarStatus('Pagamento recebido')">✅</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Orçar</span>
        <button class="fab-mini btn-warning" onclick="toggleFab();abrirModalOrcamento()">💰</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Reprovar</span>
        <button class="fab-mini btn-danger" onclick="toggleFab();abrirModalReprovacao()">❌</button>
    </div>
    <div class="fab-menu-item">
        <span class="fab-menu-label">Em Análise</span>
        <button class="fab-mini btn-info" onclick="toggleFab();atualizarStatus('Em analise')">🔍</button>
    </div>

</div>

<button class="fab" id="fab-btn" onclick="toggleFab()" title="Ações">✏️</button>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
    <a href="dashboard.php">
        <span class="nav-icon">📋</span>
        Pedidos
    </a>
    <a href="#" onclick="abrirTab('tab-status');return false;" class="active">
        <span class="nav-icon">📊</span>
        Status
    </a>
    <a href="#" onclick="abrirTab('tab-cliente');return false;">
        <span class="nav-icon">👤</span>
        Cliente
    </a>
    <a href="#" onclick="abrirTab('tab-historico');return false;">
        <span class="nav-icon">🕓</span>
        Histórico
    </a>
</nav>

<!-- MODAL ORÇAMENTO -->
<div class="modal-overlay" id="modal-orcamento">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title">💰 Definir Orçamento</div>
        <label>Valor total dos serviços (R$)</label>
        <input type="number" id="input-valor" min="0" step="0.01" placeholder="Ex: 350.00" oninput="simularValores()">
        <label>Prazo de entrega (dias úteis)</label>
        <input type="number" id="input-prazo" min="1" step="1" placeholder="Ex: 7">
        <div class="modal-hint">💡 Dias úteis — sem sábados, domingos e feriados</div>
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
        <div class="modal-title">❌ Motivo da Reprovação</div>
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
        <div class="modal-title">📲 Avisar o cliente?</div>
        <div style="font-size:14px;color:var(--g-text-2);margin-bottom:20px" id="wa-status-texto">Status atualizado!</div>
        <a id="btn-wa-enviar" href="#" target="_blank" class="btn-wa" onclick="_fecharWaEReload()">
            💬 Enviar mensagem no WhatsApp
        </a>
        <button class="btn-wa-skip" onclick="_fecharWaEReload()">Pular — recarregar página</button>
    </div>
</div>

<script>
const _pedidoId  = <?php echo $preos_id; ?>;
const _totalBase = <?php echo (float)$total_valor; ?>;
const _statusMap = <?php echo json_encode(array_map(fn($v)=>$v['icone'].' '.$v['label'], $status_map)); ?>;
const _statusBadge = <?php echo json_encode(array_map(fn($v)=>$v['badge'], $status_map)); ?>;

// ---- Tabs ----
function abrirTab(id, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    if (btn) btn.classList.add('active');
    else {
        const idx = ['tab-status','tab-cliente','tab-servicos','tab-historico'].indexOf(id);
        document.querySelectorAll('.tab-btn')[idx]?.classList.add('active');
    }
    window.scrollTo(0, 0);
}

// ---- FAB ----
let fabAberto = false;
function toggleFab() {
    fabAberto = !fabAberto;
    document.getElementById('fab-menu').classList.toggle('aberto', fabAberto);
    document.getElementById('fab-btn').textContent = fabAberto ? '✕' : '✏️';
}
document.addEventListener('click', e => {
    if (fabAberto && !e.target.closest('.fab-menu') && !e.target.closest('.fab')) {
        fabAberto = false;
        document.getElementById('fab-menu').classList.remove('aberto');
        document.getElementById('fab-btn').textContent = '✏️';
    }
});

// ---- Orçamento ----
function taxaMaquina(v) { return v > 2000 ? 15.38 : 21.58; }
function fmt(v) { return 'R$ '+v.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
function fmtInt(v) { return 'R$ '+v.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
let valorEscolhido = null;
function calcMaquina(v) {
    const taxa=taxaMaquina(v); const inteiro=Math.ceil(v*(1+taxa/100)); const real=inteiro/(1+taxa/100);
    return {taxa,inteiro,real};
}
function simularValores() {
    const v=parseFloat(document.getElementById('input-valor').value);
    if(isNaN(v)||v<=0){document.getElementById('sim-base-valor').textContent='—';document.getElementById('sim-maquina-valor').textContent='—';return;}
    const {taxa,inteiro,real}=calcMaquina(v);
    document.getElementById('sim-base-valor').textContent=fmt(v);
    document.getElementById('sim-maquina-valor').textContent=fmtInt(inteiro);
    document.getElementById('sim-maquina-sub').innerHTML='Elo/Amex 10x ('+taxa.toFixed(2)+'%)<br>Digitar '+fmt(real)+' na máquina';
    if(valorEscolhido==='base') document.getElementById('input-valor-final').value=v.toFixed(2);
    if(valorEscolhido==='maquina') document.getElementById('input-valor-final').value=inteiro.toFixed(2);
    if(valorEscolhido) atualizarAviso(v);
}
function escolherValor(tipo) {
    const v=parseFloat(document.getElementById('input-valor').value);
    if(isNaN(v)||v<=0){_toast('Informe o valor primeiro',false);return;}
    valorEscolhido=tipo;
    document.getElementById('card-base').classList.toggle('ativo',tipo==='base');
    document.getElementById('card-maquina').classList.toggle('ativo',tipo==='maquina');
    const {inteiro}=calcMaquina(v);
    document.getElementById('input-valor-final').value=(tipo==='base'?v:inteiro).toFixed(2);
    atualizarAviso(v);
    document.getElementById('btn-confirmar-orc').disabled=false;
}
function atualizarAviso(v){
    if(!valorEscolhido) return;
    const {taxa,inteiro,real}=calcMaquina(v);
    const el=document.getElementById('sim-aviso'); el.style.display='block';
    el.innerHTML=valorEscolhido==='base'?'<strong>Enviando ao cliente: '+fmt(v)+'</strong>':'<strong>Enviando ao cliente: '+fmtInt(inteiro)+'</strong><br>🖳️ Digitar na máquina: <strong>'+fmt(real)+'</strong> em <strong>10x</strong>.';
}
function confirmarOrcamento(){
    const vf=parseFloat(document.getElementById('input-valor-final').value);
    const pr=parseInt(document.getElementById('input-prazo').value);
    if(isNaN(vf)||vf<=0){_toast('Escolha o valor a enviar',false);return;}
    if(isNaN(pr)||pr<=0){_toast('Informe o prazo',false);return;}
    fecharModal('modal-orcamento');
    _enviar('Orcada',{valor_orcamento:vf,prazo_orcamento:pr});
}
function confirmarReprovacao(){
    const m=document.getElementById('input-motivo').value.trim();
    if(!m){_toast('Informe o motivo',false);return;}
    fecharModal('modal-reprovacao'); _enviar('Reprovada',{motivo:m});
}
function atualizarStatus(s){
    const label=_statusMap[s]||s;
    if(!confirm('Alterar status para "'+label+'"?')) return;
    _enviar(s,{});
}

// ---- Modais ----
function abrirModal(id){document.getElementById(id).classList.add('aberto');}
function fecharModal(id){document.getElementById(id).classList.remove('aberto');}
function abrirModalOrcamento(){
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
function abrirModalReprovacao(){abrirModal('modal-reprovacao');setTimeout(()=>document.getElementById('input-motivo').focus(),150);}
document.querySelectorAll('.modal-overlay').forEach(o=>{
    o.addEventListener('click',e=>{if(e.target===o) o.classList.remove('aberto');});
});

// ---- wa.me ----
function _fecharWaEReload(){fecharModal('modal-wa');setTimeout(()=>location.reload(),300);}
function _abrirModalWa(waLink,statusLabel){
    document.getElementById('wa-status-texto').innerHTML='✅ Status atualizado para <strong>'+statusLabel+'</strong>. Deseja avisar o cliente?';
    document.getElementById('btn-wa-enviar').href=waLink;
    abrirModal('modal-wa');
}

// ---- Fetch ----
function _toast(msg, ok){
    const el=document.createElement('div'); el.className='g-toast'; el.textContent=msg;
    document.body.appendChild(el); setTimeout(()=>el.remove(),3000);
}
function _enviar(status,extras){
    fetch('atualizar_status.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:_pedidoId,status,...extras})})
    .then(r=>r.json())
    .then(data=>{
        if(data.sucesso){
            const label=_statusMap[status]||status;
            document.getElementById('status-icone').textContent=(status.match(/[\u{1F300}-\u{1F9FF}]/u)||[''])[0]||'•';
            document.getElementById('status-label').textContent=label.replace(/^\S+\s/,'');
            if(data.atualizado_em) document.getElementById('atualizado-em').textContent='Atualizado '+data.atualizado_em;
            if(data.wa_link) _abrirModalWa(data.wa_link,label);
            else { _toast('✅ Status atualizado!',true); setTimeout(()=>location.reload(),1500); }
        } else _toast('❌ '+(data.erro||'Erro desconhecido'),false);
    })
    .catch(()=>_toast('❌ Erro de conexão',false));
}
</script>
<script src="assets/js/admin.js?v=<?php echo $v; ?>"></script>
</body>
</html>
