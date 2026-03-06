<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

function getCfg($conn, $chave, $padrao = '') {
    try {
        $stmt = $conn->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
        $stmt->execute([$chave]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['valor'] : $padrao;
    } catch (Exception $e) { return $padrao; }
}

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// Carrega valores atuais
$cfg = [
    'nome_loja'              => getCfg($conn, 'nome_loja', 'Adonis'),
    'telefone_loja'          => getCfg($conn, 'telefone_loja', ''),
    'endereco_loja'          => getCfg($conn, 'endereco_loja', ''),
    'chave_pix'              => getCfg($conn, 'chave_pix', ''),
    'desconto_avista_perc'   => getCfg($conn, 'desconto_avista_perc', '10'),

    'limite_faixa_cartao'    => getCfg($conn, 'limite_faixa_cartao', '2000.00'),
    'taxa_visa_master_ate'   => getCfg($conn, 'taxa_visa_master_ate', '2.99'),
    'taxa_visa_master_acima' => getCfg($conn, 'taxa_visa_master_acima', '3.99'),
    'taxa_elo_amex_ate'      => getCfg($conn, 'taxa_elo_amex_ate', '3.99'),
    'taxa_elo_amex_acima'    => getCfg($conn, 'taxa_elo_amex_acima', '4.99'),

    'whatsapp_admin'         => getCfg($conn, 'whatsapp_admin', ''),
    'callmebot_token'        => getCfg($conn, 'callmebot_token', ''),

    'perc_entrada'           => getCfg($conn, 'perc_entrada', '60'),
    'perc_retirada'          => getCfg($conn, 'perc_retirada', '40'),
];

$current_page = 'configuracoes.php';
$v = time();
include '_sidebar_data.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações — Adonis Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?php echo $v; ?>">
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
        <span class="topbar-title">Configurações</span>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px">settings</span>Configurações
                </h1>
                <div class="page-subtitle">Parâmetros gerais do sistema</div>
            </div>
        </div>

        <?php if ($msg): list($tipo, $texto) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($texto); ?></div>
        <?php endif; ?>

        <form method="POST" action="salvar_configuracoes.php" id="form-config">
            <!-- SEÇÃO 1: DADOS DO NEGÓCIO -->
            <div class="config-section">
                <div class="config-section-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;color:var(--g-primary)">store</span>
                    Dados do Negócio
                </div>
                <div class="config-grid">
                    <div class="config-field">
                        <label class="form-label">NOME DA LOJA</label>
                        <input class="form-input" type="text" name="nome_loja" value="<?php echo htmlspecialchars($cfg['nome_loja']); ?>" placeholder="Ex: Adonis Music Shop">
                    </div>
                    <div class="config-field">
                        <label class="form-label">TELEFONE</label>
                        <input class="form-input" type="text" name="telefone_loja" value="<?php echo htmlspecialchars($cfg['telefone_loja']); ?>" placeholder="(00) 00000-0000">
                    </div>
                </div>
                <div class="config-field">
                    <label class="form-label">ENDEREÇO COMPLETO</label>
                    <input class="form-input" type="text" name="endereco_loja" value="<?php echo htmlspecialchars($cfg['endereco_loja']); ?>" placeholder="Rua, Número, Bairro, Cidade - UF">
                </div>
                <div class="config-grid">
                    <div class="config-field">
                        <label class="form-label">CHAVE PIX</label>
                        <input class="form-input" type="text" name="chave_pix" value="<?php echo htmlspecialchars($cfg['chave_pix']); ?>" placeholder="CPF, CNPJ, e-mail ou aleatória">
                    </div>
                    <div class="config-field">
                        <label class="form-label">DESCONTO À VISTA (PIX/DINHEIRO) %</label>
                        <input class="form-input" type="number" name="desconto_avista_perc" value="<?php echo htmlspecialchars($cfg['desconto_avista_perc']); ?>" min="0" max="100" step="0.1" placeholder="10">
                        <div class="form-hint">% de desconto concedido em pagamentos à vista</div>
                    </div>
                </div>
            </div>

            <!-- SEÇÃO 2: TAXAS DE CARTÃO -->
            <div class="config-section">
                <div class="config-section-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;color:var(--g-primary)">credit_card</span>
                    Taxas de Cartão de Crédito
                </div>
                <div class="config-field">
                    <label class="form-label">LIMITE DE FAIXA (R$)</label>
                    <input class="form-input" type="number" name="limite_faixa_cartao" value="<?php echo htmlspecialchars($cfg['limite_faixa_cartao']); ?>" min="0" step="0.01" placeholder="2000.00">
                    <div class="form-hint">Valor que separa a faixa "até" da faixa "acima de"</div>
                </div>
                <div class="config-grid">
                    <div class="config-field">
                        <label class="form-label">TAXA VISA/MASTER — ATÉ R$ <?php echo number_format((float)$cfg['limite_faixa_cartao'], 2, ',', '.'); ?></label>
                        <input class="form-input" type="number" name="taxa_visa_master_ate" value="<?php echo htmlspecialchars($cfg['taxa_visa_master_ate']); ?>" min="0" max="100" step="0.01" placeholder="2.99">
                        <div class="form-hint">Taxa máxima (%) cobrada pela máquina para Visa/Master</div>
                    </div>
                    <div class="config-field">
                        <label class="form-label">TAXA VISA/MASTER — ACIMA DE R$ <?php echo number_format((float)$cfg['limite_faixa_cartao'], 2, ',', '.'); ?></label>
                        <input class="form-input" type="number" name="taxa_visa_master_acima" value="<?php echo htmlspecialchars($cfg['taxa_visa_master_acima']); ?>" min="0" max="100" step="0.01" placeholder="3.99">
                        <div class="form-hint">Taxa máxima (%) cobrada pela máquina para Visa/Master</div>
                    </div>
                </div>
                <div class="config-grid">
                    <div class="config-field">
                        <label class="form-label">TAXA ELO/AMEX — ATÉ R$ <?php echo number_format((float)$cfg['limite_faixa_cartao'], 2, ',', '.'); ?></label>
                        <input class="form-input" type="number" name="taxa_elo_amex_ate" value="<?php echo htmlspecialchars($cfg['taxa_elo_amex_ate']); ?>" min="0" max="100" step="0.01" placeholder="3.99">
                        <div class="form-hint">Taxa máxima (%) cobrada pela máquina para Elo/Amex</div>
                    </div>
                    <div class="config-field">
                        <label class="form-label">TAXA ELO/AMEX — ACIMA DE R$ <?php echo number_format((float)$cfg['limite_faixa_cartao'], 2, ',', '.'); ?></label>
                        <input class="form-input" type="number" name="taxa_elo_amex_acima" value="<?php echo htmlspecialchars($cfg['taxa_elo_amex_acima']); ?>" min="0" max="100" step="0.01" placeholder="4.99">
                        <div class="form-hint">Taxa máxima (%) cobrada pela máquina para Elo/Amex</div>
                    </div>
                </div>
            </div>

            <!-- SEÇÃO 3: WHATSAPP / CALLMEBOT -->
            <div class="config-section">
                <div class="config-section-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;color:var(--g-primary)">chat</span>
                    WhatsApp / CallMeBot
                </div>
                <div class="config-grid">
                    <div class="config-field">
                        <label class="form-label">WHATSAPP DO ADMIN (NOTIFICAÇÕES)</label>
                        <input class="form-input" type="text" name="whatsapp_admin" value="<?php echo htmlspecialchars($cfg['whatsapp_admin']); ?>" placeholder="5527999999999" maxlength="13">
                        <div class="form-hint">Número com DDI+DDD (Ex: 5527999999999)</div>
                    </div>
                    <div class="config-field">
                        <label class="form-label">TOKEN CALLMEBOT</label>
                        <input class="form-input" type="text" name="callmebot_token" value="<?php echo htmlspecialchars($cfg['callmebot_token']); ?>" placeholder="Token da API CallMeBot">
                        <div class="form-hint">Obtido em <a href="https://www.callmebot.com/blog/free-api-whatsapp-messages/" target="_blank" style="color:var(--g-primary)">callmebot.com</a></div>
                    </div>
                </div>
            </div>

            <!-- SEÇÃO 4: ENTRADA E RETIRADA -->
            <div class="config-section">
                <div class="config-section-title">
                    <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;color:var(--g-primary)">payments</span>
                    Parcelamento Padrão — Entrada e Retirada
                </div>
                <div class="config-grid">
                    <div class="config-field">
                        <label class="form-label">PERCENTUAL DE ENTRADA (%)</label>
                        <input class="form-input" type="number" name="perc_entrada" value="<?php echo htmlspecialchars($cfg['perc_entrada']); ?>" min="0" max="100" step="1" placeholder="60">
                        <div class="form-hint">Valor cobrado na aprovação do orçamento (padrão: 60%)</div>
                    </div>
                    <div class="config-field">
                        <label class="form-label">PERCENTUAL NA RETIRADA (%)</label>
                        <input class="form-input" type="number" name="perc_retirada" value="<?php echo htmlspecialchars($cfg['perc_retirada']); ?>" min="0" max="100" step="1" placeholder="40">
                        <div class="form-hint">Valor cobrado ao retirar o instrumento (padrão: 40%)</div>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px">
                <button type="button" class="btn btn-secondary" onclick="if(confirm('Descartar alterações?'))location.reload()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px">save</span>
                    Salvar Configurações
                </button>
            </div>
        </form>
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
// Validação de soma entrada + retirada = 100%
document.getElementById('form-config').addEventListener('submit', function(e) {
    const entrada = parseFloat(document.querySelector('[name="perc_entrada"]').value || 0);
    const retirada = parseFloat(document.querySelector('[name="perc_retirada"]').value || 0);
    if (Math.abs(entrada + retirada - 100) > 0.01) {
        e.preventDefault();
        alert('A soma de Entrada + Retirada deve ser 100%. Atualmente: ' + (entrada + retirada).toFixed(0) + '%');
        return false;
    }
});
</script>
</body>
</html>
