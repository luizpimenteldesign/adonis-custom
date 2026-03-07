-- Migração: Categorias de Insumos
-- Permite agrupar insumos por tipo no modal de análise

-- 1. Criar tabela de categorias de insumos
CREATE TABLE IF NOT EXISTS `categorias_insumo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `icone` varchar(50) DEFAULT 'inventory_2',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Popular com categorias padrão
INSERT INTO `categorias_insumo` (`nome`, `icone`, `ativo`) VALUES
('Trastes', 'radio_button_unchecked', 1),
('Cordas', 'cable', 1),
('Eletrônica', 'electrical_services', 1),
('Marcenaria', 'carpenter', 1),
('Acabamento', 'format_paint', 1),
('Ferramentas', 'handyman', 1),
('Hardware', 'construction', 1),
('Outros', 'category', 1);

-- 3. Adicionar coluna categoria na tabela insumos
ALTER TABLE `insumos` 
  ADD COLUMN `categoria` varchar(100) DEFAULT NULL AFTER `modelo`,
  ADD KEY `idx_categoria` (`categoria`);

-- 4. Atualizar insumos existentes (exemplo - ajustar conforme necessário)
UPDATE `insumos` SET `categoria` = 'Eletrônica' WHERE `nome` LIKE '%solda%';
UPDATE `insumos` SET `categoria` = 'Trastes' WHERE `nome` LIKE '%traste%';
UPDATE `insumos` SET `categoria` = 'Cordas' WHERE `nome` LIKE '%corda%' OR `nome` LIKE '%encordoamento%';
UPDATE `insumos` SET `categoria` = 'Acabamento' WHERE `nome` LIKE '%verniz%' OR `nome` LIKE '%lixa%' OR `nome` LIKE '%tinta%';
UPDATE `insumos` SET `categoria` = 'Marcenaria' WHERE `nome` LIKE '%cola%' OR `nome` LIKE '%madeira%';
