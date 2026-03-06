<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// Carrega dados do usuário logado
$admin_id = $_SESSION['admin_id'] ?? 0;
try {
    $stmt = $conn->prepare('SELECT id, nome, email, tipo, criado_em FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$admin_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) {
        header('Location: logout.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: logout.php');
    exit;
}

$current_page = 'perfil.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
    <style>
    .profile-header{background:var(--g-surface);border:1px solid var(--g-border);border-radius:12px;padding:24px;margin-bottom:24px;display:flex;align-items:center;gap:20px}
    .profile-avatar{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--g-primary),#0a7ea4);color:white;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:600;flex-shrink:0}
    .profile-info{flex:1}
    .profile-name{font-size:20px;font-weight:600;color:var(--g-text);margin-bottom:4px}
    .profile-email{font-size:14px;color:var(--g-text-2);margin-bottom:8px}
    .profile-meta{display:flex;gap:16px;flex-wrap:wrap}
    .profile-meta-item{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--g-text-3)}
    .form-section{background:var(--g-surface);border:1px solid var(--g-border);border-radius:12px;padding:20px 24px;margin-bottom:16px}
    .form-section-title{font-size:15px;font-weight:600;color:var(--g-text);margin-bottom:16px;display:flex;align-items:center;gap:8px}
    @media (max-width:640px){.profile-header{flex-direction:column;text-align:center}}
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="app-layout">
<?php include '_sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <span class="topbar-title">Meu Perfil</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px">account_circle</span>Meu Perfil
                </h1>
                <div class="page-subtitle">Gerencie suas informações e senha</div>
            </div>
        </div>

        <?php if ($msg): list($tipo, $texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <!-- CARD DE APRESENTAÇÃO -->
        <div class="profile-header">
            <div class="profile-avatar"><?php
                $parts = array_filter(explode(' ', trim($usuario['nome'])));
                echo count($parts)>=2 ? strtoupper(substr($parts[0],0,1).substr(end($parts),0,1)) : strtoupper(substr($parts[0]??'A',0,2));
            ?></div>
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($usuario['nome']); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($usuario['email']); ?></div>
                <div class="profile-meta">
                    <div class="profile-meta-item">
                        <span class="material-symbols-outlined" style="font-size:16px">badge</span>
                        <?php echo ucfirst($usuario['tipo']); ?>
                    </div>
                    <div class="profile-meta-item">
                        <span class="material-symbols-outlined" style="font-size:16px">calendar_today</span>
                        Cadastrado em <?php echo date('d/m/Y', strtotime($usuario['criado_em'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEÇÃO 1: DADOS PESSOAIS -->
        <form method="POST" action="atualizar_perfil.php" id="form-dados">
            <div class="form-section">
                <div class="form-section-title">
                    <span class="material-symbols-outlined" style="color:var(--g-primary)">person</span>
                    Dados Pessoais
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div>
                        <label class="form-label">NOME COMPLETO *</label>
                        <input class="form-input" type="text" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required placeholder="Seu nome completo">
                    </div>
                    <div>
                        <label class="form-label">E-MAIL *</label>
                        <input class="form-input" type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required placeholder="seu@email.com">
                    </div>
                </div>
                <div class="form-hint" style="margin-top:12px">
                    <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;color:var(--g-text-3)">info</span>
                    Essas informações aparecem no cabeçalho do painel e nos logs do sistema
                </div>
                <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:16px">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px">save</span>
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </form>

        <!-- SEÇÃO 2: ALTERAR SENHA -->
        <form method="POST" action="alterar_senha.php" id="form-senha">
            <div class="form-section">
                <div class="form-section-title">
                    <span class="material-symbols-outlined" style="color:var(--g-primary)">lock</span>
                    Alterar Senha
                </div>
                <div style="display:grid;grid-template-columns:1fr;gap:16px;max-width:480px">
                    <div>
                        <label class="form-label">SENHA ATUAL *</label>
                        <input class="form-input" type="password" name="senha_atual" id="senha-atual" required placeholder="Digite sua senha atual">
                    </div>
                    <div>
                        <label class="form-label">NOVA SENHA *</label>
                        <input class="form-input" type="password" name="senha_nova" id="senha-nova" required placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>
                    <div>
                        <label class="form-label">CONFIRMAR NOVA SENHA *</label>
                        <input class="form-input" type="password" name="senha_confirma" id="senha-confirma" required placeholder="Digite a nova senha novamente" minlength="6">
                    </div>
                </div>
                <div class="form-hint" style="margin-top:12px">
                    <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;color:var(--g-text-3)">shield</span>
                    Use uma senha forte com letras, números e símbolos
                </div>
                <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:16px">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('form-senha').reset()">Limpar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px">key</span>
                        Alterar Senha
                    </button>
                </div>
            </div>
        </form>

        <!-- SEÇÃO 3: ATIVIDADE RECENTE -->
        <?php
        try {
            $stmt_logs = $conn->prepare('SELECT tipo_acao, ip, user_agent, criado_em FROM logs_acesso WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT 10');
            $stmt_logs->execute([$admin_id]);
            $logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $logs = []; }
        ?>
        <?php if (!empty($logs)): ?>
        <div class="form-section">
            <div class="form-section-title">
                <span class="material-symbols-outlined" style="color:var(--g-primary)">history</span>
                Atividade Recente
            </div>
            <div style="display:flex;flex-direction:column;gap:10px">
                <?php foreach ($logs as $log): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--g-bg);border:1px solid var(--g-border);border-radius:8px">
                    <span class="material-symbols-outlined" style="font-size:20px;color:var(--g-text-2)">
                        <?php echo $log['tipo_acao'] === 'login' ? 'login' : ($log['tipo_acao'] === 'logout' ? 'logout' : 'verified_user'); ?>
                    </span>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:14px;font-weight:500;color:var(--g-text)">
                            <?php
                            $acao_label = [
                                'login'        => 'Login realizado',
                                'logout'       => 'Logout realizado',
                                'login_falha'  => 'Tentativa de login falha',
                                'atualizar_perfil' => 'Perfil atualizado',
                                'alterar_senha'    => 'Senha alterada',
                            ];
                            echo $acao_label[$log['tipo_acao']] ?? ucfirst($log['tipo_acao']);
                            ?>
                        </div>
                        <div style="font-size:12px;color:var(--g-text-3);margin-top:2px">
                            <?php echo date('d/m/Y \à\s H:i', strtotime($log['criado_em'])); ?> • IP: <?php echo htmlspecialchars($log['ip']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="form-hint" style="margin-top:12px">
                <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;color:var(--g-text-3)">info</span>
                Últimos 10 acessos e ações registrados
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
</div>

<nav class="bottom-nav">
    <a href="dashboard.php">
        <span class="material-symbols-outlined nav-icon">dashboard</span>Painel
    </a>
    <a href="clientes.php">
        <span class="material-symbols-outlined nav-icon">group</span>Clientes
    </a>
    <a href="servicos.php">
        <span class="material-symbols-outlined nav-icon">build</span>Serviços
    </a>
    <a href="logout.php">
        <span class="material-symbols-outlined nav-icon">logout</span>Sair
    </a>
</nav>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
<script>
// Validação de senha antes de enviar
document.getElementById('form-senha').addEventListener('submit', function(e) {
    const nova = document.getElementById('senha-nova').value;
    const confirma = document.getElementById('senha-confirma').value;

    if (nova !== confirma) {
        e.preventDefault();
        alert('As senhas não coincidem. Por favor, digite novamente.');
        document.getElementById('senha-confirma').focus();
        return false;
    }

    if (nova.length < 6) {
        e.preventDefault();
        alert('A nova senha deve ter no mínimo 6 caracteres.');
        document.getElementById('senha-nova').focus();
        return false;
    }
});
</script>
</body>
</html>
