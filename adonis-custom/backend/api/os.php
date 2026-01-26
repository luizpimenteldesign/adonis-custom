<?php
require_once '../config/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Erro de conexão']));
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        listarPreOS($pdo);
    } elseif ($method === 'POST') {
        gerarOS($pdo);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function listarPreOS($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                p.id,
                p.public_token,
                p.status,
                p.observacoes,
                p.criado_em,
                c.nome as cliente_nome,
                c.telefone as cliente_telefone,
                c.email as cliente_email,
                i.tipo,
                i.marca,
                i.modelo,
                i.cor
            FROM pre_os p
            INNER JOIN clientes c ON p.cliente_id = c.id
            INNER JOIN instrumentos i ON p.instrumento_id = i.id
            WHERE p.status IN ('Pre-OS', 'Em analise')
            ORDER BY p.criado_em DESC
            LIMIT 50
        ");
        
        $preos = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'message' => 'Pré-OS listadas com sucesso',
            'total' => count($preos),
            'data' => $preos
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Erro ao listar: ' . $e->getMessage());
    }
}

function gerarOS($pdo) {
    echo json_encode([
        'success' => false,
        'message' => 'Função gerarOS será implementada'
    ]);
}
?>
