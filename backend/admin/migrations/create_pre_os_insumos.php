<?php
/**
 * Migration: cria tabela pre_os_insumos
 * Acesse via browser uma única vez: /backend/admin/migrations/create_pre_os_insumos.php
 */
require_once '../auth.php';
require_once '../../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `pre_os_insumos` (
            `id`             int(11)        NOT NULL AUTO_INCREMENT,
            `pre_os_id`      int(11)        NOT NULL,
            `insumo_id`      int(11)        NOT NULL,
            `quantidade`     decimal(10,3)  NOT NULL DEFAULT '1.000',
            `valor_unitario` decimal(10,2)  NOT NULL,
            `cliente_fornece` tinyint(1)    NOT NULL DEFAULT '0',
            `criado_em`      datetime       DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_poi_pre_os`  (`pre_os_id`),
            KEY `idx_poi_insumo`  (`insumo_id`),
            CONSTRAINT `fk_poi_pre_os` FOREIGN KEY (`pre_os_id`) REFERENCES `pre_os`  (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_poi_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo '<p style="color:green;font-family:monospace">✔ Tabela <strong>pre_os_insumos</strong> criada (ou já existia).</p>';
} catch (PDOException $e) {
    echo '<p style="color:red;font-family:monospace">✘ Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
