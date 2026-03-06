<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php');
    exit;
}

$admin_id = $_SESSION['admin_id'] ?? 0;
$nome     = trim($_POST['nome'] ?? '');
$email    = trim($_POST['email'] ?? '');

if (!$nome || !$email) {
    header('Location: perfil.php?msg=erro:Nome e e-mail são obrigatórios.');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: perfil.php?msg=erro:E-mail inválido.');
    exit;
}

try {
    // Verifica se o e-mail já está em uso por outro usuário
    $stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ? LIMIT 1');
    $stmt->execute([$email, $admin_id]);
    if ($stmt->fetch()) {
        header('Location: perfil.php?msg=erro:Este e-mail já está em uso por outro usuário.');
        exit;
    }

    // Atualiza os dados
    $stmt = $conn->prepare('UPDATE usuarios SET nome = ?, email = ? WHERE id = ?');
    $stmt->execute([$nome, $email, $admin_id]);

    // Atualiza a sessão
    $_SESSION['admin_nome']  = $nome;
    $_SESSION['admin_email'] = $email;

    // Registra no log
    $stmt_log = $conn->prepare('INSERT INTO logs_acesso (usuario_id, ip, user_agent, tipo_acao, detalhes) VALUES (?, ?, ?, ?, ?)');
    $stmt_log->execute([
        $admin_id,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'atualizar_perfil',
        'Nome e e-mail atualizados'
    ]);

    header('Location: perfil.php?msg=sucesso:Dados atualizados com sucesso!');
    exit;

} catch (Exception $e) {
    error_log('Erro ao atualizar perfil: ' . $e->getMessage());
    header('Location: perfil.php?msg=erro:Erro ao atualizar. Tente novamente.');
    exit;
}
