<?php
/**
 * UTILITÁRIO - Adicionar colunas em pre_os
 * Executar UMA VEZ e depois deletar.
 */

require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: text/plain; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

// Verificar e adicionar coluna valor_orcamento
try {
    $col = $conn->query("SHOW COLUMNS FROM pre_os LIKE 'valor_orcamento'")->fetch();
    if ($col) {
        echo "⚠️ Coluna valor_orcamento já existe \u2014 pulando\n";
    } else {
        $conn->exec("ALTER TABLE pre_os ADD COLUMN valor_orcamento DECIMAL(10,2) DEFAULT NULL");
        echo "✅ Coluna valor_orcamento \u2014 criada com sucesso\n";
    }
} catch (PDOException $e) {
    echo "❌ valor_orcamento \u2014 " . $e->getMessage() . "\n";
}

// Verificar e adicionar coluna motivo_reprovacao
try {
    $col = $conn->query("SHOW COLUMNS FROM pre_os LIKE 'motivo_reprovacao'")->fetch();
    if ($col) {
        echo "⚠️ Coluna motivo_reprovacao já existe \u2014 pulando\n";
    } else {
        $conn->exec("ALTER TABLE pre_os ADD COLUMN motivo_reprovacao TEXT DEFAULT NULL");
        echo "✅ Coluna motivo_reprovacao \u2014 criada com sucesso\n";
    }
} catch (PDOException $e) {
    echo "❌ motivo_reprovacao \u2014 " . $e->getMessage() . "\n";
}

echo "\nPronto! Você pode fechar esta página.\n";
