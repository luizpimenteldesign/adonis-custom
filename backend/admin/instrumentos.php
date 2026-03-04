<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

// Garante tabelas do catálogo
$conn->exec("CREATE TABLE IF NOT EXISTS cat_tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    icone VARCHAR(10) DEFAULT '🎸',
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4");

$conn->exec("CREATE TABLE IF NOT EXISTS cat_marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4");

$conn->exec("CREATE TABLE IF NOT EXISTS cat_modelos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    marca_id INT DEFAULT NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (marca_id) REFERENCES cat_marcas(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4");

$conn->exec("CREATE TABLE IF NOT EXISTS cat_cores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4");

$msg = '';
$aba = $_GET['aba'] ?? 'tipos';
$abas_validas = ['tipos','marcas','modelos','cores'];
if (!in_array($aba, $abas_validas)) $aba = 'tipos';

// ── POST: CRUD ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao    = $_POST['acao']    ?? '';
    $tabela  = $_POST['tabela']  ?? '';
    $tabelas_ok = ['cat_tipos','cat_marcas','cat_modelos','cat_cores'];
    $aba_map = ['cat_tipos'=>'tipos','cat_marcas'=>'marcas','cat_modelos'=>'modelos','cat_cores'=>'cores'];

    if (!in_array($tabela, $tabelas_ok)) {
        header('Location: instrumentos.php?aba='.$aba); exit;
    }
    $aba_redir = $aba_map[$tabela] ?? 'tipos';

    if ($acao === 'criar' || $acao === 'editar') {
        $nome  = trim($_POST['nome'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if (!$nome) { header('Location: instrumentos.php?aba='.$aba_redir.'&msg=erro:Nome obrigatório'); exit; }

        if ($tabela === 'cat_tipos') {
            $icone = trim($_POST['icone'] ?? '🎸') ?: '🎸';
            if ($acao === 'criar') {
                try {
                    $conn->prepare("INSERT INTO cat_tipos (nome, icone, ativo) VALUES (?,?,?)")->execute([$nome,$icone,$ativo]);
                    $msg = 'sucesso:Tipo criado!';
                } catch(Exception $e) { $msg = 'erro:Já existe um tipo com esse nome.'; }
            } else {
                $id = (int)$_POST['id'];
                $conn->prepare("UPDATE cat_tipos SET nome=?, icone=?, ativo=? WHERE id=?")->execute([$nome,$icone,$ativo,$id]);
                $msg = 'sucesso:Tipo atualizado!';
            }

        } elseif ($tabela === 'cat_marcas') {
            if ($acao === 'criar') {
                try {
                    $conn->prepare("INSERT INTO cat_marcas (nome, ativo) VALUES (?,?)")->execute([$nome,$ativo]);
                    $msg = 'sucesso:Marca criada!';
                } catch(Exception $e) { $msg = 'erro:Já existe uma marca com esse nome.'; }
            } else {
                $id = (int)$_POST['id'];
                $conn->prepare("UPDATE cat_marcas SET nome=?, ativo=? WHERE id=?")->execute([$nome,$ativo,$id]);
                $msg = 'sucesso:Marca atualizada!';
            }

        } elseif ($tabela === 'cat_modelos') {
            $marca_id = $_POST['marca_id'] ? (int)$_POST['marca_id'] : null;
            if ($acao === 'criar') {
                $conn->prepare("INSERT INTO cat_modelos (nome, marca_id, ativo) VALUES (?,?,?)")->execute([$nome,$marca_id,$ativo]);
                $msg = 'sucesso:Modelo criado!';
            } else {
                $id = (int)$_POST['id'];
                $conn->prepare("UPDATE cat_modelos SET nome=?, marca_id=?, ativo=? WHERE id=?")->execute([$nome,$marca_id,$ativo,$id]);
                $msg = 'sucesso:Modelo atualizado!';
            }

        } elseif ($tabela === 'cat_cores') {
            if ($acao === 'criar') {
                try {
                    $conn->prepare("INSERT INTO cat_cores (nome, ativo) VALUES (?,?)")->execute([$nome,$ativo]);
                    $msg = 'sucesso:Cor criada!';
                } catch(Exception $e) { $msg = 'erro:Já existe uma cor com esse nome.'; }
            } else {
                $id = (int)$_POST['id'];
                $conn->prepare("UPDATE cat_cores SET nome=?, ativo=? WHERE id=?")->execute([$nome,$ativo,$id]);
                $msg = 'sucesso:Cor atualizada!';
            }
        }

    } elseif ($acao === 'excluir') {
        $id = (int)$_POST['id'];
        try {
            $conn->prepare("DELETE FROM $tabela WHERE id=?")->execute([$id]);
            $msg = 'sucesso:Item removido.';
        } catch(Exception $e) { $msg = 'erro:Não é possível excluir — item em uso.'; }
    }

    header('Location: instrumentos.php?aba='.$aba_redir.'&msg='.urlencode($msg)); exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

// ── Carregar dados da aba ───────────────────────────────────────────────────
$tipos   = $conn->query("SELECT * FROM cat_tipos   ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$marcas  = $conn->query("SELECT * FROM cat_marcas  ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$modelos = $conn->query("SELECT m.*, b.nome as marca_nome FROM cat_modelos m LEFT JOIN cat_marcas b ON m.marca_id=b.id ORDER BY b.nome, m.nome")->fetchAll(PDO::FETCH_ASSOC);
$cores   = $conn->query("SELECT * FROM cat_cores   ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'instrumentos.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
    <style>
    .tab-bar{display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--g-border);padding-bottom:0}
    .tab-btn{padding:9px 18px;font-size:13.5px;font-weight:500;color:var(--g-text-2);cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-2px;border-radius:6px 6px 0 0;transition:color .15s,border-color .15s;font-family:inherit}
    .tab-btn:hover{color:var(--g-text);background:var(--g-hover)}
    .tab-btn.active{color:var(--g-blue);border-bottom-color:var(--g-blue)}
    .tab-panel{display:none}.tab-panel.active{display:block}
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="app-layout">
<?php include '_sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()">☰</button>
        <span class="topbar-title">Catálogo</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">🗂️ Catálogo de Instrumentos</h1>
                <div class="page-subtitle">Gerencie tipos, marcas, modelos e cores disponíveis no formulário</div>
            </div>
            <button class="btn btn-primary" id="btn-novo" onclick="abrirModalNovo()">+ Novo Item</button>
        </div>

        <?php if ($msg): list($tipo_msg,$texto_msg) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo_msg; ?>"><?php echo htmlspecialchars($texto_msg); ?></div>
        <?php endif; ?>

        <!-- ABAS -->
        <div class="tab-bar">
            <button class="tab-btn <?php echo $aba==='tipos'   ? 'active' : ''; ?>" onclick="mudarAba('tipos')">🎸 Tipos</button>
            <button class="tab-btn <?php echo $aba==='marcas'  ? 'active' : ''; ?>" onclick="mudarAba('marcas')">🏷️ Marcas</button>
            <button class="tab-btn <?php echo $aba==='modelos' ? 'active' : ''; ?>" onclick="mudarAba('modelos')">📋 Modelos</button>
            <button class="tab-btn <?php echo $aba==='cores'   ? 'active' : ''; ?>" onclick="mudarAba('cores')">🎨 Cores</button>
        </div>

        <!-- ABA: TIPOS -->
        <div class="tab-panel <?php echo $aba==='tipos' ? 'active' : ''; ?>" id="tab-tipos">
            <div class="table-wrap">
                <?php if (empty($tipos)): ?>
                <div class="empty-state"><div class="empty-state-icon">🎸</div><div class="empty-state-title">Nenhum tipo cadastrado</div><div class="empty-state-sub">Clique em "+ Novo Item" para adicionar</div></div>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Ícone</th><th>Nome</th><th class="text-center">Status</th><th class="text-center">Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ($tipos as $item): ?>
                    <tr class="<?php echo !$item['ativo'] ? 'row-inactive' : ''; ?>">
                        <td style="font-size:22px;text-align:center"><?php echo htmlspecialchars($item['icone']); ?></td>
                        <td><strong><?php echo htmlspecialchars($item['nome']); ?></strong></td>
                        <td class="text-center"><?php echo $item['ativo'] ? '<span class="badge badge-success">✅ Ativo</span>' : '<span class="badge badge-dark">⛔ Inativo</span>'; ?></td>
                        <td class="text-center">
                            <div class="table-actions">
                                <button class="btn-icon" onclick='editarItem("cat_tipos",<?php echo $item["id"]; ?>,<?php echo htmlspecialchars(json_encode(["nome"=>$item["nome"],"icone"=>$item["icone"],"ativo"=>$item["ativo"]]),ENT_QUOTES); ?>)' title="Editar">✏️</button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir tipo <?php echo htmlspecialchars(addslashes($item['nome'])); ?>?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="tabela" value="cat_tipos">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn-icon danger" title="Excluir">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ABA: MARCAS -->
        <div class="tab-panel <?php echo $aba==='marcas' ? 'active' : ''; ?>" id="tab-marcas">
            <div class="table-wrap">
                <?php if (empty($marcas)): ?>
                <div class="empty-state"><div class="empty-state-icon">🏷️</div><div class="empty-state-title">Nenhuma marca cadastrada</div><div class="empty-state-sub">Clique em "+ Novo Item" para adicionar</div></div>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Nome</th><th class="text-center">Status</th><th class="text-center">Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ($marcas as $item): ?>
                    <tr class="<?php echo !$item['ativo'] ? 'row-inactive' : ''; ?>">
                        <td><strong><?php echo htmlspecialchars($item['nome']); ?></strong></td>
                        <td class="text-center"><?php echo $item['ativo'] ? '<span class="badge badge-success">✅ Ativo</span>' : '<span class="badge badge-dark">⛔ Inativo</span>'; ?></td>
                        <td class="text-center">
                            <div class="table-actions">
                                <button class="btn-icon" onclick='editarItem("cat_marcas",<?php echo $item["id"]; ?>,<?php echo htmlspecialchars(json_encode(["nome"=>$item["nome"],"ativo"=>$item["ativo"]]),ENT_QUOTES); ?>)' title="Editar">✏️</button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir marca <?php echo htmlspecialchars(addslashes($item['nome'])); ?>?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="tabela" value="cat_marcas">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn-icon danger" title="Excluir">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ABA: MODELOS -->
        <div class="tab-panel <?php echo $aba==='modelos' ? 'active' : ''; ?>" id="tab-modelos">
            <div class="table-wrap">
                <?php if (empty($modelos)): ?>
                <div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">Nenhum modelo cadastrado</div><div class="empty-state-sub">Clique em "+ Novo Item" para adicionar</div></div>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Nome</th><th>Marca</th><th class="text-center">Status</th><th class="text-center">Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ($modelos as $item): ?>
                    <tr class="<?php echo !$item['ativo'] ? 'row-inactive' : ''; ?>">
                        <td><strong><?php echo htmlspecialchars($item['nome']); ?></strong></td>
                        <td><?php echo $item['marca_nome'] ? htmlspecialchars($item['marca_nome']) : '<span class="text-muted">—</span>'; ?></td>
                        <td class="text-center"><?php echo $item['ativo'] ? '<span class="badge badge-success">✅ Ativo</span>' : '<span class="badge badge-dark">⛔ Inativo</span>'; ?></td>
                        <td class="text-center">
                            <div class="table-actions">
                                <button class="btn-icon" onclick='editarItem("cat_modelos",<?php echo $item["id"]; ?>,<?php echo htmlspecialchars(json_encode(["nome"=>$item["nome"],"marca_id"=>$item["marca_id"],"ativo"=>$item["ativo"]]),ENT_QUOTES); ?>)' title="Editar">✏️</button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir modelo <?php echo htmlspecialchars(addslashes($item['nome'])); ?>?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="tabela" value="cat_modelos">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn-icon danger" title="Excluir">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ABA: CORES -->
        <div class="tab-panel <?php echo $aba==='cores' ? 'active' : ''; ?>" id="tab-cores">
            <div class="table-wrap">
                <?php if (empty($cores)): ?>
                <div class="empty-state"><div class="empty-state-icon">🎨</div><div class="empty-state-title">Nenhuma cor cadastrada</div><div class="empty-state-sub">Clique em "+ Novo Item" para adicionar</div></div>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Nome</th><th class="text-center">Status</th><th class="text-center">Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ($cores as $item): ?>
                    <tr class="<?php echo !$item['ativo'] ? 'row-inactive' : ''; ?>">
                        <td><strong><?php echo htmlspecialchars($item['nome']); ?></strong></td>
                        <td class="text-center"><?php echo $item['ativo'] ? '<span class="badge badge-success">✅ Ativo</span>' : '<span class="badge badge-dark">⛔ Inativo</span>'; ?></td>
                        <td class="text-center">
                            <div class="table-actions">
                                <button class="btn-icon" onclick='editarItem("cat_cores",<?php echo $item["id"]; ?>,<?php echo htmlspecialchars(json_encode(["nome"=>$item["nome"],"ativo"=>$item["ativo"]]),ENT_QUOTES); ?>)' title="Editar">✏️</button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir cor <?php echo htmlspecialchars(addslashes($item['nome'])); ?>?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="tabela" value="cat_cores">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn-icon danger" title="Excluir">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>
</div>

<nav class="bottom-nav">
    <a href="dashboard.php"><span>🏠</span>Painel</a>
    <a href="clientes.php"><span>👥</span>Clientes</a>
    <a href="servicos.php"><span>🔧</span>Serviços</a>
    <a href="logout.php"><span>🚪</span>Sair</a>
</nav>

<!-- MODAL CRIAR / EDITAR -->
<div class="modal-overlay" id="modal-catalogo">
    <div class="modal-box">
        <div class="modal-drag"></div>
        <div class="modal-title" id="modal-cat-titulo">Novo Item</div>
        <form method="POST" id="form-catalogo">
            <input type="hidden" name="acao"   id="f-acao"   value="criar">
            <input type="hidden" name="id"     id="f-id"     value="">
            <input type="hidden" name="tabela" id="f-tabela" value="">

            <!-- Ícone (só tipos) -->
            <div id="campo-icone" style="display:none">
                <label class="form-label">ÍCONE (EMOJI)</label>
                <input class="form-input" type="text" name="icone" id="f-icone" placeholder="🎸" maxlength="5">
            </div>

            <label class="form-label">NOME *</label>
            <input class="form-input" type="text" name="nome" id="f-nome" required placeholder="Digite o nome...">

            <!-- Marca vinculada (só modelos) -->
            <div id="campo-marca" style="display:none">
                <label class="form-label">MARCA (OPCIONAL)</label>
                <select class="form-input" name="marca_id" id="f-marca">
                    <option value="">— Sem marca —</option>
                    <?php foreach ($marcas as $m): ?>
                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label class="form-check" style="margin-top:8px">
                <input type="checkbox" name="ativo" id="f-ativo" value="1">
                ATIVO (VISÍVEL NO FORMULÁRIO)
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="fecharModalCat()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/sidebar.js?v=<?php echo $v; ?>"></script>
<script>
const _abaAtual = '<?php echo $aba; ?>';

function mudarAba(aba) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector(`.tab-btn[onclick="mudarAba('${aba}')"]`).classList.add('active');
    document.getElementById('tab-' + aba).classList.add('active');
    history.replaceState(null,'','instrumentos.php?aba='+aba);
    _abaCorrente = aba;
}

let _abaCorrente = _abaAtual;

const _tabelaMap = {
    tipos:   'cat_tipos',
    marcas:  'cat_marcas',
    modelos: 'cat_modelos',
    cores:   'cat_cores'
};

const _tituloMap = {
    cat_tipos:   ['Novo Tipo',   'Editar Tipo'],
    cat_marcas:  ['Nova Marca',  'Editar Marca'],
    cat_modelos: ['Novo Modelo', 'Editar Modelo'],
    cat_cores:   ['Nova Cor',    'Editar Cor'],
};

function abrirModalNovo() {
    const tabela = _tabelaMap[_abaCorrente];
    document.getElementById('modal-cat-titulo').textContent = _tituloMap[tabela][0];
    document.getElementById('f-acao').value   = 'criar';
    document.getElementById('f-id').value     = '';
    document.getElementById('f-tabela').value = tabela;
    document.getElementById('f-nome').value   = '';
    document.getElementById('f-ativo').checked = true;
    document.getElementById('f-icone').value  = '🎸';
    document.getElementById('f-marca').value  = '';
    document.getElementById('campo-icone').style.display  = tabela === 'cat_tipos'   ? '' : 'none';
    document.getElementById('campo-marca').style.display  = tabela === 'cat_modelos' ? '' : 'none';
    document.getElementById('modal-catalogo').classList.add('aberto');
    setTimeout(() => document.getElementById('f-nome').focus(), 150);
}

function editarItem(tabela, id, dados) {
    document.getElementById('modal-cat-titulo').textContent = _tituloMap[tabela][1];
    document.getElementById('f-acao').value   = 'editar';
    document.getElementById('f-id').value     = id;
    document.getElementById('f-tabela').value = tabela;
    document.getElementById('f-nome').value   = dados.nome || '';
    document.getElementById('f-ativo').checked = !!dados.ativo;
    document.getElementById('f-icone').value  = dados.icone || '🎸';
    document.getElementById('f-marca').value  = dados.marca_id || '';
    document.getElementById('campo-icone').style.display  = tabela === 'cat_tipos'   ? '' : 'none';
    document.getElementById('campo-marca').style.display  = tabela === 'cat_modelos' ? '' : 'none';
    document.getElementById('modal-catalogo').classList.add('aberto');
    setTimeout(() => document.getElementById('f-nome').focus(), 150);
}

function fecharModalCat() {
    document.getElementById('modal-catalogo').classList.remove('aberto');
}

document.getElementById('modal-catalogo').addEventListener('click', function(e) {
    if (e.target === this) fecharModalCat();
});
</script>
</body>
</html>
