<?php
/**
 * DASHBOARD ADMINISTRATIVO - SISTEMA ADONIS
 * Versão: 1.0
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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        /* HEADER */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .header-logo {
            height: 40px;
        }
        
        .header-title {
            font-size: 20px;
            font-weight: 600;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .user-info {
            text-align: right;
            font-size: 14px;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .user-email {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* CONTAINER */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }
        
        /* CARDS DE ESTATÍSTICAS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        
        .stat-card.pendente {
            border-left-color: #ff9800;
        }
        
        .stat-card.aprovado {
            border-left-color: #4caf50;
        }
        
        .stat-card.finalizado {
            border-left-color: #607d8b;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #333;
        }
        
        /* TABELA */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f9f9f9;
        }
        
        th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        tbody tr:hover {
            background: #f9f9f9;
        }
        
        /* BADGES */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-new {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .badge-info {
            background: #e0f7fa;
            color: #00838f;
        }
        
        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-dark {
            background: #eceff1;
            color: #455a64;
        }
        
        /* BOTÕES */
        .btn {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        /* UTILITÁRIOS */
        .text-muted {
            color: #999;
            font-size: 13px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
    </style>
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
</body>
</html>