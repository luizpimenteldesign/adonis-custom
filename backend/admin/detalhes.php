<?php
/**
 * DETALHES DO PEDIDO - SISTEMA ADONIS
 * Versão: 1.2
 * Data: 26/01/2026
 */

require_once 'auth.php';
require_once '../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

// Verificar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$preos_id = (int)$_GET['id'];

// Buscar dados completos do pedido
try {
    // Dados principais
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            c.email as cliente_email,
            c.endereco as cliente_endereco,
            i.tipo as instrumento_tipo,
            i.marca as instrumento_marca,
            i.modelo as instrumento_modelo,
            i.referencia as instrumento_referencia,
            i.cor as instrumento_cor,
            i.numero_serie as instrumento_serie
        FROM preos p
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
    
    // Buscar serviços
    $stmt_servicos = $conn->prepare("
        SELECT s.id, s.nome, s.descricao, s.valor_base, s.prazo_base
        FROM preos_servicos ps
        JOIN servicos s ON ps.servico_id = s.id
        WHERE ps.preos_id = :preos_id
    ");
    $stmt_servicos->bindParam(':preos_id', $preos_id);
    $stmt_servicos->execute();
    $servicos = $stmt_servicos->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar fotos
    $stmt_fotos = $conn->prepare("
        SELECT caminho, ordem
        FROM fotos
        WHERE preos_id = :preos_id
        ORDER BY ordem ASC
    ");
    $stmt_fotos->bindParam(':preos_id', $preos_id);
    $stmt_fotos->execute();
    $fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Erro ao buscar detalhes: ' . $e->getMessage());
    header('Location: dashboard.php?erro=banco');
    exit;
}

// Função para formatar status
function formatarStatusDetalhes($status) {
    $badges = [
        'criado' => '<span class="badge badge-new">🆕 Novo</span>',
        'aguardando_analise' => '<span class="badge badge-warning">⏳ Aguardando Análise</span>',
        'em_analise' => '<span class="badge badge-info">🔍 Em Análise</span>',
        'aprovado' => '<span class="badge badge-success">✅ Aprovado</span>',
        'reprovado' => '<span class="badge badge-danger">❌ Reprovado</span>',
        'finalizado' => '<span class="badge badge-dark">✔️ Finalizado</span>'
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
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="header-left">
            <a href="dashboard.php" class="back-button">← Voltar</a>
            <h1 class="header-title">Pedido #<?php echo $pedido['id']; ?></h1>
        </div>
    </header>
    
    <!-- CONTEÚDO -->
    <div class="container">
        
        <!-- STATUS E AÇÕES -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Status do Pedido</h2>
                    <?php echo formatarStatusDetalhes($pedido['status']); ?>
                </div>
                <div class="actions">
                    <button class="btn btn-success">✅ Aprovar</button>
                    <button class="btn btn-danger">❌ Reprovar</button>
                    <button class="btn btn-secondary">✏️ Editar</button>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Data de Criação</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($pedido['criado_em'])); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Última Atualização</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($pedido['atualizado_em'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- DADOS DO CLIENTE -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">👤 Dados do Cliente</h2>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Nome Completo</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['cliente_nome']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Telefone</div>
                    <div class="info-value">
                        <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $pedido['cliente_telefone']); ?>" target="_blank" style="color: #25d366; text-decoration: none;">
                            📞 <?php echo htmlspecialchars($pedido['cliente_telefone']); ?>
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($pedido['cliente_email'])): ?>
                <div class="info-item">
                    <div class="info-label">E-mail</div>
                    <div class="info-value">
                        <a href="mailto:<?php echo htmlspecialchars($pedido['cliente_email']); ?>" style="color: #667eea; text-decoration: none;">
                            📧 <?php echo htmlspecialchars($pedido['cliente_email']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($pedido['cliente_endereco'])): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-label">Endereço</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($pedido['cliente_endereco'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- DADOS DO INSTRUMENTO -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">🎸 Dados do Instrumento</h2>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Tipo</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_tipo']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Marca</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_marca']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Modelo</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_modelo']); ?></div>
                </div>
                
                <?php if (!empty($pedido['instrumento_referencia'])): ?>
                <div class="info-item">
                    <div class="info-label">Referência</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_referencia']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($pedido['instrumento_cor'])): ?>
                <div class="info-item">
                    <div class="info-label">Cor</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_cor']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($pedido['instrumento_serie'])): ?>
                <div class="info-item">
                    <div class="info-label">Número de Série</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['instrumento_serie']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- SERVIÇOS SOLICITADOS -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">🔧 Serviços Solicitados</h2>
            </div>
            
            <?php if (empty($servicos)): ?>
                <div class="empty-state">Nenhum serviço selecionado</div>
            <?php else: ?>
                <table class="services-table">
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Descrição</th>
                            <th>Valor Base</th>
                            <th>Prazo Base</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servicos as $servico): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($servico['nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($servico['descricao']); ?></td>
                                <td>R$ <?php echo number_format($servico['valor_base'], 2, ',', '.'); ?></td>
                                <td><?php echo $servico['prazo_base']; ?> dias</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- FOTOS -->
        <?php if (!empty($fotos)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📷 Fotos do Instrumento</h2>
            </div>
            
            <div class="photos-grid">
                <?php foreach ($fotos as $foto): ?>
                    <div class="photo-item">
                        <img src="<?php echo htmlspecialchars($foto['caminho']); ?>" alt="Foto do instrumento" onclick="window.open(this.src, '_blank')">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- OBSERVAÇÕES -->
        <?php if (!empty($pedido['observacoes'])): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📝 Observações do Cliente</h2>
            </div>
            
            <div class="observacoes">
                <?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- TOKEN PÚBLICO -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">🔑 Código de Acompanhamento</h2>
            </div>
            
            <div class="token-box">
                <?php echo htmlspecialchars($pedido['public_token']); ?>
            </div>
        </div>
    </div>
    
    <script src="assets/js/admin.js"></script>
</body>
</html>