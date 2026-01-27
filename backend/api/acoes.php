<?php
/**
 * API DE AÇÕES - SISTEMA ADONIS
 * Aprovar, reprovar e gerenciar pedidos
 * Versão: 1.0
 * Data: 26/01/2026
 */

// Headers CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tratar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Iniciar sessão
session_start();

// Verificar autenticação
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Não autorizado. Faça login primeiro.'
    ]);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit;
}

// Incluir Database
require_once '../config/Database.php';

// Obter ação da URL
$action = $_GET['action'] ?? '';
$pedido_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validar ID
if ($pedido_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID do pedido inválido.'
    ]);
    exit;
}

// Conectar banco
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao conectar ao banco de dados.'
    ]);
    error_log('Erro DB: ' . $e->getMessage());
    exit;
}

// ========================================
// FUNÇÃO: APROVAR PEDIDO
// ========================================
function aprovarPedido($conn, $pedido_id, $admin_id) {
    try {
        // Verificar se pedido existe
        $stmt = $conn->prepare("SELECT id, status FROM preos WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $pedido_id);
        $stmt->execute();
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            return [
                'success' => false,
                'message' => 'Pedido não encontrado.'
            ];
        }
        
        // Verificar se já está aprovado
        if ($pedido['status'] === 'aprovado') {
            return [
                'success' => false,
                'message' => 'Pedido já está aprovado.'
            ];
        }
        
        // Atualizar status
        $stmt_update = $conn->prepare("
            UPDATE preos 
            SET status = 'aprovado', 
                atualizado_em = NOW() 
            WHERE id = :id
        ");
        $stmt_update->bindParam(':id', $pedido_id);
        $stmt_update->execute();
        
        // Registrar no histórico
        $stmt_historico = $conn->prepare("
            INSERT INTO preos_historico 
                (preos_id, status_anterior, status_novo, usuario_id, observacao) 
            VALUES 
                (:preos_id, :status_anterior, 'aprovado', :usuario_id, 'Pedido aprovado pelo administrador')
        ");
        $stmt_historico->execute([
            ':preos_id' => $pedido_id,
            ':status_anterior' => $pedido['status'],
            ':usuario_id' => $admin_id
        ]);
        
        return [
            'success' => true,
            'message' => 'Pedido aprovado com sucesso!',
            'pedido_id' => $pedido_id,
            'novo_status' => 'aprovado'
        ];
        
    } catch (PDOException $e) {
        error_log('Erro ao aprovar pedido: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao processar aprovação.'
        ];
    }
}

// ========================================
// FUNÇÃO: REPROVAR PEDIDO
// ========================================
function reprovarPedido($conn, $pedido_id, $admin_id, $motivo) {
    try {
        // Validar motivo
        if (empty(trim($motivo))) {
            return [
                'success' => false,
                'message' => 'Motivo da reprovação é obrigatório.'
            ];
        }
        
        // Verificar se pedido existe
        $stmt = $conn->prepare("SELECT id, status FROM preos WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $pedido_id);
        $stmt->execute();
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            return [
                'success' => false,
                'message' => 'Pedido não encontrado.'
            ];
        }
        
        // Verificar se já está reprovado
        if ($pedido['status'] === 'reprovado') {
            return [
                'success' => false,
                'message' => 'Pedido já está reprovado.'
            ];
        }
        
        // Atualizar status
        $stmt_update = $conn->prepare("
            UPDATE preos 
            SET status = 'reprovado', 
                observacoes = CONCAT(COALESCE(observacoes, ''), '\n\n--- REPROVADO ---\nMotivo: ', :motivo),
                atualizado_em = NOW() 
            WHERE id = :id
        ");
        $stmt_update->execute([
            ':id' => $pedido_id,
            ':motivo' => trim($motivo)
        ]);
        
        // Registrar no histórico
        $stmt_historico = $conn->prepare("
            INSERT INTO preos_historico 
                (preos_id, status_anterior, status_novo, usuario_id, observacao) 
            VALUES 
                (:preos_id, :status_anterior, 'reprovado', :usuario_id, :observacao)
        ");
        $stmt_historico->execute([
            ':preos_id' => $pedido_id,
            ':status_anterior' => $pedido['status'],
            ':usuario_id' => $admin_id,
            ':observacao' => 'Pedido reprovado. Motivo: ' . trim($motivo)
        ]);
        
        return [
            'success' => true,
            'message' => 'Pedido reprovado com sucesso!',
            'pedido_id' => $pedido_id,
            'novo_status' => 'reprovado',
            'motivo' => trim($motivo)
        ];
        
    } catch (PDOException $e) {
        error_log('Erro ao reprovar pedido: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao processar reprovação.'
        ];
    }
}

// ========================================
// ROTEAMENTO DE AÇÕES
// ========================================

$admin_id = $_SESSION['admin_id'] ?? null;

// Obter dados do POST
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

switch ($action) {
    
    case 'aprovar':
        $resultado = aprovarPedido($conn, $pedido_id, $admin_id);
        echo json_encode($resultado);
        break;
    
    case 'reprovar':
        $motivo = $data['motivo'] ?? '';
        $resultado = reprovarPedido($conn, $pedido_id, $admin_id, $motivo);
        echo json_encode($resultado);
        break;
    
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Ação inválida. Use: aprovar ou reprovar'
        ]);
        break;
}

exit;
