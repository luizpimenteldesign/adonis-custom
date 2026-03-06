<?php
/**
 * criar_tabela_configuracoes.php
 * Executar UMA ÚNICA VEZ pelo navegador para criar a tabela "configuracoes" no banco.
 * Acesse: https://adns.luizpimentel.com/adonis-custom/backend/admin/criar_tabela_configuracoes.php
 */
require_once 'auth.php';
require_once '../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS configuracoes (
            chave VARCHAR(100) PRIMARY KEY,
            valor TEXT NULL,
            atualizadoem DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ Tabela 'configuracoes' criada (ou já existia).\n\n";

    // Insere valores padrão caso ainda não existam
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
        $stmt = $conn->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE chave = chave');
        $stmt->execute([$chave, $valor]);
    }

    echo "✅ Valores padrão inseridos (se ainda não existiam).\n\n";
    echo "🎉 Tudo pronto! Acesse: configuracoes.php\n";

} catch (Exception $e) {
    echo "❌ ERRO AO CRIAR TABELA:\n";
    echo $e->getMessage() . "\n";
    exit;
}
