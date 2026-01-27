<?php
/**
 * DASHBOARD ADMINISTRATIVO - SISTEMA ADONIS
 * Versão: 1.2
 * Data: 26/01/2026
 */

require_once 'auth.php';
require_once '../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

// Buscar estatísticas
try {
    // Total de pré-OS
    $stmt_total = $conn->query("SELECT COUNT(*) as total FROM preos");
    $total_preos = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pendentes (status: criado, aguardando_analise)
    $stmt_pendentes = $conn->query("
        SELECT COUNT(*) as total 
        FROM preos 
        WHERE status IN ('criado', 'aguardando_analise')
    ");
    $total_pendentes = $stmt_pendentes->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Aprovados (status: aprovado)
    $stmt_aprovados = $conn->query("
        SELECT COUNT(*) as total 
        FROM preos 
        WHERE status = 'aprovado'
    ");
    $total_aprovados = $stmt_aprovados->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Finalizados (status: finalizado)
    $stmt_finalizados = $conn->query("
        SELECT COUNT(*) as total 
        FROM preos 
        WHERE status = 'finalizado'
    ");
    $total_finalizados = $stmt_finalizados->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Listar pré-OS recentes (50 últimos)
    $stmt_lista = $conn->query("
        SELECT 
            p.id,
            p.numero_preos,
            p.status,
            p.public_token,
            p.criado_em,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            c.email as cliente_email,
            i.tipo as instrumento_tipo,
            i.marca as instrumento_marca,
            i.modelo as instrumento_modelo
        FROM preos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN instrumentos i ON p.instrumento_id = i.id
        ORDER BY p.criado_em DESC
        LIMIT 50
    ");
    $lista_preos = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Erro ao buscar dados do dashboard: ' . $e->getMessage());
    $erro = 'Erro ao carregar dados.';
}

// Função para formatar status
function formatarStatus($status) {
    $badges = [
        'criado' => '<span class="badge badge-new">Novo</span>',
        'aguardando_analise' => '<span class="badge badge-warning">Aguardando</span>',
        'em_analise' => '<span class="badge badge-info">Em Análise</span>',
        'aprovado' => '<span class="badge badge-success">Aprovado</span>',
        'reprovado' => '<span class="badge badge-danger">Reprovado</span>',
        'finalizado' => '<span class="badge badge-dark">Finalizado</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
}

// Função para formatar data
function formatarData($data) {
    $timestamp = strtotime($data);
    return date('d/m/Y H:i', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Adonis Custom</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="header-left">
            <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis3.png" alt="Adonis" class="header-logo">
            <h1 class="header-title">Painel Administrativo</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></div>
            </div>
            <a href="logout.php" class="btn-logout">🚪 Sair</a>
        </div>
    </header>
    
    <!-- CONTEÚDO -->
    <div class="container">
        <!-- ESTATÍSTICAS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">📋 Total de Pedidos</div>
                <div class="stat-value"><?php echo $total_preos; ?></div>
            </div>
            
            <div class="stat-card pendente">
                <div class="stat-label">⏳ Pendentes</div>
                <div class="stat-value"><?php echo $total_pendentes; ?></div>
            </div>
            
            <div class="stat-card aprovado">
                <div class="stat-label">✅ Aprovados</div>
                <div class="stat-value"><?php echo $total_aprovados; ?></div>
            </div>
            
            <div class="stat-card finalizado">
                <div class="stat-label">✔️ Finalizados</div>
                <div class="stat-value"><?php echo $total_finalizados; ?></div>
            </div>
        </div>
        
        <!-- TABELA DE PEDIDOS -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">Pedidos Recentes</h2>
            </div>
            
            <?php if (empty($lista_preos)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                    <p>Nenhum pedido encontrado</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Instrumento</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_preos as $preos): ?>
                                <tr>
                                    <td><strong>#<?php echo $preos['id']; ?></strong></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($preos['cliente_nome']); ?></div>
                                        <div class="text-muted"><?php echo htmlspecialchars($preos['cliente_telefone']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($preos['instrumento_tipo']); ?></div>
                                        <div class="text-muted"><?php echo htmlspecialchars($preos['instrumento_marca'] . ' ' . $preos['instrumento_modelo']); ?></div>
                                    </td>
                                    <td><?php echo formatarStatus($preos['status']); ?></td>
                                    <td><?php echo formatarData($preos['criado_em']); ?></td>
                                    <td>
                                        <a href="detalhes.php?id=<?php echo $preos['id']; ?>" class="btn btn-primary">👁️ Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="assets/js/admin.js"></script>
</body>
</html>