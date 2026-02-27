<?php
/**
 * UTILITÁRIO - Criar tabela status_historico e colunas extras em pre_os
 * Executar UMA VEZ pelo navegador estando logado, depois deletar este arquivo.
 * URL: /backend/admin/criar_tabela_historico.php
 */

require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: text/plain; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

$sqls = [
    "Tabela status_historico" => "
        CREATE TABLE IF NOT EXISTS status_historico (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            pre_os_id    INT          NOT NULL,
            status       VARCHAR(50)  NOT NULL,
            valor_orcamento DECIMAL(10,2) DEFAULT NULL,
            motivo       TEXT         DEFAULT NULL,
            admin_id     INT          DEFAULT NULL,
            criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pre_os_id (pre_os_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    "Coluna valor_orcamento em pre_os" => "
        ALTER TABLE pre_os
        ADD COLUMN IF NOT EXISTS valor_orcamento DECIMAL(10,2) DEFAULT NULL
    ",
    "Coluna motivo_reprovacao em pre_os" => "
        ALTER TABLE pre_os
        ADD COLUMN IF NOT EXISTS motivo_reprovacao TEXT DEFAULT NULL
    "
];

foreach ($sqls as $label => $sql) {
    try {
        $conn->exec(trim($sql));
        echo "✅ $label — OK\n";
    } catch (PDOException $e) {
        echo "❌ $label — " . $e->getMessage() . "\n";
    }
}

echo "\nPronto! Delete este arquivo após executar.\n";
