<?php
/**
 * Migração: adiciona colunas marca e modelo na tabela insumos
 * Executar UMA vez via browser: /backend/admin/migrations/alter-insumos-marca-modelo.php
 */
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$resultados = [];

// Verifica e adiciona coluna marca
$cols = $conn->query("SHOW COLUMNS FROM insumos LIKE 'marca'")->fetchAll();
if (empty($cols)) {
    try {
        $conn->exec("ALTER TABLE insumos ADD COLUMN marca VARCHAR(100) NULL AFTER nome");
        $resultados[] = ['col' => 'marca', 'ok' => true, 'msg' => 'Coluna marca adicionada.'];
    } catch (Exception $e) {
        $resultados[] = ['col' => 'marca', 'ok' => false, 'msg' => $e->getMessage()];
    }
} else {
    $resultados[] = ['col' => 'marca', 'ok' => true, 'msg' => 'Coluna marca ja existe, nenhuma alteracao necessaria.'];
}

// Verifica e adiciona coluna modelo
$cols = $conn->query("SHOW COLUMNS FROM insumos LIKE 'modelo'")->fetchAll();
if (empty($cols)) {
    try {
        $conn->exec("ALTER TABLE insumos ADD COLUMN modelo VARCHAR(100) NULL AFTER marca");
        $resultados[] = ['col' => 'modelo', 'ok' => true, 'msg' => 'Coluna modelo adicionada.'];
    } catch (Exception $e) {
        $resultados[] = ['col' => 'modelo', 'ok' => false, 'msg' => $e->getMessage()];
    }
} else {
    $resultados[] = ['col' => 'modelo', 'ok' => true, 'msg' => 'Coluna modelo ja existe, nenhuma alteracao necessaria.'];
}

echo '<pre style="font-family:monospace;font-size:14px">';
foreach ($resultados as $r) {
    $icon = $r['ok'] ? '&#10003;' : '&#10007;';
    $cor  = $r['ok'] ? 'green' : 'red';
    echo "<span style='color:{$cor}'>{$icon} [{$r['col']}] {$r['msg']}</span>\n";
}
echo '</pre>';
echo '<p><a href="../insumos.php">Voltar para Insumos</a></p>';
