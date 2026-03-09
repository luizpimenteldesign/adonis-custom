-- ============================================================
-- Tabela: insumos_precos_historico
-- Registra variações de preço dos insumos ao longo do tempo
-- ============================================================

CREATE TABLE IF NOT EXISTS `insumos_precos_historico` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `insumo_id`     INT(11) NOT NULL,
  `preco_anterior` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `preco_novo`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `variacao_pct`  DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Variação percentual',
  `fonte`         VARCHAR(50) NOT NULL DEFAULT 'mercadolivre' COMMENT 'mercadolivre | manual',
  `query_usada`   VARCHAR(200) DEFAULT NULL COMMENT 'Termo buscado na API',
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_insumo_id` (`insumo_id`),
  KEY `idx_atualizado_em` (`atualizado_em`),
  CONSTRAINT `fk_hist_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
