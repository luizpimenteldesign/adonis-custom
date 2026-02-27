<?php
/**
 * PÁGINA PÚBLICA DE ACOMPANHAMENTO DO PEDIDO
 * Versão: 1.0
 * Data: 27/02/2026
 */

require_once '../../backend/config/Database.php';

$token  = isset($_GET['token']) ? trim($_GET['token']) : '';
$pedido = null;
$historico = [];
$servicos  = [];
$erro      = '';

if (empty($token)) {
    $erro = 'Token não informado.';
} else {
    try {
        $db   = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
            SELECT
                p.id, p.status, p.criado_em, p.atualizado_em,
                p.valor_orcamento, p.observacoes,
                c.nome as cliente_nome,
                i.tipo as instrumento_tipo, i.marca as instrumento_marca, i.modelo as instrumento_modelo
            FROM pre_os p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN instrumentos i ON p.instrumento_id = i.id
            WHERE p.public_token = :token LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            $erro = 'Pedido não encontrado. Verifique o token informado.';
        } else {
            // Serviços
            $stmt_s = $conn->prepare("
                SELECT s.nome, s.valor_base
                FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id = s.id
                WHERE ps.pre_os_id = :id
            ");
            $stmt_s->execute([':id' => $pedido['id']]);
            $servicos = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

            // Histórico
            try {
                $stmt_h = $conn->prepare("
                    SELECT status, valor_orcamento, motivo, criado_em
                    FROM status_historico
                    WHERE pre_os_id = :id ORDER BY criado_em ASC
                ");
                $stmt_h->execute([':id' => $pedido['id']]);
                $historico = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) { /* tabela pode ainda não existir */ }
        }

    } catch (PDOException $e) {
        error_log('Erro acompanhar: ' . $e->getMessage());
        $erro = 'Erro ao buscar informações. Tente novamente.';
    }
}

$statusInfo = [
    'Pre-OS'               => ['label'=>'Pré-OS',                'cor'=>'#1976d2','bg'=>'#e3f2fd','icone'=>'🗒️', 'desc'=>'Seu pedido foi recebido e está na fila de análise.'],
    'Em analise'           => ['label'=>'Em Análise',            'cor'=>'#00838f','bg'=>'#e0f7fa','icone'=>'🔍', 'desc'=>'Nosso técnico está avaliando o instrumento.'],
    'Orcada'               => ['label'=>'Orçamento Disponível',  'cor'=>'#f57c00','bg'=>'#fff3e0','icone'=>'💰', 'desc'=>'O orçamento foi preparado. Aguardamos sua aprovação.'],
    'Aguardando aprovacao' => ['label'=>'Aguardando Aprovação',  'cor'=>'#f57c00','bg'=>'#fff3e0','icone'=>'⏳', 'desc'=>'Aguardando sua confirmação para prosseguir.'],
    'Aprovada'             => ['label'=>'Aprovada',              'cor'=>'#2e7d32','bg'=>'#e8f5e9','icone'=>'✅', 'desc'=>'Serviço aprovado! Estamos trabalhando no seu instrumento.'],
    'Reprovada'            => ['label'=>'Reprovada',             'cor'=>'#c62828','bg'=>'#ffebee','icone'=>'❌', 'desc'=>'O orçamento foi reprovado.'],
    'Cancelada'            => ['label'=>'Cancelada',             'cor'=>'#455a64','bg'=>'#eceff1','icone'=>'🚫', 'desc'=>'Este pedido foi cancelado.'],
];

$icones_hist = [
    'Pre-OS'=>'🗒️','Em analise'=>'🔍','Orcada'=>'💰',
    'Aguardando aprovacao'=>'⏳','Aprovada'=>'✅','Reprovada'=>'❌','Cancelada'=>'🚫'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Pedido - Adonis Custom</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Roboto',sans-serif; background:#f5f5f5; color:#333; }

        .header {
            background:#000; color:#fff;
            padding:16px 24px;
            display:flex; align-items:center; gap:16px;
            box-shadow:0 2px 8px rgba(0,0,0,.15);
        }
        .header img { height:40px; }
        .header h1 { font-size:18px; font-weight:600; }

        .container { max-width:760px; margin:0 auto; padding:32px 20px; }

        .card {
            background:#fff; border-radius:12px;
            padding:24px; box-shadow:0 2px 8px rgba(0,0,0,.08);
            margin-bottom:20px;
        }
        .card-title {
            font-size:16px; font-weight:600; color:#333;
            margin-bottom:16px; padding-bottom:12px;
            border-bottom:2px solid #f0f0f0;
        }

        /* Status principal */
        .status-card {
            border-radius:12px; padding:24px;
            display:flex; align-items:center; gap:20px;
            margin-bottom:20px;
        }
        .status-icone { font-size:48px; flex-shrink:0; }
        .status-label { font-size:22px; font-weight:700; }
        .status-desc  { font-size:14px; margin-top:6px; opacity:.85; }

        /* Info grid */
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; }
        .info-item { background:#f9f9f9; border-radius:8px; padding:12px; }
        .info-label { font-size:11px; color:#888; font-weight:600; text-transform:uppercase; margin-bottom:4px; }
        .info-value { font-size:14px; color:#333; font-weight:500; }

        /* Orçamento */
        .orcamento-box {
            background:#e8f5e9; border-left:4px solid #4caf50;
            border-radius:8px; padding:16px;
            display:flex; align-items:center; gap:12px;
        }
        .orcamento-valor { font-size:28px; font-weight:700; color:#2e7d32; }
        .orcamento-label { font-size:13px; color:#388e3c; }

        /* Timeline */
        .timeline { list-style:none; padding:0; margin:0; position:relative; }
        .timeline::before {
            content:''; position:absolute; left:17px; top:0; bottom:0;
            width:2px; background:#e0e0e0;
        }
        .tl-item { display:flex; gap:16px; padding-bottom:24px; position:relative; }
        .tl-dot {
            width:34px; height:34px; border-radius:50%;
            background:#0d9488; color:#fff;
            display:flex; align-items:center; justify-content:center;
            font-size:15px; flex-shrink:0; z-index:1;
        }
        .tl-status { font-weight:600; font-size:15px; }
        .tl-data   { font-size:12px; color:#888; margin-top:2px; }
        .tl-detalhe {
            margin-top:6px; padding:8px 12px;
            border-radius:6px; font-size:13px;
        }
        .tl-detalhe.valor  { background:#e8f5e9; color:#2e7d32; }
        .tl-detalhe.motivo { background:#ffebee; color:#c62828; }

        /* Erro */
        .erro-box {
            text-align:center; padding:60px 20px;
        }
        .erro-box .erro-icone { font-size:64px; display:block; margin-bottom:16px; }
        .erro-box h2 { color:#c62828; margin-bottom:8px; }
        .erro-box p  { color:#888; font-size:14px; }

        /* Footer */
        .footer { text-align:center; padding:32px 20px; font-size:12px; color:#bbb; }

        @media(max-width:480px) {
            .status-card  { flex-direction:column; text-align:center; }
            .orcamento-box{ flex-direction:column; text-align:center; }
            .header h1    { font-size:15px; }
        }
    </style>
</head>
<body>

    <header class="header">
        <img src="assets/img/Logo-Adonis3.png" alt="Adonis Custom">
        <h1>Acompanhamento de Pedido</h1>
    </header>

    <div class="container">

    <?php if ($erro): ?>
        <div class="erro-box">
            <span class="erro-icone">😕</span>
            <h2>Pedido não encontrado</h2>
            <p><?php echo htmlspecialchars($erro); ?></p>
        </div>
    <?php else:
        $si = $statusInfo[$pedido['status']] ?? ['label'=>$pedido['status'],'cor'=>'#666','bg'=>'#f5f5f5','icone'=>'•','desc'=>''];
    ?>

        <!-- STATUS PRINCIPAL -->
        <div class="status-card" style="background:<?php echo $si['bg']; ?>;color:<?php echo $si['cor']; ?>">
            <div class="status-icone"><?php echo $si['icone']; ?></div>
            <div>
                <div class="status-label"><?php echo $si['label']; ?></div>
                <div class="status-desc"><?php echo $si['desc']; ?></div>
            </div>
        </div>

        <!-- ORÇAMENTO -->
        <?php if (!empty($pedido['valor_orcamento'])): ?>
        <div class="card">
            <div class="orcamento-box">
                <div>💰</div>
                <div>
                    <div class="orcamento-label">Valor do Orçamento</div>
                    <div class="orcamento-valor">R$ <?php echo number_format($pedido['valor_orcamento'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- DADOS DO PEDIDO -->
        <div class="card">
            <div class="card-title">📋 Dados do Pedido</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Número do Pedido</div>
                    <div class="info-value">#<?php echo $pedido['id']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Cliente</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['cliente_nome']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Instrumento</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_tipo'] . ' ' . $pedido['instrumento_marca'] . ' ' . $pedido['instrumento_modelo']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Data do Pedido</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($pedido['criado_em'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Última Atualização</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($pedido['atualizado_em'])); ?></div>
                </div>
            </div>
        </div>

        <!-- SERVIÇOS -->
        <?php if (!empty($servicos)): ?>
        <div class="card">
            <div class="card-title">🔧 Serviços Solicitados</div>
            <?php foreach ($servicos as $s): ?>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f0;font-size:14px">
                <span><?php echo htmlspecialchars($s['nome']); ?></span>
                <span style="color:#888">R$ <?php echo number_format($s['valor_base'], 2, ',', '.'); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- HISTÓRICO -->
        <?php if (!empty($historico)): ?>
        <div class="card">
            <div class="card-title">🕓 Histórico de Atualizações</div>
            <ul class="timeline">
                <?php foreach ($historico as $h): ?>
                <li class="tl-item">
                    <div class="tl-dot"><?php echo $icones_hist[$h['status']] ?? '•'; ?></div>
                    <div>
                        <div class="tl-status"><?php echo htmlspecialchars($h['status']); ?></div>
                        <div class="tl-data"><?php echo date('d/m/Y H:i', strtotime($h['criado_em'])); ?></div>
                        <?php if (!empty($h['valor_orcamento'])): ?>
                        <div class="tl-detalhe valor">💰 Orçamento: R$ <?php echo number_format($h['valor_orcamento'], 2, ',', '.'); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($h['motivo'])): ?>
                        <div class="tl-detalhe motivo">Motivo: <?php echo htmlspecialchars($h['motivo']); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- OBSERVAÇÕES -->
        <?php if (!empty($pedido['observacoes'])): ?>
        <div class="card">
            <div class="card-title">📝 Observações</div>
            <div style="font-size:14px;color:#555;line-height:1.7"><?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
    </div>

    <div class="footer">Adonis Custom &mdash; Sistema de Acompanhamento de Pedidos</div>

</body>
</html>
