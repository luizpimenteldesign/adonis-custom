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
        // Verifica se a chave existe
        $s = $conn->prepare('SELECT id FROM configuracoes WHERE chave = ? LIMIT 1');
        $s->execute([$chave]);
        if ($s->fetch()) {
            $conn->prepare('UPDATE configuracoes SET valor = ? WHERE chave = ?')
                 ->execute([$valor, $chave]);
        } else {
            $conn->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?)')
                 ->execute([$chave, $valor]);
        }
        return true;
    } catch (Exception $e) {
        error_log('setCfg ['.$chave.']: '.$e->getMessage());
        return $e->getMessage();
    }
}

$chaves = [
    'nome_loja', 'telefone_loja', 'endereco_loja', 'chave_pix', 'desconto_avista_perc',
    'limite_faixa_cartao', 'taxa_visa_master_ate', 'taxa_visa_master_acima',
    'taxa_elo_amex_ate', 'taxa_elo_amex_acima',
    'whatsapp_admin', 'callmebot_token',
    'perc_entrada', 'perc_retirada',
];

$erros = [];
foreach ($chaves as $c) {
    $val = isset($_POST[$c]) ? trim($_POST[$c]) : '';
    $res = setCfg($conn, $c, $val);
    if ($res !== true) $erros[] = $c . ': ' . $res;
}

if (empty($erros)) {
    header('Location: configuracoes.php?msg=sucesso:Configura%C3%A7%C3%B5es salvas com sucesso!');
} else {
    $detalhe = urlencode(implode(' | ', $erros));
    header('Location: configuracoes.php?msg=erro:' . $detalhe);
}
exit;
