<?php
/**
 * PÁGINA PÚBLICA DE ACOMPANHAMENTO DO PEDIDO
 * Versão: 4.4 - remove débito do bloco cartão; parcelas iniciam em 2x
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

        $has_prazo = $has_motivo = false;
        try {
            $cols = $conn->query("SHOW COLUMNS FROM pre_os")->fetchAll(PDO::FETCH_COLUMN);
            $has_prazo  = in_array('prazo_orcamento',   $cols);
            $has_motivo = in_array('motivo_reprovacao', $cols);
        } catch (PDOException $e) {}

        $extra = '';
        if ($has_prazo)  $extra .= ', p.prazo_orcamento';
        if ($has_motivo) $extra .= ', p.motivo_reprovacao';

        $stmt = $conn->prepare("
            SELECT p.id, p.status, p.criado_em, p.atualizado_em,
                   p.valor_orcamento, p.observacoes, p.public_token $extra,
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

        if ($pedido) {
            $pedido['prazo_orcamento']   = $pedido['prazo_orcamento']   ?? null;
            $pedido['motivo_reprovacao'] = $pedido['motivo_reprovacao'] ?? null;

            $stmt_s = $conn->prepare("SELECT s.nome FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id = s.id WHERE ps.pre_os_id = :id");
            $stmt_s->execute([':id' => $pedido['id']]);
            $servicos = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

            try {
                $hcols = $conn->query("SHOW COLUMNS FROM status_historico")->fetchAll(PDO::FETCH_COLUMN);
                $hp    = in_array('prazo_orcamento', $hcols) ? ', prazo_orcamento' : '';
                $stmt_h = $conn->prepare("SELECT status, valor_orcamento$hp, motivo, criado_em FROM status_historico WHERE pre_os_id = :id ORDER BY criado_em ASC");
                $stmt_h->execute([':id' => $pedido['id']]);
                $historico = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
                foreach ($historico as &$h) { $h['prazo_orcamento'] = $h['prazo_orcamento'] ?? null; } unset($h);
            } catch (PDOException $e) {}
        } else {
            $erro = 'Pedido não encontrado. Verifique se o código foi digitado corretamente.';
        }
    } catch (PDOException $e) {
        error_log('Erro acompanhar: ' . $e->getMessage());
        $erro = 'Erro ao buscar informações. Tente novamente.';
    }
}

$statusInfo = [
    'Pre-OS'               => ['label'=>'Recebido',              'cor'=>'#1565c0','bg'=>'#e3f2fd','icone'=>'🗒️','desc'=>'Seu pedido foi recebido e está na fila para análise.'],
    'Em analise'           => ['label'=>'Em Análise',            'cor'=>'#00695c','bg'=>'#e0f2f1','icone'=>'🔍','desc'=>'Nosso técnico está avaliando o instrumento.'],
    'Orcada'               => ['label'=>'Orçamento Pronto',      'cor'=>'#e65100','bg'=>'#fff3e0','icone'=>'💰','desc'=>'O orçamento está disponível. Escolha a forma de pagamento e aprove abaixo.'],
    'Aguardando aprovacao' => ['label'=>'Aguardando Aprovação', 'cor'=>'#f57f17','bg'=>'#fffde7','icone'=>'⏳','desc'=>'Aguardando sua confirmação para iniciar o serviço.'],
    'Aprovada'             => ['label'=>'Em Serviço',            'cor'=>'#1b5e20','bg'=>'#e8f5e9','icone'=>'✅','desc'=>'Serviço aprovado! Estamos trabalhando no seu instrumento.'],
    'Reprovada'            => ['label'=>'Não Aprovado',          'cor'=>'#b71c1c','bg'=>'#ffebee','icone'=>'❌','desc'=>'O orçamento não foi aprovado.'],
    'Cancelada'            => ['label'=>'Cancelado',             'cor'=>'#37474f','bg'=>'#eceff1','icone'=>'🚫','desc'=>'Este pedido foi cancelado.'],
];
$icones_hist = ['Pre-OS'=>'🗒️','Em analise'=>'🔍','Orcada'=>'💰','Aguardando aprovacao'=>'⏳','Aprovada'=>'✅','Reprovada'=>'❌','Cancelada'=>'🚫'];
$pode_aprovar = $pedido && in_array($pedido['status'], ['Orcada','Aguardando aprovacao']) && !empty($pedido['valor_orcamento']);
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
        .busca-token button{padding:11px 22px;background:#0d9488;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap}
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
        .pgto-opcoes{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:18px}
        .pgto-btn{border:2px solid #e0e0e0;border-radius:10px;padding:14px 10px;text-align:center;cursor:pointer;transition:all .2s;background:#fff;font-family:inherit}
        .pgto-btn:hover{border-color:#0d9488;background:#f0fdfb}
        .pgto-btn.ativo{border-color:#0d9488;background:#e0f2f1;box-shadow:0 0 0 3px rgba(13,148,136,.15)}
        .pgto-btn .pgto-icone{font-size:28px;display:block;margin-bottom:6px}
        .pgto-btn .pgto-nome{font-size:13px;font-weight:600;color:#333}
        .pgto-btn .pgto-sub{font-size:11px;color:#0d9488;margin-top:3px}
        .pgto-resultado{background:#f8f9fa;border-radius:10px;padding:18px;margin-bottom:18px;display:none}
        .pgto-resultado.visivel{display:block}
        .pgto-res-titulo{font-size:13px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
        .pgto-linha{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #eee;font-size:14px}
        .pgto-linha:last-child{border-bottom:none}
        .pgto-linha.destaque{background:#e8f5e9;border-radius:8px;padding:10px 12px;margin:4px -4px;border:none}
        .pgto-linha .lbl{color:#666}
        .pgto-linha .val{font-weight:700;color:#222}
        .pgto-linha.destaque .val{color:#2e7d32;font-size:16px}
        .parcelas-lista{display:flex;flex-direction:column;gap:6px;max-height:280px;overflow-y:auto}
        .parcela-item{display:flex;justify-content:space-between;align-items:center;padding:9px 12px;border-radius:8px;background:#f5f5f5;font-size:13px;cursor:pointer;border:2px solid transparent;transition:all .15s}
        .parcela-item:hover{background:#e0f2f1;border-color:#0d9488}
        .parcela-item.ativo{background:#e0f2f1;border-color:#0d9488}
        .parc-n{font-weight:700;color:#333}
        .parc-v{font-weight:700;color:#0d9488}
        .acoes{display:flex;gap:12px;margin-top:20px}
        .btn-aprovar{flex:1;padding:14px;background:#2e7d32;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s}
        .btn-aprovar:hover{background:#1b5e20}
        .btn-aprovar:disabled{background:#bbb;cursor:not-allowed}
        .btn-reprovar{padding:14px 20px;background:#fff;color:#c62828;border:2px solid #ef9a9a;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s}
        .btn-reprovar:hover{background:#ffebee;border-color:#c62828}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;justify-content:center;align-items:center;padding:20px}
        .modal-overlay.aberto{display:flex}
        .modal-box{background:#fff;border-radius:12px;padding:28px;width:100%;max-width:440px;box-shadow:0 8px 32px rgba(0,0,0,.2)}
        .modal-title{font-size:17px;font-weight:700;margin-bottom:14px;color:#333}
        .modal-box label{display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px}
        .modal-box textarea{width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;min-height:100px;transition:border-color .2s}
        .modal-box textarea:focus{outline:none;border-color:#ef5350}
        .modal-actions{display:flex;gap:10px;margin-top:16px;justify-content:flex-end}
        .btn-modal-cancel{padding:10px 18px;background:#f5f5f5;border:none;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit}
        .btn-modal-confirm{padding:10px 20px;background:#c62828;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit}
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
            .status-card,.orc-card{flex-direction:column;text-align:center;gap:12px}
            .busca-token form,.acoes{flex-direction:column}
            .header span,.orc-emoji{display:none}
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
                   value="<?php echo htmlspecialchars($token); ?>" autocomplete="off" spellcheck="false">
            <button type="submit">Buscar</button>
        </form>
    </div>

    <?php if (!empty($token) && $erro): ?>
        <div class="erro-box"><span class="erro-icone">😕</span><h2>Pedido não encontrado</h2><p><?php echo htmlspecialchars($erro); ?></p></div>

    <?php elseif ($pedido):
        $si = $statusInfo[$pedido['status']] ?? ['label'=>$pedido['status'],'cor'=>'#666','bg'=>'#f5f5f5','icone'=>'•','desc'=>''];
    ?>
        <div class="status-card" style="background:<?php echo $si['bg']; ?>;color:<?php echo $si['cor']; ?>">
            <div class="status-icone"><?php echo $si['icone']; ?></div>
            <div><div class="status-label"><?php echo $si['label']; ?></div><div class="status-desc"><?php echo $si['desc']; ?></div></div>
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

        <?php if ($pode_aprovar): ?>
        <div class="card" id="card-pagamento">
            <div class="card-title">💳 Forma de Pagamento &amp; Aprovação</div>
            <div class="pgto-opcoes">
                <button class="pgto-btn" onclick="selecionarPgto('pix')" id="btn-pix">
                    <span class="pgto-icone">🟢</span>
                    <span class="pgto-nome">PIX / Dinheiro</span>
                    <span class="pgto-sub">5% de desconto</span>
                </button>
                <button class="pgto-btn" onclick="selecionarPgto('entrada')" id="btn-entrada">
                    <span class="pgto-icone">🔑</span>
                    <span class="pgto-nome">Entrada + Retirada</span>
                    <span class="pgto-sub">50% + 50%</span>
                </button>
                <button class="pgto-btn" onclick="selecionarPgto('cartao')" id="btn-cartao">
                    <span class="pgto-icone">📳</span>
                    <span class="pgto-nome">Cartão de Crédito</span>
                    <span class="pgto-sub">parcelado em até 10x</span>
                </button>
            </div>

            <div class="pgto-resultado" id="res-pix">
                <div class="pgto-res-titulo">🟢 PIX ou Dinheiro — à vista</div>
                <div class="pgto-linha"><span class="lbl">Valor do orçamento</span><span class="val" id="pix-original"></span></div>
                <div class="pgto-linha"><span class="lbl">Desconto à vista (5%)</span><span class="val" style="color:#2e7d32" id="pix-desconto"></span></div>
                <div class="pgto-linha destaque"><span class="lbl">Você paga</span><span class="val" id="pix-final"></span></div>
            </div>

            <div class="pgto-resultado" id="res-entrada">
                <div class="pgto-res-titulo">🔑 Entrada + Pagamento na Retirada</div>
                <div class="pgto-linha"><span class="lbl">Valor total</span><span class="val" id="ent-total"></span></div>
                <div class="pgto-linha"><span class="lbl">Entrada (50% agora)</span><span class="val" style="color:#1565c0" id="ent-entrada"></span></div>
                <div class="pgto-linha destaque"><span class="lbl">Na retirada (50%)</span><span class="val" id="ent-retirada"></span></div>
            </div>

            <div class="pgto-resultado" id="res-cartao">
                <div class="pgto-res-titulo">📳 Cartão de Crédito</div>
                <div style="font-size:12px;color:#999;margin-bottom:10px">Selecione a quantidade de parcelas:</div>
                <div class="parcelas-lista" id="parcelas-lista"></div>
                <div class="pgto-linha destaque" style="margin-top:12px;display:none" id="res-parc-selecionada">
                    <span class="lbl" id="parc-sel-label"></span>
                    <span class="val" id="parc-sel-valor"></span>
                </div>
            </div>

            <div class="acoes">
                <button class="btn-aprovar" id="btn-aprovar" onclick="confirmarAprovacao()" disabled>✅ Selecione a forma de pagamento</button>
                <button class="btn-reprovar" onclick="abrirReprovacao()">❌ Não aprovar</button>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title">📋 Dados do Pedido</div>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Número</div><div class="info-value">#<?php echo $pedido['id']; ?></div></div>
                <div class="info-item"><div class="info-label">Cliente</div><div class="info-value"><?php echo htmlspecialchars($pedido['cliente_nome']); ?></div></div>
                <div class="info-item"><div class="info-label">Instrumento</div><div class="info-value"><?php
                    echo htmlspecialchars(trim($pedido['instrumento_tipo'].' '.$pedido['instrumento_marca'].' '.$pedido['instrumento_modelo']));
                    if (!empty($pedido['instrumento_cor'])) echo ' <span style="color:#aaa;font-size:12px">('. htmlspecialchars($pedido['instrumento_cor']).')</span>';
                ?></div></div>
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
                        <div class="tl-detalhe valor">💰 R$ <?php echo number_format($h['valor_orcamento'],2,',','.'); ?><?php if (!empty($h['prazo_orcamento'])): ?> &nbsp;• <strong><?php echo (int)$h['prazo_orcamento']; ?> dias úteis</strong><?php endif; ?></div>
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

<div class="modal-overlay" id="modal-reprovacao">
    <div class="modal-box">
        <div class="modal-title">❌ Motivo da Não Aprovação</div>
        <label>Conte-nos o motivo (obrigatório):</label>
        <textarea id="motivo-reprovacao" placeholder="Ex: O valor ficou acima do meu orçamento..."></textarea>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="fecharModal()">Cancelar</button>
            <button class="btn-modal-confirm" onclick="enviarReprovacao()">❌ Confirmar Reprovação</button>
        </div>
    </div>
</div>

<div class="footer">Adonis Custom &mdash; Acompanhamento de Pedidos</div>

<?php if ($pode_aprovar): ?>
<script>
const VALOR_BASE   = <?php echo (float)$pedido['valor_orcamento']; ?>;
const PEDIDO_TOKEN = '<?php echo htmlspecialchars($pedido['public_token']); ?>';
const API_URL      = '../../backend/public/aprovar_orcamento.php';
const MAX_PARCELAS = 10;
const MIN_PARCELAS = 2; // Cartão de crédito: mínimo 2x (débito vai via PIX)

let pgtoSelecionado = null, pagamentoPayload = {};
function fmt(v){ return 'R$ ' + v.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }

function selecionarPgto(tipo) {
    pgtoSelecionado = tipo; pagamentoPayload = {};
    ['pix','entrada','cartao'].forEach(t => {
        document.getElementById('btn-'+t).classList.toggle('ativo', t===tipo);
        document.getElementById('res-'+t).classList.toggle('visivel', t===tipo);
    });
    if (tipo === 'pix') {
        const desc  = VALOR_BASE * 0.05;
        const final = VALOR_BASE - desc;
        document.getElementById('pix-original').textContent = fmt(VALOR_BASE);
        document.getElementById('pix-desconto').textContent = '- ' + fmt(desc);
        document.getElementById('pix-final').textContent    = fmt(final);
        pagamentoPayload = { forma:'PIX/Dinheiro', valor_final:final, descricao:'PIX/Dinheiro à vista com 5% de desconto' };
        habilitarAprovar(true);
    } else if (tipo === 'entrada') {
        const metade = VALOR_BASE * 0.50;
        document.getElementById('ent-total').textContent    = fmt(VALOR_BASE);
        document.getElementById('ent-entrada').textContent  = fmt(metade);
        document.getElementById('ent-retirada').textContent = fmt(metade);
        pagamentoPayload = { forma:'Entrada+Retirada', valor_final:VALOR_BASE, entrada:metade, retirada:metade, descricao:'Entrada 50% + 50% na retirada' };
        habilitarAprovar(true);
    } else if (tipo === 'cartao') {
        const lista = document.getElementById('parcelas-lista');
        lista.innerHTML = '';
        document.getElementById('res-parc-selecionada').style.display = 'none';
        // Parcelas de 2x a 10x (débito não parcela — quem quer pagar à vista usa PIX)
        for (let n = MIN_PARCELAS; n <= MAX_PARCELAS; n++) {
            const pv  = VALOR_BASE / n;
            const el  = document.createElement('div');
            el.className = 'parcela-item';
            const label = n + 'x de ' + fmt(pv);
            el.innerHTML = `<span class="parc-n">${label}</span><span class="parc-v">${fmt(pv)}</span>`;
            el.onclick = () => {
                document.querySelectorAll('.parcela-item').forEach(e => e.classList.remove('ativo'));
                el.classList.add('ativo');
                document.getElementById('parc-sel-label').textContent = label;
                document.getElementById('parc-sel-valor').textContent  = fmt(pv);
                document.getElementById('res-parc-selecionada').style.display = 'flex';
                pagamentoPayload = { forma:'Cartão', descricao:label, valor_final:VALOR_BASE, por_parcela:pv, parcelas:n };
                habilitarAprovar(true);
            };
            lista.appendChild(el);
        }
        habilitarAprovar(false);
    }
}

function habilitarAprovar(ok) {
    const btn = document.getElementById('btn-aprovar');
    btn.disabled    = !ok;
    btn.textContent = ok ? '✅ Aprovar Orçamento' : '✅ Selecione a forma de pagamento';
}
function confirmarAprovacao() {
    if (!pagamentoPayload.forma) return;
    if (!confirm('Confirmar aprovação?\n\nForma: '+pagamentoPayload.descricao+'\nValor total: '+fmt(pagamentoPayload.valor_final))) return;
    enviar('Aprovada', { pagamento: pagamentoPayload });
}
function abrirReprovacao() {
    document.getElementById('motivo-reprovacao').value = '';
    document.getElementById('modal-reprovacao').classList.add('aberto');
}
function fecharModal() { document.getElementById('modal-reprovacao').classList.remove('aberto'); }
function enviarReprovacao() {
    const motivo = document.getElementById('motivo-reprovacao').value.trim();
    if (!motivo) { alert('Por favor, informe o motivo.'); return; }
    fecharModal();
    enviar('Reprovada', { motivo });
}
function enviar(status, extras) {
    fetch(API_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:PEDIDO_TOKEN,status,...extras})})
    .then(r=>r.json())
    .then(d=>{
        if(d.sucesso){
            document.getElementById('card-pagamento').innerHTML='<div style="text-align:center;padding:20px 0">'+(status==='Aprovada'?'✅ <strong>Orçamento aprovado!</strong><br><span style="font-size:13px;color:#555">Entraremos em contato em breve para combinar os detalhes.</span>':'❌ <strong>Orçamento não aprovado.</strong><br><span style="font-size:13px;color:#555">Registro enviado. Obrigado pelo retorno!</span>')+'</div>';
            setTimeout(()=>location.reload(),2500);
        } else { alert('❌ Erro: '+(d.erro||'Tente novamente.')); }
    })
    .catch(()=>alert('❌ Erro de conexão. Tente novamente.'));
}
document.getElementById('modal-reprovacao').addEventListener('click',function(e){if(e.target===this)fecharModal();});
</script>
<?php endif; ?>
</body>
</html>
