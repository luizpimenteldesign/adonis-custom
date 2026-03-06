<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php');
    exit;
}

$admin_id       = $_SESSION['admin_id'] ?? 0;
$senha_atual    = trim($_POST['senha_atual'] ?? '');
$senha_nova     = trim($_POST['senha_nova'] ?? '');
$senha_confirma = trim($_POST['senha_confirma'] ?? '');

if (!$senha_atual || !$senha_nova || !$senha_confirma) {
    header('Location: perfil.php?msg=erro:Todos os campos de senha são obrigatórios.');
    exit;
}

if ($senha_nova !== $senha_confirma) {
    header('Location: perfil.php?msg=erro:As senhas não coincidem.');
    exit;
}

if (strlen($senha_nova) < 6) {
    header('Location: perfil.php?msg=erro:A nova senha deve ter no mínimo 6 caracteres.');
    exit;
}

try {
    // Busca a senha atual do banco
    $stmt = $conn->prepare('SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$admin_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        header('Location: logout.php');
        exit;
    }

    // Verifica se a senha atual está correta
    if (!password_verify($senha_atual, $usuario['senha_hash'])) {
        header('Location: perfil.php?msg=erro:Senha atual incorreta.');
        exit;
    }

    // Gera o hash da nova senha
    $novo_hash = password_hash($senha_nova, PASSWORD_DEFAULT);

    // Atualiza no banco
    $stmt = $conn->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
    $stmt->execute([$novo_hash, $admin_id]);

    // Registra no log
    $stmt_log = $conn->prepare('INSERT INTO logs_acesso (usuario_id, ip, user_agent, tipo_acao, detalhes) VALUES (?, ?, ?, ?, ?)');
    $stmt_log->execute([
        $admin_id,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'alterar_senha',
        'Senha alterada com sucesso'
    ]);

    header('Location: perfil.php?msg=sucesso:Senha alterada com sucesso!');
    exit;

} catch (Exception $e) {
    error_log('Erro ao alterar senha: ' . $e->getMessage());
    header('Location: perfil.php?msg=erro:Erro ao alterar senha. Tente novamente.');
    exit;
}
