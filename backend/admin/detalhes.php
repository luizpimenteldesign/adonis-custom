<?php
/**
 * DETALHES DO PEDIDO - SISTEMA ADONIS
 * Versão: 3.1
 * Data: 27/02/2026
 */

require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$preos_id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("
        SELECT
            p.*,
            c.nome as cliente_nome, c.telefone as cliente_telefone,
            c.email as cliente_email, c.endereco as cliente_endereco,
            i.id as instrumento_id, i.tipo as instrumento_tipo,
            i.marca as instrumento_marca, i.modelo as instrumento_modelo,
            i.referencia as instrumento_referencia, i.cor as instrumento_cor,
            i.numero_serie as instrumento_serie
        FROM pre_os p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN instrumentos i ON p.instrumento_id = i.id
        WHERE p.id = :id LIMIT 1
    ");
    $stmt->bindParam(':id', $preos_id);
    $stmt->execute();
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) { header('Location: dashboard.php?erro=nao_encontrado'); exit; }

    $stmt_servicos = $conn->prepare("
        SELECT s.id, s.nome, s.descricao, s.valor_base, s.prazo_base
        FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id = s.id
        WHERE ps.pre_os_id = :pre_os_id
    ");
    $stmt_servicos->execute([':pre_os_id' => $preos_id]);
    $servicos = $stmt_servicos->fetchAll(PDO::FETCH_ASSOC);

    // Totais dos serviços
    $total_valor = 0;
    $total_prazo = 0;
    foreach ($servicos as $s) {
        $total_valor += (float)$s['valor_base'];
        $total_prazo += (int)$s['prazo_base'];
    }

    $fotos = [];
    if (!empty($pedido['instrumento_id'])) {
        $stmt_fotos = $conn->prepare("
            SELECT caminho, ordem FROM instrumento_fotos
            WHERE instrumento_id = :instrumento_id ORDER BY ordem ASC
        ");
        $stmt_fotos->execute([':instrumento_id' => $pedido['instrumento_id']]);
        $fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);
    }

    $historico = [];
    try {
        $stmt_hist = $conn->prepare("
            SELECT h.status, h.valor_orcamento, h.motivo, h.criado_em, a.nome as admin_nome
            FROM status_historico h
            LEFT JOIN admins a ON h.admin_id = a.id
            WHERE h.pre_os_id = :id ORDER BY h.criado_em ASC
        ");
        $stmt_hist->execute([':id' => $preos_id]);
        $historico = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

} catch (PDOException $e) {
    error_log('Erro detalhes: ' . $e->getMessage());
    header('Location: dashboard.php?erro=banco'); exit;
}

function formatarStatusDetalhes($status) {
    $badges = [
        'Pre-OS'               => '<span class="badge badge-new">🗒️ Pré-OS</span>',
        'Em analise'           => '<span class="badge badge-info">🔍 Em Análise</span>',
        'Orcada'               => '<span class="badge badge-warning">💰 Orçada</span>',
        'Aguardando aprovacao' => '<span class="badge badge-warning">⏳ Aguardando Aprovação</span>',
        'Aprovada'             => '<span class="badge badge-success">✅ Aprovada</span>',
        'Reprovada'            => '<span class="badge badge-danger">❌ Reprovada</span>',
        'Cancelada'            => '<span class="badge badge-dark">🚫 Cancelada</span>',
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
}

$v = time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?php echo $pedido['id']; ?> - Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <style>
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.5); z-index: 1000;
            justify-content: center; align-items: center;
        }
        .modal-overlay.aberto { display: flex; }
        .modal-box {
            background: #fff; border-radius: 12px; padding: 32px;
            width: 100%; max-width: 440px; box-shadow: 0 8px 32px rgba(0,0,0,.2);
        }
        .modal-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #333; }
        .modal-box label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:6px; }
        .modal-box input[type=number], .modal-box textarea {
            width: 100%; padding: 10px 14px; border: 2px solid #e0e0e0;
            border-radius: 8px; font-size: 14px; box-sizing: border-box;
            transition: border-color .2s; font-family: inherit;
        }
        .modal-box input[type=number]:focus, .modal-box textarea:focus { outline: none; border-color: #0d9488; }
        .modal-box textarea { resize: vertical; min-height: 100px; }
        .modal-actions { display:flex; gap:12px; margin-top:20px; justify-content:flex-end; }
        /* Timeline */
        .timeline { list-style: none; padding: 0; margin: 0; position: relative; }
        .timeline::before { content:''; position:absolute; left:18px; top:0; bottom:0; width:2px; background:#e0e0e0; }
        .timeline-item { display:flex; gap:16px; padding:0 0 24px 0; position:relative; }
        .timeline-dot { width:36px; height:36px; border-radius:50%; background:#0d9488; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; z-index:1; }
        .timeline-content { flex:1; padding-top:4px; }
        .timeline-status { font-weight:600; font-size:15px; color:#333; }
        .timeline-meta { font-size:12px; color:#888; margin-top:2px; }
        .timeline-detalhe { margin-top:6px; padding:8px 12px; border-radius:6px; font-size:13px; }
        .timeline-detalhe.valor  { background:#e8f5e9; color:#2e7d32; }
        .timeline-detalhe.motivo { background:#ffebee; color:#c62828; }
        /* Totais serviços */
        table tfoot td {
            font-weight: 700;
            font-size: 14px;
            border-top: 2px solid #e0e0e0;
            background: #f9f9f9;
            padding: 12px 16px;
        }
        .totais-row td:first-child { color: #555; }
        .total-valor { color: #2e7d32; font-size: 15px; }
        .total-prazo { color: #1565c0; font-size: 15px; }
        .total-obs   { font-size: 11px; color: #999; font-weight: 400; display:block; margin-top:2px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <a href="dashboard.php" class="back-button">← Voltar</a>
            <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis3.png" alt="Adonis" class="header-logo">
            <h1 class="header-title">Pedido #<?php echo $pedido['id']; ?></h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></div>
            </div>
            <a href="logout.php" class="btn-logout">🚪 Sair</a>
        </div>
    </header>

    <div class="container">

        <!-- STATUS E AÇÕES -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Status do Pedido</h2>
                    <div id="status-badge" style="margin-top:8px"><?php echo formatarStatusDetalhes($pedido['status']); ?></div>
                    <?php if (!empty($pedido['valor_orcamento'])): ?>
                    <div style="margin-top:8px;font-size:14px;color:#2e7d32;font-weight:600">
                        💰 Orçamento: R$ <?php echo number_format($pedido['valor_orcamento'], 2, ',', '.'); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pedido['motivo_reprovacao'])): ?>
                    <div style="margin-top:8px;font-size:13px;color:#c62828;background:#ffebee;padding:8px 12px;border-radius:6px">
                        ❌ Motivo: <?php echo htmlspecialchars($pedido['motivo_reprovacao']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <button class="btn btn-info"    onclick="atualizarStatus('Em analise')">🔍 Analisar</button>
                    <button class="btn btn-warning" onclick="abrirModalOrcamento()">💰 Orçar</button>
                    <button class="btn btn-success" onclick="atualizarStatus('Aprovada')">✅ Aprovar</button>
                    <button class="btn btn-danger"  onclick="abrirModalReprovacao()">❌ Reprovar</button>
                    <button class="btn btn-dark"    onclick="atualizarStatus('Cancelada')">🚫 Cancelar</button>
                </div>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Data de Criação</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($pedido['criado_em'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Última Atualização</div>
                    <div class="info-value" id="atualizado-em"><?php echo date('d/m/Y H:i', strtotime($pedido['atualizado_em'])); ?></div>
                </div>
            </div>
        </div>

        <!-- DADOS DO CLIENTE -->
        <div class="card">
            <div class="card-header"><h2 class="card-title">👤 Dados do Cliente</h2></div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Nome Completo</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['cliente_nome']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Telefone / WhatsApp</div>
                    <div class="info-value">
                        <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $pedido['cliente_telefone']); ?>" target="_blank" style="color:#25d366;text-decoration:none;">
                            📞 <?php echo htmlspecialchars($pedido['cliente_telefone']); ?>
                        </a>
                    </div>
                </div>
                <?php if (!empty($pedido['cliente_email'])): ?>
                <div class="info-item">
                    <div class="info-label">E-mail</div>
                    <div class="info-value">
                        <a href="mailto:<?php echo htmlspecialchars($pedido['cliente_email']); ?>" style="color:#667eea;text-decoration:none;">
                            📧 <?php echo htmlspecialchars($pedido['cliente_email']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($pedido['cliente_endereco'])): ?>
                <div class="info-item" style="grid-column:1/-1">
                    <div class="info-label">Endereço</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($pedido['cliente_endereco'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- DADOS DO INSTRUMENTO -->
        <div class="card">
            <div class="card-header"><h2 class="card-title">🎸 Dados do Instrumento</h2></div>
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
                <div class="info-item"><div class="info-label">Número de Série</div><div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_serie']); ?></div></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SERVIÇOS -->
        <div class="card">
            <div class="card-header"><h2 class="card-title">🔧 Serviços Solicitados</h2></div>
            <?php if (empty($servicos)): ?>
                <div style="padding:20px;color:#888;">Nenhum serviço selecionado</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Serviço</th><th>Descrição</th><th>Valor Base</th><th>Prazo</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servicos as $s): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s['nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['descricao']); ?></td>
                            <td>R$ <?php echo number_format($s['valor_base'], 2, ',', '.'); ?></td>
                            <td><?php echo (int)$s['prazo_base']; ?> dias</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="totais-row">
                            <td colspan="2">Totais estimados (<?php echo count($servicos); ?> serviço<?php echo count($servicos) > 1 ? 's' : ''; ?>)</td>
                            <td class="total-valor">
                                R$ <?php echo number_format($total_valor, 2, ',', '.'); ?>
                                <span class="total-obs">soma dos valores base</span>
                            </td>
                            <td class="total-prazo">
                                <?php echo $total_prazo; ?> dias
                                <span class="total-obs">prazo acumulado estimado</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>

        <!-- FOTOS -->
        <?php if (!empty($fotos)): ?>
        <div class="card">
            <div class="card-header"><h2 class="card-title">📷 Fotos do Instrumento</h2></div>
            <div class="photos-grid">
                <?php foreach ($fotos as $foto): ?>
                <div class="photo-item">
                    <img src="<?php echo htmlspecialchars($foto['caminho']); ?>" alt="Foto" onclick="window.open(this.src,'_blank')" style="cursor:pointer">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- OBSERVAÇÕES -->
        <?php if (!empty($pedido['observacoes'])): ?>
        <div class="card">
            <div class="card-header"><h2 class="card-title">📝 Observações do Cliente</h2></div>
            <div class="observacoes"><?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></div>
        </div>
        <?php endif; ?>

        <!-- HISTÓRICO DE STATUS -->
        <div class="card">
            <div class="card-header"><h2 class="card-title">🕓 Histórico de Status</h2></div>
            <?php if (empty($historico)): ?>
                <div style="color:#888;font-size:14px;padding:8px 0">Nenhuma alteração registrada ainda.</div>
            <?php else: ?>
                <ul class="timeline">
                <?php
                $icones = [
                    'Pre-OS'=>'🗒️','Em analise'=>'🔍','Orcada'=>'💰',
                    'Aguardando aprovacao'=>'⏳','Aprovada'=>'✅','Reprovada'=>'❌','Cancelada'=>'🚫'
                ];
                foreach ($historico as $h):
                ?>
                <li class="timeline-item">
                    <div class="timeline-dot"><?php echo $icones[$h['status']] ?? '•'; ?></div>
                    <div class="timeline-content">
                        <div class="timeline-status"><?php echo htmlspecialchars($h['status']); ?></div>
                        <div class="timeline-meta">
                            <?php echo date('d/m/Y H:i', strtotime($h['criado_em'])); ?>
                            <?php if (!empty($h['admin_nome'])): ?> &mdash; <?php echo htmlspecialchars($h['admin_nome']); ?><?php endif; ?>
                        </div>
                        <?php if (!empty($h['valor_orcamento'])): ?>
                        <div class="timeline-detalhe valor">💰 Orçamento: R$ <?php echo number_format($h['valor_orcamento'], 2, ',', '.'); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($h['motivo'])): ?>
                        <div class="timeline-detalhe motivo">Motivo: <?php echo htmlspecialchars($h['motivo']); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- TOKEN -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">🔑 Código de Acompanhamento</h2>
                <a href="../../frontend/public/acompanhar.php?token=<?php echo urlencode($pedido['public_token']); ?>" target="_blank" class="btn btn-primary">👁️ Ver página do cliente</a>
            </div>
            <div class="token-box"><?php echo htmlspecialchars($pedido['public_token']); ?></div>
        </div>

    </div>

    <!-- MODAL ORÇAMENTO -->
    <div class="modal-overlay" id="modal-orcamento">
        <div class="modal-box">
            <div class="modal-title">💰 Definir Valor do Orçamento</div>
            <label for="input-valor">Valor total do orçamento (R$)</label>
            <input type="number" id="input-valor" min="0" step="0.01" placeholder="Ex: 350.00">
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="fecharModal('modal-orcamento')">Cancelar</button>
                <button class="btn btn-warning" onclick="confirmarOrcamento()">💰 Confirmar Orçamento</button>
            </div>
        </div>
    </div>

    <!-- MODAL REPROVAÇÃO -->
    <div class="modal-overlay" id="modal-reprovacao">
        <div class="modal-box">
            <div class="modal-title">❌ Motivo da Reprovação</div>
            <label for="input-motivo">Descreva o motivo da reprovação</label>
            <textarea id="input-motivo" placeholder="Ex: Cliente desistiu do serviço..."></textarea>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="fecharModal('modal-reprovacao')">Cancelar</button>
                <button class="btn btn-danger" onclick="confirmarReprovacao()">❌ Confirmar Reprovação</button>
            </div>
        </div>
    </div>

    <script>
    const _pedidoId = <?php echo $preos_id; ?>;
    const _statusLabels = {
        'Pre-OS':'🗒️ Pré-OS','Em analise':'🔍 Em Análise','Orcada':'💰 Orçada',
        'Aguardando aprovacao':'⏳ Aguardando Aprovação','Aprovada':'✅ Aprovada',
        'Reprovada':'❌ Reprovada','Cancelada':'🚫 Cancelada'
    };
    const _statusClasses = {
        'Pre-OS':'badge-new','Em analise':'badge-info','Orcada':'badge-warning',
        'Aguardando aprovacao':'badge-warning','Aprovada':'badge-success',
        'Reprovada':'badge-danger','Cancelada':'badge-dark'
    };

    function _toast(msg, ok) {
        const el = document.createElement('div');
        el.textContent = msg;
        el.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:8px;font-size:14px;z-index:9999;color:#fff;background:' + (ok ? '#2d7a2d' : '#a00');
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }
    function abrirModal(id)  { document.getElementById(id).classList.add('aberto'); }
    function fecharModal(id) { document.getElementById(id).classList.remove('aberto'); }
    function abrirModalOrcamento()  { abrirModal('modal-orcamento');  document.getElementById('input-valor').focus(); }
    function abrirModalReprovacao() { abrirModal('modal-reprovacao'); document.getElementById('input-motivo').focus(); }

    function confirmarOrcamento() {
        const valor = parseFloat(document.getElementById('input-valor').value);
        if (isNaN(valor) || valor <= 0) { _toast('Informe um valor válido', false); return; }
        fecharModal('modal-orcamento');
        _enviar('Orcada', { valor_orcamento: valor });
    }
    function confirmarReprovacao() {
        const motivo = document.getElementById('input-motivo').value.trim();
        if (!motivo) { _toast('Informe o motivo da reprovação', false); return; }
        fecharModal('modal-reprovacao');
        _enviar('Reprovada', { motivo });
    }
    function atualizarStatus(novoStatus) {
        if (!confirm('Alterar status para "' + _statusLabels[novoStatus] + '"?')) return;
        _enviar(novoStatus, {});
    }
    function _enviar(status, extras) {
        fetch('atualizar_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: _pedidoId, status, ...extras })
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                document.getElementById('status-badge').innerHTML =
                    '<span class="badge ' + _statusClasses[status] + '">' + _statusLabels[status] + '</span>';
                const at = document.getElementById('atualizado-em');
                if (at) at.textContent = data.atualizado_em;
                _toast('✅ Status atualizado!', true);
                setTimeout(() => location.reload(), 1500);
            } else {
                _toast('❌ ' + (data.erro || 'Erro desconhecido'), false);
            }
        })
        .catch(() => _toast('❌ Erro de conexão', false));
    }
    document.querySelectorAll('.modal-overlay').forEach(o => {
        o.addEventListener('click', e => { if (e.target === o) o.classList.remove('aberto'); });
    });
    </script>

    <script src="assets/js/admin.js?v=<?php echo $v; ?>"></script>
</body>
</html>
