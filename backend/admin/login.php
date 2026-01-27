<?php
/**
 * LOGIN ADMINISTRATIVO - SISTEMA ADONIS
 * Versão: 1.0
 * Data: 26/01/2026
 */

session_start();

// Se já estiver logado, redireciona para dashboard
if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Processar login
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/Database.php';
    
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    
    // Validações básicas
    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Buscar usuário por e-mail
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
                // Login bem-sucedido
                $_SESSION['admin_logado'] = true;
                $_SESSION['admin_id'] = $usuario['id'];
                $_SESSION['admin_nome'] = $usuario['nome'];
                $_SESSION['admin_email'] = $usuario['email'];
                $_SESSION['admin_tipo'] = $usuario['tipo'];
                $_SESSION['login_timestamp'] = time();
                
                // Registrar log de acesso
                $stmt_log = $conn->prepare("
                    INSERT INTO logs_acesso (usuario_id, ip, user_agent, tipo_acao) 
                    VALUES (:usuario_id, :ip, :user_agent, 'login')
                ");
                $stmt_log->execute([
                    ':usuario_id' => $usuario['id'],
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $erro = 'E-mail ou senha inválidos.';
                
                // Log de tentativa falha
                $stmt_log = $conn->prepare("
                    INSERT INTO logs_acesso (usuario_id, ip, user_agent, tipo_acao, detalhes) 
                    VALUES (NULL, :ip, :user_agent, 'login_falha', :email)
                ");
                $stmt_log->execute([
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    ':email' => $email
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
    <title>Login Admin - Adonis Custom</title>
    <link rel="stylesheet" href="../../frontend/public/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Roboto', sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 48px;
            width: 100%;
            max-width: 420px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .login-logo {
            width: 120px;
            margin-bottom: 16px;
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .login-subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 8px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #c62828;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #999;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="https://adns.luizpimentel.com/adonis-custom/frontend/public/assets/img/Logo-Adonis3.png" alt="Adonis Custom" class="login-logo">
            <h1 class="login-title">Área Administrativa</h1>
            <p class="login-subtitle">Faça login para acessar o painel</p>
        </div>
        
        <?php if (!empty($erro)): ?>
            <div class="alert-error">
                <strong>❌ Erro:</strong> <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" name="email" id="email" required autofocus 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" name="senha" id="senha" required>
            </div>
            
            <button type="submit" class="btn-login">
                🔐 Entrar
            </button>
        </form>
        
        <div class="login-footer">
            <p>© 2026 Adonis Custom Luthieria</p>
            <p><a href="../../frontend/index.php">← Voltar para o site</a></p>
        </div>
    </div>
</body>
</html>