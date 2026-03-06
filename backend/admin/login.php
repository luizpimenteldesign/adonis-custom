<?php
/**
 * LOGIN ADMINISTRATIVO - SISTEMA ADONIS
 * Versão: 1.6
 */

session_start();

if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/Database.php';

    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        try {
            $db   = new Database();
            $conn = $db->getConnection();

            $stmt = $conn->prepare("
                SELECT id, nome, email, senha_hash, tipo, ativo
                FROM usuarios
                WHERE email = :email AND ativo = 1
                LIMIT 1
            ");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
                $_SESSION['admin_logado']     = true;
                $_SESSION['admin_id']         = $usuario['id'];
                $_SESSION['admin_nome']        = $usuario['nome'];
                $_SESSION['admin_email']       = $usuario['email'];
                $_SESSION['admin_tipo']        = $usuario['tipo'];
                $_SESSION['login_timestamp']   = time();

                $stmt_log = $conn->prepare("
                    INSERT INTO logs_acesso (usuario_id, ip, user_agent, tipo_acao)
                    VALUES (:usuario_id, :ip, :user_agent, 'login')
                ");
                $stmt_log->execute([
                    ':usuario_id' => $usuario['id'],
                    ':ip'         => $_SERVER['REMOTE_ADDR']    ?? 'unknown',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ]);

                header('Location: dashboard.php');
                exit;
            } else {
                $erro = 'E-mail ou senha inválidos.';

                $stmt_log = $conn->prepare("
                    INSERT INTO logs_acesso (usuario_id, ip, user_agent, tipo_acao, detalhes)
                    VALUES (NULL, :ip, :user_agent, 'login_falha', :email)
                ");
                $stmt_log->execute([
                    ':ip'         => $_SERVER['REMOTE_ADDR']    ?? 'unknown',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    ':email'      => $email,
                ]);
            }
        } catch (PDOException $e) {
            error_log('Erro no login: ' . $e->getMessage());
            $erro = 'Erro ao processar login. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Adonis Admin</title>
    <link rel="icon" type="image/png" href="/frontend/public/assets/img/favicon.png">
    <link rel="shortcut icon" type="image/png" href="/frontend/public/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/backend/admin/assets/css/admin.css">
</head>
<body>

<div class="login-page">
    <div class="login-card">

        <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis2.png"
             alt="Adonis Custom" class="login-logo">

        <div class="login-title">Área Administrativa</div>
        <div class="login-subtitle">Faça login para acessar o painel</div>

        <?php if (!empty($erro)): ?>
        <div class="login-error"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="email"
                   name="email"
                   id="email"
                   placeholder="E-mail"
                   required
                   autofocus
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

            <input type="password"
                   name="senha"
                   id="senha"
                   placeholder="Senha"
                   required>

            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>

        <div style="margin-top:24px;font-size:13px;color:var(--g-text-3)">
            <a href="../../frontend/index.php" style="color:var(--g-text-3)">← Voltar para o site</a>
        </div>

    </div>
</div>

</body>
</html>
