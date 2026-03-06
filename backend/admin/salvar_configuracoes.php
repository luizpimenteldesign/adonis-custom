<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: configuracoes.php');
    exit;
}

function setCfg($conn, $chave, $valor) {
    try {
        $stmt = $conn->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizadoem = NOW()');
        $stmt->execute([$chave, $valor]);
        return true;
    } catch (Exception $e) {
        error_log('Erro ao salvar configuração [' . $chave . ']: ' . $e->getMessage());
        return false;
    }
}

$chaves = [
    'nome_loja', 'telefone_loja', 'endereco_loja', 'chave_pix', 'desconto_avista_perc',
    'limite_faixa_cartao', 'taxa_visa_master_ate', 'taxa_visa_master_acima',
    'taxa_elo_amex_ate', 'taxa_elo_amex_acima',
    'whatsapp_admin', 'callmebot_token',
    'perc_entrada', 'perc_retirada',
];

$ok = true;
foreach ($chaves as $c) {
    $val = isset($_POST[$c]) ? trim($_POST[$c]) : '';
    if (!setCfg($conn, $c, $val)) $ok = false;
}

if ($ok) {
    header('Location: configuracoes.php?msg=sucesso:Configurações salvas com sucesso!');
} else {
    header('Location: configuracoes.php?msg=erro:Erro ao salvar uma ou mais configurações. Verifique o log.');
}
exit;
