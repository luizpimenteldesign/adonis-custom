<?php
/**
 * Migração: adiciona colunas marca e modelo na tabela insumos
 * Executar UMA vez via browser: /backend/admin/migrations/alter-insumos-marca-modelo.php
 */
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$sqls = [
    "ALTER TABLE insumos ADD COLUMN IF NOT EXISTS marca  VARCHAR(100) NULL AFTER nome",
    "ALTER TABLE insumos ADD COLUMN IF NOT EXISTS modelo VARCHAR(100) NULL AFTER marca",
];

$erros = [];
foreach ($sqls as $sql) {
    try { $conn->exec($sql); }
    catch (Exception $e) { $erros[] = $e->getMessage(); }
}

if ($erros) {
    echo '<pre style="color:red">Erro(s):\n' . implode("\n", $erros) . '</pre>';
} else {
    echo '<p style="color:green;font-family:monospace">&#10003; Colunas marca e modelo adicionadas com sucesso na tabela insumos.</p>';
    echo '<p><a href="../insumos.php">Voltar para Insumos</a></p>';
}
