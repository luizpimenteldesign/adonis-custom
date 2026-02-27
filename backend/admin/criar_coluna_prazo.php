<?php
/**
 * UTILITÁRIO - Adicionar coluna prazo_orcamento em pre_os
 * Executar UMA VEZ e depois será bloqueado automaticamente.
 */
require_once 'auth.php';
require_once '../config/Database.php';
header('Content-Type: text/plain; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

try {
    $col = $conn->query("SHOW COLUMNS FROM pre_os LIKE 'prazo_orcamento'")->fetch();
    if ($col) {
        echo "⚠️ Coluna prazo_orcamento já existe — nada a fazer.\n";
    } else {
        $conn->exec("ALTER TABLE pre_os ADD COLUMN prazo_orcamento INT DEFAULT NULL COMMENT 'Prazo em dias uteis definido pelo admin no orcamento'");
        echo "✅ Coluna prazo_orcamento — criada com sucesso!\n";
    }

    // Adicionar também na tabela de histórico
    $col2 = $conn->query("SHOW COLUMNS FROM status_historico LIKE 'prazo_orcamento'")->fetch();
    if ($col2) {
        echo "⚠️ Coluna prazo_orcamento em status_historico já existe.\n";
    } else {
        $conn->exec("ALTER TABLE status_historico ADD COLUMN prazo_orcamento INT DEFAULT NULL");
        echo "✅ Coluna prazo_orcamento em status_historico — criada!\n";
    }
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\nPronto! Você pode fechar esta página.\n";
