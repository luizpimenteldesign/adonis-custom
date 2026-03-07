<?php
/**
 * Migração: cria tabela configuracoes e insere valores padrão
 * Executar UMA vez via browser: /backend/admin/migrations/create-configuracoes.php
 */
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$resultados = [];

// 1. Cria a tabela
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        chave      VARCHAR(100) NOT NULL UNIQUE,
        valor      TEXT NULL,
        criado_em  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $resultados[] = ['ok' => true, 'msg' => 'Tabela configuracoes criada (ou já existia).'];
} catch (Exception $e) {
    $resultados[] = ['ok' => false, 'msg' => 'Criar tabela: ' . $e->getMessage()];
}

// 2. Insere valores padrão
$defaults = [
    'nome_loja'              => 'Adonis',
    'telefone_loja'          => '',
    'endereco_loja'          => '',
    'chave_pix'              => '',
    'desconto_avista_perc'   => '10',
    'limite_faixa_cartao'    => '2000.00',
    'taxa_visa_master_ate'   => '2.99',
    'taxa_visa_master_acima' => '3.99',
    'taxa_elo_amex_ate'      => '3.99',
    'taxa_elo_amex_acima'    => '4.99',
    'whatsapp_admin'         => '',
    'callmebot_token'        => '',
    'perc_entrada'           => '60',
    'perc_retirada'          => '40',
];

foreach ($defaults as $chave => $valor) {
    try {
        $existe = $conn->prepare("SELECT id FROM configuracoes WHERE chave = ?");
        $existe->execute([$chave]);
        if (!$existe->fetch()) {
            $conn->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?)")
                 ->execute([$chave, $valor]);
            $resultados[] = ['ok' => true, 'msg' => "[{$chave}] inserido com valor padrão."];
        } else {
            $resultados[] = ['ok' => true, 'msg' => "[{$chave}] já existe, mantido."];
        }
    } catch (Exception $e) {
        $resultados[] = ['ok' => false, 'msg' => "[{$chave}]: " . $e->getMessage()];
    }
}

echo '<pre style="font-family:monospace;font-size:14px">';
foreach ($resultados as $r) {
    $icon = $r['ok'] ? '&#10003;' : '&#10007;';
    $cor  = $r['ok'] ? 'green' : 'red';
    echo "<span style='color:{$cor}'>{$icon} {$r['msg']}</span>\n";
}
echo '</pre>';
echo '<p><a href=\"../configuracoes.php\">Ir para Configurações</a></p>';
