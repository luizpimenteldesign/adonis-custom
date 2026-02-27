<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$host = 'localhost';
$dbname = 'luizpi39_adns_app';
$username = 'luizpi39_adns';
$password = 'a[Ne3KC][3OT';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction();

    // Capturar dados
    $nome = $_POST['cliente_nome'] ?? '';
    $telefone = $_POST['cliente_telefone'] ?? '';
    $email = $_POST['cliente_email'] ?? null;
    $endereco = $_POST['cliente_endereco'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $marca = $_POST['marca'] ?? '';
    $modelo = $_POST['modelo'] ?? '';
    $cor = $_POST['cor'] ?? '';
    $observacoes = $_POST['observacoes'] ?? null;
    $servicos = json_decode($_POST['servicos'] ?? '[]', true);

    // Validar
    if (empty($nome) || empty($telefone) || empty($tipo) || empty($marca) || empty($modelo) || empty($cor)) {
        throw new Exception('Campos obrigatórios não preenchidos');
    }

    // 1. Buscar ou criar cliente
    $stmt = $db->prepare("SELECT id FROM clientes WHERE telefone = :telefone LIMIT 1");
    $stmt->execute([':telefone' => $telefone]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        $cliente_id = $cliente['id'];
        // Atualizar dados do cliente
        $stmt = $db->prepare("UPDATE clientes SET nome = :nome, email = :email, endereco = :endereco WHERE id = :id");
        $stmt->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':endereco' => $endereco,
            ':id' => $cliente_id
        ]);
    } else {
        // Criar novo cliente
        $stmt = $db->prepare("INSERT INTO clientes (nome, telefone, email, endereco) VALUES (:nome, :telefone, :email, :endereco)");
        $stmt->execute([
            ':nome' => $nome,
            ':telefone' => $telefone,
            ':email' => $email,
            ':endereco' => $endereco
        ]);
        $cliente_id = $db->lastInsertId();
    }

    // 2. Criar instrumento
    $stmt = $db->prepare("INSERT INTO instrumentos (cliente_id, tipo, marca, modelo, cor) VALUES (:cliente_id, :tipo, :marca, :modelo, :cor)");
    $stmt->execute([
        ':cliente_id' => $cliente_id,
        ':tipo' => $tipo,
        ':marca' => $marca,
        ':modelo' => $modelo,
        ':cor' => $cor
    ]);
    $instrumento_id = $db->lastInsertId();

    // 3. Criar pre_os
    $public_token = hash('sha256', uniqid($telefone, true));
    $stmt = $db->prepare("INSERT INTO pre_os (cliente_id, instrumento_id, observacoes, public_token, status) VALUES (:cliente_id, :instrumento_id, :observacoes, :public_token, 'Pre-OS')");
    $stmt->execute([
        ':cliente_id' => $cliente_id,
        ':instrumento_id' => $instrumento_id,
        ':observacoes' => $observacoes,
        ':public_token' => $public_token
    ]);
    $preos_id = $db->lastInsertId();

    // 4. Inserir serviços
    if (!empty($servicos) && is_array($servicos)) {
        $stmt = $db->prepare("INSERT INTO pre_os_servicos (pre_os_id, servico_id) VALUES (:pre_os_id, :servico_id)");
        foreach ($servicos as $servico_id) {
            $stmt->execute([
                ':pre_os_id' => $preos_id,
                ':servico_id' => (int)$servico_id
            ]);
        }
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pré-OS criada com sucesso!',
        'data' => [
            'id' => $preos_id,
            'public_token' => $public_token
        ]
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
