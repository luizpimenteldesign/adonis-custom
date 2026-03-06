<?php
require_once 'auth.php';
require_once '../config/Database.php';

// Apenas admins podem acessar
if (($_SESSION['admin_tipo'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db   = new Database();
$conn = $db->getConnection();

$busca = trim($_GET['q'] ?? '');
$msg   = '';

// AÇÕES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    // CRIAR USUÁRIO
    if ($acao === 'criar') {
        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $tipo  = $_POST['tipo'] ?? 'admin';
        
        if ($nome && $email && $senha) {
            try {
                // Verifica se email já existe
                $check = $conn->prepare('SELECT id FROM usuarios WHERE email = ?');
                $check->execute([$email]);
                if ($check->fetch()) {
                    $msg = 'erro:E-mail já cadastrado';
                } else {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo) VALUES (?, ?, ?, ?, 1)');
                    $stmt->execute([$nome, $email, $hash, $tipo]);
                    $msg = 'sucesso:Usuário criado com sucesso!';
                }
            } catch (Exception $e) {
                $msg = 'erro:' . $e->getMessage();
            }
        }
    }
    
    // EDITAR USUÁRIO
    if ($acao === 'editar') {
        $id    = (int)$_POST['id'];
        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $tipo  = $_POST['tipo'] ?? 'admin';
        
        if ($id && $nome && $email) {
            try {
                // Verifica se email já existe em outro usuário
                $check = $conn->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
                $check->execute([$email, $id]);
                if ($check->fetch()) {
                    $msg = 'erro:E-mail já cadastrado por outro usuário';
                } else {
                    $stmt = $conn->prepare('UPDATE usuarios SET nome = ?, email = ?, tipo = ?, atualizado_em = NOW() WHERE id = ?');
                    $stmt->execute([$nome, $email, $tipo, $id]);
                    $msg = 'sucesso:Usuário atualizado!';
                }
            } catch (Exception $e) {
                $msg = 'erro:' . $e->getMessage();
            }
        }
    }
    
    // ALTERNAR STATUS (ATIVAR/DESATIVAR)
    if ($acao === 'toggle_status') {
        $id = (int)$_POST['id'];
        if ($id && $id !== $_SESSION['admin_id']) {
            try {
                $stmt = $conn->prepare('UPDATE usuarios SET ativo = NOT ativo WHERE id = ?');
                $stmt->execute([$id]);
                $msg = 'sucesso:Status alterado!';
            } catch (Exception $e) {
                $msg = 'erro:' . $e->getMessage();
            }
        } else {
            $msg = 'erro:Você não pode desativar seu próprio usuário';
        }
    }
    
    // EXCLUIR USUÁRIO
    if ($acao === 'excluir') {
        $id = (int)$_POST['id'];
        if ($id && $id !== $_SESSION['admin_id']) {
            try {
                $conn->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
                $msg = 'sucesso:Usuário removido';
            } catch (Exception $e) {
                $msg = 'erro:Erro ao excluir: ' . $e->getMessage();
            }
        } else {
            $msg = 'erro:Você não pode excluir seu próprio usuário';
        }
    }
    
    header('Location: users.php' . ($msg ? '?msg='.urlencode($msg) : ''));
    exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

// BUSCA USUÁRIOS
try {
    $sql = 'SELECT u.*, 
            (SELECT COUNT(*) FROM logs_acesso WHERE usuario_id = u.id) as total_acessos,
            (SELECT MAX(criado_em) FROM logs_acesso WHERE usuario_id = u.id AND tipo_acao = "login") as ultimo_login
            FROM usuarios u'
         . ($busca ? ' WHERE u.nome LIKE :q OR u.email LIKE :q' : '')
         . ' ORDER BY u.ativo DESC, u.nome';
    $stmt = $conn->prepare($sql);
    if ($busca) $stmt->execute([':q' => '%'.$busca.'%']);
    else $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $usuarios = []; }

$current_page = 'users.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
    <style>
    .user-avatar-small{
        width:32px;height:32px;border-radius:50%;object-fit:cover;
        border:2px solid var(--g-border);flex-shrink:0;
    }
    .badge-status{
        padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600;
        text-transform:uppercase;letter-spacing:.3px;
        display:inline-flex;align-items:center;gap:4px;
    }
    .badge-status.ativo{background:#e6f4ea;color:#1e7e34;border:1px solid #c8e6c9}
    .badge-status.inativo{background:#fce8e6;color:#c62828;border:1px solid #ffcdd2}
    .tipo-badge{
        padding:3px 8px;border-radius:10px;font-size:11px;font-weight:600;
        background:var(--g-hover);color:var(--g-text-2);border:1px solid var(--g-border);
    }
    .tipo-badge.admin{background:#e3f2fd;color:#1565c0;border-color:#90caf9}
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
        <span class="topbar-title">Usuários</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px">admin_panel_settings</span>Usuários
                </h1>
                <div class="page-subtitle"><?php echo count($usuarios); ?> usuário<?php echo count($usuarios)!==1?'s':''; ?> cadastrado<?php echo count($usuarios)!==1?'s':''; ?></div>
            </div>
            <button class="btn btn-primary" onclick="abrirModal('criar')">
                <span class="material-symbols-outlined" style="font-size:18px">add</span>
                Novo Usuário
            </button>
        </div>

        <?php if ($msg): list($tipo,$texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <form method="GET" action="users.php" style="margin-bottom:16px">
            <div class="search-bar">
                <span class="search-icon material-symbols-outlined">search</span>
                <input type="text" name="q" placeholder="Buscar por nome ou e-mail..." value="<?php echo htmlspecialchars($busca); ?>" autocomplete="off">
                <?php if ($busca): ?>
                <button type="button" onclick="location.href='users.php'" style="background:none;border:none;cursor:pointer;color:var(--g-text-3);padding:0 4px;display:flex;align-items:center" title="Limpar">
                    <span class="material-symbols-outlined" style="font-size:18px">close</span>
                </button>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($usuarios)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><span class="material-symbols-outlined">admin_panel_settings</span></div>
                <div class="empty-state-title">Nenhum usuário encontrado</div>
                <div class="empty-state-sub"><?php echo $busca ? 'Tente outro termo de busca' : 'Nenhum usuário cadastrado'; ?></div>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Acessos</th>
                        <th class="text-center">Último Login</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <div class="td-with-avatar">
                            <?php if ($u['avatar_url'] && file_exists(__DIR__ . '/' . $u['avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars($u['avatar_url']); ?>?v=<?php echo time(); ?>" class="user-avatar-small" alt="Avatar">
                            <?php else: ?>
                                <div class="mini-avatar"><?php
                                    $parts = array_filter(explode(' ', trim($u['nome']??'')));
                                    echo count($parts)>=2 ? strtoupper(substr($parts[0],0,1).substr(end($parts),0,1)) : strtoupper(substr($parts[0]??'?',0,2));
                                ?></div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($u['nome']); ?></strong>
                                <?php if ($u['id'] == $_SESSION['admin_id']): ?>
                                <span style="font-size:11px;color:var(--g-text-3);margin-left:4px">(você)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td class="text-center">
                        <span class="tipo-badge <?php echo $u['tipo']; ?>"><?php echo ucfirst($u['tipo']); ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge-status <?php echo $u['ativo'] ? 'ativo' : 'inativo'; ?>">
                            <span class="material-symbols-outlined" style="font-size:12px"><?php echo $u['ativo'] ? 'check_circle' : 'cancel'; ?></span>
                            <?php echo $u['ativo'] ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <?php if ($u['total_acessos'] > 0): ?>
                        <button class="badge badge-info" style="border:none;cursor:pointer" onclick='verLogs(<?php echo $u["id"]; ?>, <?php echo htmlspecialchars(json_encode($u["nome"]), ENT_QUOTES); ?>)'>
                            <?php echo $u['total_acessos']; ?> acesso<?php echo $u['total_acessos']!=1?'s':''; ?>
                        </button>
                        <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                    </td>
                    <td class="text-center text-muted" style="font-size:12px">
                        <?php echo $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : '—'; ?>
                    </td>
                    <td class="text-center">
                        <div class="table-actions">
                            <?php if ($u['total_acessos'] > 0): ?>
                            <button class="btn-icon" title="Ver histórico" onclick='verLogs(<?php echo $u["id"]; ?>, <?php echo htmlspecialchars(json_encode($u["nome"]), ENT_QUOTES); ?>)'>
                                <span class="material-symbols-outlined">history</span>
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($u['id'] !== $_SESSION['admin_id']): ?>
                            <button class="btn-icon" title="Editar" onclick='editarUsuario(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES); ?>)'>
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="acao" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn-icon" title="<?php echo $u['ativo'] ? 'Desativar' : 'Ativar'; ?>">
                                    <span class="material-symbols-outlined"><?php echo $u['ativo'] ? 'block' : 'check_circle'; ?></span>
                                </button>
                            </form>
                            
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir usuário <?php echo htmlspecialchars(addslashes($u['nome'])); ?>?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn-icon danger" title="Excluir">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </form>
                            <?php else: ?>
                            <a href="perfil.php" class="btn-icon" title="Editar meu perfil">
                                <span class="material-symbols-outlined">account_circle</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
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

<!-- MODAL: CRIAR/EDITAR USUÁRIO -->
<div class="modal-overlay" id="modal-user">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-drag"></div>
        <div class="modal-title" id="modal-user-titulo">Novo Usuário</div>
        <form method="POST" id="form-user">
            <input type="hidden" name="acao" id="user-acao" value="criar">
            <input type="hidden" name="id" id="user-id" value="">
            
            <label class="form-label">NOME COMPLETO *</label>
            <input class="form-input" type="text" name="nome" id="user-nome" required placeholder="Nome do usuário">
            
            <label class="form-label" style="margin-top:12px">E-MAIL *</label>
            <input class="form-input" type="email" name="email" id="user-email" required placeholder="email@exemplo.com">
            
            <div id="campo-senha">
                <label class="form-label" style="margin-top:12px">SENHA *</label>
                <input class="form-input" type="password" name="senha" id="user-senha" placeholder="Mínimo 6 caracteres" minlength="6">
                <div class="form-hint">Use uma senha forte com letras, números e símbolos</div>
            </div>
            
            <label class="form-label" style="margin-top:12px">TIPO DE USUÁRIO *</label>
            <select class="form-input" name="tipo" id="user-tipo" required>
                <option value="admin">Admin (acesso total)</option>
                <option value="supervisor">Supervisor (sem acesso a usuários)</option>
            </select>
            <div class="form-hint">
                <strong>Admin:</strong> Pode gerenciar tudo, incluindo usuários<br>
                <strong>Supervisor:</strong> Pode gerenciar pedidos, clientes e serviços
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">save</span>
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: HISTÓRICO DE ACESSOS -->
<div class="modal-overlay" id="modal-logs">
    <div class="modal-box" style="max-width:600px">
        <div class="modal-drag"></div>
        <div class="modal-title" id="modal-logs-titulo">Histórico de Acessos</div>
        <div id="modal-logs-body" style="max-height:400px;overflow-y:auto;margin-top:12px"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="fecharModalLogs()">Fechar</button>
        </div>
    </div>
</div>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
<script>
// CRUD USUÁRIOS
function abrirModal(modo) {
    if (modo === 'criar') {
        document.getElementById('modal-user-titulo').textContent = 'Novo Usuário';
        document.getElementById('user-acao').value = 'criar';
        document.getElementById('user-id').value = '';
        document.getElementById('user-nome').value = '';
        document.getElementById('user-email').value = '';
        document.getElementById('user-senha').value = '';
        document.getElementById('user-senha').required = true;
        document.getElementById('user-tipo').value = 'admin';
        document.getElementById('campo-senha').style.display = 'block';
    }
    document.getElementById('modal-user').classList.add('aberto');
}

function editarUsuario(user) {
    document.getElementById('modal-user-titulo').textContent = 'Editar Usuário';
    document.getElementById('user-acao').value = 'editar';
    document.getElementById('user-id').value = user.id;
    document.getElementById('user-nome').value = user.nome;
    document.getElementById('user-email').value = user.email;
    document.getElementById('user-senha').value = '';
    document.getElementById('user-senha').required = false;
    document.getElementById('user-tipo').value = user.tipo;
    document.getElementById('campo-senha').style.display = 'none';
    document.getElementById('modal-user').classList.add('aberto');
}

function fecharModal() {
    document.getElementById('modal-user').classList.remove('aberto');
}

document.getElementById('modal-user').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});

// LOGS DE ACESSO
function verLogs(userId, userName) {
    document.getElementById('modal-logs-titulo').textContent = 'Histórico — ' + userName;
    document.getElementById('modal-logs-body').innerHTML = '<div style="text-align:center;padding:24px;color:var(--g-text-3)">Carregando...</div>';
    document.getElementById('modal-logs').classList.add('aberto');
    
    // Busca logs via AJAX
    fetch('api_user_logs.php?id=' + userId)
        .then(r => r.json())
        .then(data => {
            if (!data || data.length === 0) {
                document.getElementById('modal-logs-body').innerHTML = `
                    <div class="empty-state" style="padding:24px 0">
                        <div class="empty-state-icon"><span class="material-symbols-outlined">history</span></div>
                        <div class="empty-state-title">Nenhum acesso registrado</div>
                    </div>
                `;
                return;
            }
            
            let html = '<div style="display:flex;flex-direction:column;gap:10px">';
            data.forEach(log => {
                const icons = {
                    'login': 'login',
                    'logout': 'logout',
                    'login_falha': 'error',
                    'atualizar_perfil': 'edit',
                    'alterar_senha': 'key'
                };
                const labels = {
                    'login': 'Login realizado',
                    'logout': 'Logout realizado',
                    'login_falha': 'Tentativa de login falha',
                    'atualizar_perfil': 'Perfil atualizado',
                    'alterar_senha': 'Senha alterada'
                };
                const icon = icons[log.tipo_acao] || 'verified_user';
                const label = labels[log.tipo_acao] || log.tipo_acao;
                
                html += `
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--g-bg);border:1px solid var(--g-border);border-radius:8px">
                        <span class="material-symbols-outlined" style="font-size:20px;color:var(--g-text-2)">${icon}</span>
                        <div style="flex:1">
                            <div style="font-size:14px;font-weight:500;color:var(--g-text)">${label}</div>
                            <div style="font-size:12px;color:var(--g-text-3);margin-top:2px">
                                ${log.criado_em_formatado} • IP: ${log.ip}
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            document.getElementById('modal-logs-body').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('modal-logs-body').innerHTML = '<div style="text-align:center;padding:24px;color:var(--g-red)">Erro ao carregar logs</div>';
        });
}

function fecharModalLogs() {
    document.getElementById('modal-logs').classList.remove('aberto');
}

document.getElementById('modal-logs').addEventListener('click', function(e) {
    if (e.target === this) fecharModalLogs();
});
</script>
</body>
</html>
