<?php
/**
 * DETALHES DO PEDIDO - SISTEMA ADONIS
 * Versão: 2.3
 * Data: 27/02/2026
 */

require_once 'auth.php';
require_once '../config/Database.php';

$db = new Database();
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
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            c.email as cliente_email,
            c.endereco as cliente_endereco,
            i.id as instrumento_id,
            i.tipo as instrumento_tipo,
            i.marca as instrumento_marca,
            i.modelo as instrumento_modelo,
            i.referencia as instrumento_referencia,
            i.cor as instrumento_cor,
            i.numero_serie as instrumento_serie
        FROM pre_os p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN instrumentos i ON p.instrumento_id = i.id
        WHERE p.id = :id
        LIMIT 1
    ");
    $stmt->bindParam(':id', $preos_id);
    $stmt->execute();
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        header('Location: dashboard.php?erro=nao_encontrado');
        exit;
    }

    $stmt_servicos = $conn->prepare("
        SELECT s.id, s.nome, s.descricao, s.valor_base, s.prazo_base
        FROM pre_os_servicos ps
        JOIN servicos s ON ps.servico_id = s.id
        WHERE ps.pre_os_id = :pre_os_id
    ");
    $stmt_servicos->bindParam(':pre_os_id', $preos_id);
    $stmt_servicos->execute();
    $servicos = $stmt_servicos->fetchAll(PDO::FETCH_ASSOC);

    $fotos = [];
    if (!empty($pedido['instrumento_id'])) {
        $stmt_fotos = $conn->prepare("
            SELECT caminho, ordem
            FROM instrumento_fotos
            WHERE instrumento_id = :instrumento_id
            ORDER BY ordem ASC
        ");
        $stmt_fotos->bindParam(':instrumento_id', $pedido['instrumento_id']);
        $stmt_fotos->execute();
        $fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log('Erro ao buscar detalhes: ' . $e->getMessage());
    header('Location: dashboard.php?erro=banco');
    exit;
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
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
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

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Status do Pedido</h2>
                    <div id="status-badge"><?php echo formatarStatusDetalhes($pedido['status']); ?></div>
                </div>
                <div class="actions">
                    <button class="btn btn-info"    onclick="atualizarStatus('Em analise')">🔍 Analisar</button>
                    <button class="btn btn-warning" onclick="atualizarStatus('Orcada')">💰 Orçar</button>
                    <button class="btn btn-success" onclick="atualizarStatus('Aprovada')">✅ Aprovar</button>
                    <button class="btn btn-danger"  onclick="atualizarStatus('Reprovada')">❌ Reprovar</button>
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

        <div class="card">
            <div class="card-header"><h2 class="card-title">🔧 Serviços Solicitados</h2></div>
            <?php if (empty($servicos)): ?>
                <div class="empty-state" style="padding:20px;color:#888;">Nenhum serviço selecionado</div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Serviço</th><th>Descrição</th><th>Valor Base</th><th>Prazo</th></tr></thead>
                    <tbody>
                        <?php foreach ($servicos as $s): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s['nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['descricao']); ?></td>
                            <td>R$ <?php echo number_format($s['valor_base'], 2, ',', '.'); ?></td>
                            <td><?php echo $s['prazo_base']; ?> dias</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

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

        <?php if (!empty($pedido['observacoes'])): ?>
        <div class="card">
            <div class="card-header"><h2 class="card-title">📝 Observações do Cliente</h2></div>
            <div class="observacoes"><?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h2 class="card-title">🔑 Código de Acompanhamento</h2></div>
            <div class="token-box"><?php echo htmlspecialchars($pedido['public_token']); ?></div>
        </div>

    </div>

    <!-- JS inline: garante que atualizarStatus esteja sempre disponível independente do cache do admin.js -->
    <script>
    const _pedidoId = <?php echo $preos_id; ?>;

    const _statusLabels = {
        'Pre-OS':               '🗒️ Pré-OS',
        'Em analise':           '🔍 Em Análise',
        'Orcada':               '💰 Orçada',
        'Aguardando aprovacao': '⏳ Aguardando Aprovação',
        'Aprovada':             '✅ Aprovada',
        'Reprovada':            '❌ Reprovada',
        'Cancelada':            '🚫 Cancelada',
    };

    const _statusClasses = {
        'Pre-OS':               'badge-new',
        'Em analise':           'badge-info',
        'Orcada':               'badge-warning',
        'Aguardando aprovacao': 'badge-warning',
        'Aprovada':             'badge-success',
        'Reprovada':            'badge-danger',
        'Cancelada':            'badge-dark',
    };

    function _toast(msg, ok) {
        const el = document.createElement('div');
        el.textContent = msg;
        el.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:8px;font-size:14px;z-index:9999;color:#fff;background:' + (ok ? '#2d7a2d' : '#a00');
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }

    function atualizarStatus(novoStatus) {
        if (!confirm('Alterar status para "' + _statusLabels[novoStatus] + '"?')) return;
        fetch('atualizar_status.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id: _pedidoId, status: novoStatus })
        })
        .then(r => r.json())
        .then(data => {
            if (data.sucesso) {
                document.getElementById('status-badge').innerHTML =
                    '<span class="badge ' + _statusClasses[novoStatus] + '">' + _statusLabels[novoStatus] + '</span>';
                const at = document.getElementById('atualizado-em');
                if (at) at.textContent = data.atualizado_em;
                _toast('✅ Status atualizado!', true);
            } else {
                _toast('❌ ' + (data.erro || 'Erro desconhecido'), false);
            }
        })
        .catch(() => _toast('❌ Erro de conexão', false));
    }
    </script>

    <script src="assets/js/admin.js?v=<?php echo time(); ?>"></script>
</body>
</html>
