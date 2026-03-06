<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: novo-pedido.php');
    exit;
}

$cliente_id      = (int)($_POST['cliente_id'] ?? 0);
$instrumento_id  = (int)($_POST['instrumento_id'] ?? 0);
$servicos_ids    = $_POST['servicos'] ?? [];
$observacoes     = trim($_POST['observacoes'] ?? '');
$status_inicial  = trim($_POST['status_inicial'] ?? 'Pre-OS');
$prazo_estimado  = ($_POST['prazo_estimado'] ?? '') !== '' ? (int)$_POST['prazo_estimado'] : null;

// Validações
if (!$cliente_id || !$instrumento_id || empty($servicos_ids)) {
    header('Location: novo-pedido.php?msg=erro:Cliente, instrumento e serviços são obrigatórios.');
    exit;
}

// Gera public_token único
function gerarPublicToken($conn) {
    do {
        $token = bin2hex(random_bytes(16));
        $stmt = $conn->prepare('SELECT id FROM pre_os WHERE public_token = ? LIMIT 1');
        $stmt->execute([$token]);
    } while ($stmt->fetch());
    return $token;
}

$public_token = gerarPublicToken($conn);

try {
    $conn->beginTransaction();

    // Insere a Pré-OS
    $stmt = $conn->prepare(
        'INSERT INTO pre_os (cliente_id, instrumento_id, observacoes, status, public_token, prazo_estimado_dias, criado_em, atualizado_em)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([$cliente_id, $instrumento_id, $observacoes, $status_inicial, $public_token, $prazo_estimado]);
    $preos_id = $conn->lastInsertId();

    // Insere os serviços
    $stmt_srv = $conn->prepare('INSERT INTO pre_os_servicos (preos_id, servico_id) VALUES (?, ?)');
    foreach ($servicos_ids as $srv_id) {
        $stmt_srv->execute([$preos_id, (int)$srv_id]);
    }

    // Registra no histórico
    $admin_user = $_SESSION['admin_user'] ?? 'admin';
    $stmt_hist = $conn->prepare(
        'INSERT INTO status_historico (preos_id, status, alterado_por, alterado_em)
         VALUES (?, ?, ?, NOW())'
    );
    $stmt_hist->execute([$preos_id, $status_inicial, $admin_user]);

    $conn->commit();

    header('Location: detalhes.php?id=' . $preos_id . '&msg=sucesso:Ordem de Serviço #' . $preos_id . ' criada com sucesso!');
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    error_log('Erro ao criar pedido manual: ' . $e->getMessage());
    header('Location: novo-pedido.php?msg=erro:Erro ao criar pedido. Tente novamente.');
    exit;
}
