-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRATION 004: TABELA servico_insumos
-- Vincula insumos aos serviços (relação muitos-para-muitos)
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `servico_insumos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `servico_id` INT UNSIGNED NOT NULL COMMENT 'ID do serviço',
  `insumo_id` INT UNSIGNED NOT NULL COMMENT 'ID do insumo',
  `quantidade` DECIMAL(10,3) NOT NULL DEFAULT 1.000 COMMENT 'Quantidade padrão necessária',
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_servico_insumo` (`servico_id`, `insumo_id`),
  KEY `idx_servico` (`servico_id`),
  KEY `idx_insumo` (`insumo_id`),
  CONSTRAINT `fk_servico_insumos_servico` 
    FOREIGN KEY (`servico_id`) 
    REFERENCES `servicos` (`id`) 
    ON DELETE CASCADE,
  CONSTRAINT `fk_servico_insumos_insumo` 
    FOREIGN KEY (`insumo_id`) 
    REFERENCES `insumos` (`id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vincula insumos aos serviços';

-- ═══════════════════════════════════════════════════════════════════════════════
-- DADOS DE EXEMPLO (opcional - remova se não quiser)
-- ═══════════════════════════════════════════════════════════════════════════════

-- Exemplo: Se você tem um serviço "Troca de cordas" (id=1) que usa "Jogo de cordas" (id=1)
-- INSERT INTO servico_insumos (servico_id, insumo_id, quantidade) VALUES
-- (1, 1, 1.000);

-- Para descobrir os IDs dos seus serviços e insumos:
-- SELECT id, nome FROM servicos;
-- SELECT id, nome FROM insumos;
