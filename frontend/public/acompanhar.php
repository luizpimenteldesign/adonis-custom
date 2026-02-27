<?php
/**
 * PÁGINA PÚBLICA DE ACOMPANHAMENTO DO PEDIDO
 * Versão: 3.1 - query segura (colunas opcionais)
 * Data: 27/02/2026
 */

require_once '../../backend/config/Database.php';

$token     = isset($_GET['token']) ? trim($_GET['token']) : '';
$pedido    = null;
$historico = [];
$servicos  = [];
$erro      = '';

if (!empty($token)) {
    try {
        $db   = new Database();
        $conn = $db->getConnection();

        // Detecta colunas opcionais que podem ainda não existir
        $has_prazo  = false;
        $has_motivo = false;
        try {
            $cols = $conn->query("SHOW COLUMNS FROM pre_os")->fetchAll(PDO::FETCH_COLUMN);
            $has_prazo  = in_array('prazo_orcamento',    $cols);
            $has_motivo = in_array('motivo_reprovacao',  $cols);
        } catch (PDOException $e) {}

        $extra_cols = '';
        if ($has_prazo)  $extra_cols .= ', p.prazo_orcamento';
        if ($has_motivo) $extra_cols .= ', p.motivo_reprovacao';

        $stmt = $conn->prepare("
            SELECT
                p.id, p.status, p.criado_em, p.atualizado_em,
                p.valor_orcamento, p.observacoes
                {$extra_cols},
                c.nome as cliente_nome,
                i.tipo as instrumento_tipo, i.marca as instrumento_marca,
                i.modelo as instrumento_modelo, i.cor as instrumento_cor
            FROM pre_os p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN instrumentos i ON p.instrumento_id = i.id
            WHERE p.public_token = :token LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        // Garantir chaves com valor padrão caso coluna não exista
        if ($pedido) {
            $pedido['prazo_orcamento']   = $pedido['prazo_orcamento']   ?? null;
            $pedido['motivo_reprovacao'] = $pedido['motivo_reprovacao'] ?? null;
        } else {
            $erro = 'Pedido não encontrado. Verifique se o código foi digitado corretamente.';
        }

        if ($pedido) {
            $stmt_s = $conn->prepare("
                SELECT s.nome
                FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id = s.id
                WHERE ps.pre_os_id = :id
            ");
            $stmt_s->execute([':id' => $pedido['id']]);
            $servicos = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

            // Histórico também com colunas opcionais
            try {
                $has_hist_prazo = false;
                try {
                    $hcols = $conn->query("SHOW COLUMNS FROM status_historico")->fetchAll(PDO::FETCH_COLUMN);
                    $has_hist_prazo = in_array('prazo_orcamento', $hcols);
                } catch (PDOException $e) {}

                $hist_prazo_col = $has_hist_prazo ? ', prazo_orcamento' : '';
                $stmt_h = $conn->prepare("
                    SELECT status, valor_orcamento{$hist_prazo_col}, motivo, criado_em
                    FROM status_historico
                    WHERE pre_os_id = :id ORDER BY criado_em ASC
                ");
                $stmt_h->execute([':id' => $pedido['id']]);
                $historico = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
                foreach ($historico as &$h) {
                    $h['prazo_orcamento'] = $h['prazo_orcamento'] ?? null;
                }
                unset($h);
            } catch (PDOException $e) {}
        }

    } catch (PDOException $e) {
        error_log('Erro acompanhar: ' . $e->getMessage());
        $erro = 'Erro ao buscar informações. Tente novamente.';
    }
}

$statusInfo = [
    'Pre-OS'               => ['label'=>'Recebido',              'cor'=>'#1565c0','bg'=>'#e3f2fd','icone'=>'🗒️','desc'=>'Seu pedido foi recebido e está na fila para análise.'],
    'Em analise'           => ['label'=>'Em Análise',            'cor'=>'#00695c','bg'=>'#e0f2f1','icone'=>'🔍','desc'=>'Nosso técnico está avaliando o instrumento.'],
    'Orcada'               => ['label'=>'Orçamento Pronto',      'cor'=>'#e65100','bg'=>'#fff3e0','icone'=>'💰','desc'=>'O orçamento está disponível. Entre em contato para aprovar.'],
    'Aguardando aprovacao' => ['label'=>'Aguardando Aprovação', 'cor'=>'#f57f17','bg'=>'#fffde7','icone'=>'⏳','desc'=>'Aguardando sua confirmação para iniciar o serviço.'],
    'Aprovada'             => ['label'=>'Em Serviço',            'cor'=>'#1b5e20','bg'=>'#e8f5e9','icone'=>'✅','desc'=>'Serviço aprovado! Estamos trabalhando no seu instrumento.'],
    'Reprovada'            => ['label'=>'Não Aprovado',          'cor'=>'#b71c1c','bg'=>'#ffebee','icone'=>'❌','desc'=>'O orçamento não foi aprovado.'],
    'Cancelada'            => ['label'=>'Cancelado',             'cor'=>'#37474f','bg'=>'#eceff1','icone'=>'🚫','desc'=>'Este pedido foi cancelado.'],
];

$icones_hist = ['Pre-OS'=>'🗒️','Em analise'=>'🔍','Orcada'=>'💰','Aguardando aprovacao'=>'⏳','Aprovada'=>'✅','Reprovada'=>'❌','Cancelada'=>'🚫'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Pedido — Adonis Custom</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Roboto',sans-serif;background:#f0f2f5;color:#333;min-height:100vh}
        .header{background:#111;padding:14px 24px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 10px rgba(0,0,0,.3);position:sticky;top:0;z-index:100}
        .header img{height:36px}
        .header span{color:#ccc;font-size:15px;font-weight:500}
        .busca-token{background:#fff;border-radius:12px;padding:28px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:24px;text-align:center}
        .busca-token h2{font-size:18px;margin-bottom:6px;color:#222}
        .busca-token p{font-size:13px;color:#888;margin-bottom:18px}
        .busca-token form{display:flex;gap:10px;max-width:480px;margin:0 auto}
        .busca-token input{flex:1;padding:11px 16px;border:2px solid #ddd;border-radius:8px;font-size:14px;font-family:inherit;letter-spacing:.5px;transition:border-color .2s}
        .busca-token input:focus{outline:none;border-color:#0d9488}
        .busca-token button{padding:11px 22px;background:#0d9488;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .2s}
        .busca-token button:hover{background:#0a7c72}
        .container{max-width:740px;margin:0 auto;padding:32px 20px}
        .card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:20px}
        .card-title{font-size:15px;font-weight:700;color:#444;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #f0f0f0;display:flex;align-items:center;gap:8px}
        .status-card{border-radius:12px;padding:24px 28px;display:flex;align-items:center;gap:20px;margin-bottom:20px;border:1px solid rgba(0,0,0,.06)}
        .status-icone{font-size:52px;flex-shrink:0;line-height:1}
        .status-label{font-size:24px;font-weight:700}
        .status-desc{font-size:14px;margin-top:6px;opacity:.8;line-height:1.5}
        .orc-card{border-radius:12px;padding:20px 24px;margin-bottom:20px;background:#e8f5e9;border-left:5px solid #43a047;display:flex;align-items:center;gap:20px}
        .orc-emoji{font-size:36px;flex-shrink:0}
        .orc-label{font-size:11px;color:#388e3c;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
        .orc-valor{font-size:32px;font-weight:700;color:#2e7d32;line-height:1.1;margin:2px 0}
        .orc-prazo{font-size:13px;color:#388e3c;font-weight:600;margin-top:4px}
        .orc-prazo strong{color:#1b5e20}
        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
        .info-item{background:#f8f9fa;border-radius:8px;padding:12px 14px}
        .info-label{font-size:10px;color:#999;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
        .info-value{font-size:14px;color:#333;font-weight:500}
        .servico-item{padding:9px 0;border-bottom:1px solid #f0f0f0;font-size:14px;color:#444;display:flex;align-items:center;gap:8px}
        .servico-item:last-child{border-bottom:none}
        .servico-item::before{content:'•';color:#0d9488;font-size:18px;flex-shrink:0}
        .timeline{list-style:none;padding:0;margin:0;position:relative}
        .timeline::before{content:'';position:absolute;left:16px;top:6px;bottom:6px;width:2px;background:#e8e8e8}
        .tl-item{display:flex;gap:14px;padding-bottom:22px;position:relative}
        .tl-item:last-child{padding-bottom:0}
        .tl-dot{width:32px;height:32px;border-radius:50%;background:#0d9488;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;z-index:1;box-shadow:0 0 0 3px #fff}
        .tl-body{padding-top:4px;flex:1}
        .tl-status{font-weight:600;font-size:14px;color:#333}
        .tl-data{font-size:11px;color:#aaa;margin-top:2px}
        .tl-detalhe{margin-top:6px;padding:7px 11px;border-radius:6px;font-size:13px}
        .tl-detalhe.valor{background:#e8f5e9;color:#2e7d32}
        .tl-detalhe.motivo{background:#ffebee;color:#c62828}
        .erro-box{text-align:center;padding:48px 20px}
        .erro-icone{font-size:56px;display:block;margin-bottom:14px}
        .erro-box h2{font-size:20px;color:#c62828;margin-bottom:8px}
        .erro-box p{font-size:13px;color:#999}
        .footer{text-align:center;padding:28px 20px 40px;font-size:12px;color:#ccc}
        @media(max-width:520px){
            .status-card{flex-direction:column;text-align:center;gap:12px}
            .orc-card{flex-direction:column;text-align:center;gap:8px}
            .busca-token form{flex-direction:column}
            .header span{display:none}
        }
    </style>
</head>
<body>
<header class="header">
    <img src="assets/img/Logo-Adonis3.png" alt="Adonis Custom">
    <span>Acompanhamento de Pedido</span>
</header>

<div class="container">
    <div class="busca-token">
        <h2>🔍 Consultar meu pedido</h2>
        <p>Digite o código de acompanhamento que você recebeu</p>
        <form method="GET">
            <input type="text" name="token" placeholder="Cole seu código aqui..."
                   value="<?php echo htmlspecialchars($token); ?>"
                   autocomplete="off" spellcheck="false">
            <button type="submit">Buscar</button>
        </form>
    </div>

    <?php if (!empty($token) && $erro): ?>
        <div class="erro-box">
            <span class="erro-icone">😕</span>
            <h2>Pedido não encontrado</h2>
            <p><?php echo htmlspecialchars($erro); ?></p>
        </div>

    <?php elseif ($pedido):
        $si = $statusInfo[$pedido['status']] ?? ['label'=>$pedido['status'],'cor'=>'#666','bg'=>'#f5f5f5','icone'=>'•','desc'=>''];
    ?>

        <div class="status-card" style="background:<?php echo $si['bg']; ?>;color:<?php echo $si['cor']; ?>">
            <div class="status-icone"><?php echo $si['icone']; ?></div>
            <div>
                <div class="status-label"><?php echo $si['label']; ?></div>
                <div class="status-desc"><?php echo $si['desc']; ?></div>
            </div>
        </div>

        <?php if (!empty($pedido['valor_orcamento'])): ?>
        <div class="orc-card">
            <div class="orc-emoji">💰</div>
            <div>
                <div class="orc-label">Valor do Orçamento</div>
                <div class="orc-valor">R$ <?php echo number_format($pedido['valor_orcamento'],2,',','.'); ?></div>
                <?php if (!empty($pedido['prazo_orcamento'])): ?>
                <div class="orc-prazo">📅 Prazo estimado: <strong><?php echo (int)$pedido['prazo_orcamento']; ?> dias úteis</strong></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title">📋 Dados do Pedido</div>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Número</div><div class="info-value">#<?php echo $pedido['id']; ?></div></div>
                <div class="info-item"><div class="info-label">Cliente</div><div class="info-value"><?php echo htmlspecialchars($pedido['cliente_nome']); ?></div></div>
                <div class="info-item">
                    <div class="info-label">Instrumento</div>
                    <div class="info-value"><?php
                        echo htmlspecialchars(trim($pedido['instrumento_tipo'].' '.$pedido['instrumento_marca'].' '.$pedido['instrumento_modelo']));
                        if (!empty($pedido['instrumento_cor'])) echo ' <span style="color:#aaa;font-size:12px">('. htmlspecialchars($pedido['instrumento_cor']).')</span>';
                    ?></div>
                </div>
                <div class="info-item"><div class="info-label">Abertura</div><div class="info-value"><?php echo date('d/m/Y', strtotime($pedido['criado_em'])); ?></div></div>
                <div class="info-item"><div class="info-label">Última Atualização</div><div class="info-value"><?php echo date('d/m/Y H:i', strtotime($pedido['atualizado_em'])); ?></div></div>
            </div>
        </div>

        <?php if (!empty($servicos)): ?>
        <div class="card">
            <div class="card-title">🔧 Serviços Solicitados</div>
            <?php foreach ($servicos as $s): ?>
            <div class="servico-item"><?php echo htmlspecialchars($s['nome']); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($historico)): ?>
        <div class="card">
            <div class="card-title">🕓 Histórico de Atualizações</div>
            <ul class="timeline">
                <?php foreach ($historico as $h): ?>
                <li class="tl-item">
                    <div class="tl-dot"><?php echo $icones_hist[$h['status']] ?? '•'; ?></div>
                    <div class="tl-body">
                        <div class="tl-status"><?php echo htmlspecialchars($statusInfo[$h['status']]['label'] ?? $h['status']); ?></div>
                        <div class="tl-data"><?php echo date('d/m/Y às H:i', strtotime($h['criado_em'])); ?></div>
                        <?php if (!empty($h['valor_orcamento'])): ?>
                        <div class="tl-detalhe valor">💰 R$ <?php echo number_format($h['valor_orcamento'],2,',','.'); ?>
                            <?php if (!empty($h['prazo_orcamento'])): ?> &nbsp;• <strong><?php echo (int)$h['prazo_orcamento']; ?> dias úteis</strong><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($h['motivo'])): ?>
                        <div class="tl-detalhe motivo">⚠️ <?php echo htmlspecialchars($h['motivo']); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($pedido['observacoes'])): ?>
        <div class="card">
            <div class="card-title">📝 Suas Observações</div>
            <div style="font-size:14px;color:#555;line-height:1.8"><?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($pedido['motivo_reprovacao'])): ?>
        <div class="card" style="border-left:4px solid #ef5350">
            <div class="card-title" style="color:#c62828">❌ Motivo da Não Aprovação</div>
            <div style="font-size:14px;color:#555;line-height:1.7"><?php echo nl2br(htmlspecialchars($pedido['motivo_reprovacao'])); ?></div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<div class="footer">Adonis Custom &mdash; Acompanhamento de Pedidos</div>
</body>
</html>
