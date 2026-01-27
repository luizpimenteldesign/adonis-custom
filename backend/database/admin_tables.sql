-- ================================================================
-- SISTEMA ADONIS - TABELAS ADMINISTRATIVAS
-- Versão: 1.0
-- Data: 26/01/2026
-- ================================================================

-- ================================================================
-- 1. TABELA DE USUÁRIOS ADMINISTRATIVOS
-- ================================================================
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `senha_hash` VARCHAR(255) NOT NULL,
  `tipo` ENUM('admin', 'supervisor') NOT NULL DEFAULT 'supervisor',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir usuário admin padrão (senha: admin123)
-- ATENÇÃO: Alterar senha após primeiro login!
INSERT INTO `usuarios` (`nome`, `email`, `senha_hash`, `tipo`) VALUES
('Administrador', 'admin@adonis.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`);

-- ================================================================
-- 2. TABELA DE LOGS DE ACESSO
-- ================================================================
CREATE TABLE IF NOT EXISTS `logs_acesso` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) UNSIGNED NULL,
  `ip` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(500) NULL,
  `tipo_acao` ENUM('login', 'login_falha', 'logout') NOT NULL,
  `detalhes` TEXT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_usuario_id` (`usuario_id`),
  INDEX `idx_tipo_acao` (`tipo_acao`),
  INDEX `idx_criado_em` (`criado_em`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 3. AJUSTES NA TABELA PREOS (PRÉ-OS)
-- ================================================================
ALTER TABLE `preos` 
  ADD COLUMN IF NOT EXISTS `numero_preos` VARCHAR(50) NULL UNIQUE AFTER `id`,
  ADD COLUMN IF NOT EXISTS `public_token` VARCHAR(64) NULL UNIQUE AFTER `status`,
  ADD COLUMN IF NOT EXISTS `public_token_active` TINYINT(1) DEFAULT 1 AFTER `public_token`,
  ADD COLUMN IF NOT EXISTS `token_expires_at` DATETIME NULL AFTER `public_token_active`,
  ADD COLUMN IF NOT EXISTS `observacoes` TEXT NULL AFTER `instrumento_id`,
  ADD INDEX IF NOT EXISTS `idx_public_token` (`public_token`),
  ADD INDEX IF NOT EXISTS `idx_status` (`status`),
  ADD INDEX IF NOT EXISTS `idx_criado_em` (`criado_em`);

-- ================================================================
-- 4. TABELA DE RELAÇÃO PRÉOS <-> SERVIÇOS (N:N)
-- ================================================================
CREATE TABLE IF NOT EXISTS `preos_servicos` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `preos_id` INT(11) UNSIGNED NOT NULL,
  `servico_id` INT(11) UNSIGNED NOT NULL,
  `quantidade` INT(11) DEFAULT 1,
  `valor_unitario` DECIMAL(10,2) NULL,
  `valor_total` DECIMAL(10,2) NULL,
  `observacao` TEXT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_preos_servico` (`preos_id`, `servico_id`),
  INDEX `idx_preos_id` (`preos_id`),
  INDEX `idx_servico_id` (`servico_id`),
  FOREIGN KEY (`preos_id`) REFERENCES `preos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`servico_id`) REFERENCES `servicos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 5. TABELA DE FOTOS
-- ================================================================
CREATE TABLE IF NOT EXISTS `fotos` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `preos_id` INT(11) UNSIGNED NOT NULL,
  `caminho` VARCHAR(500) NOT NULL,
  `nome_original` VARCHAR(255) NULL,
  `tamanho` INT(11) NULL COMMENT 'Tamanho em bytes',
  `tipo_mime` VARCHAR(100) NULL,
  `ordem` INT(11) DEFAULT 0,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_preos_id` (`preos_id`),
  INDEX `idx_ordem` (`ordem`),
  FOREIGN KEY (`preos_id`) REFERENCES `preos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 6. AJUSTES NA TABELA CLIENTES
-- ================================================================
ALTER TABLE `clientes`
  ADD COLUMN IF NOT EXISTS `endereco` TEXT NULL AFTER `email`,
  ADD INDEX IF NOT EXISTS `idx_telefone` (`telefone`),
  ADD INDEX IF NOT EXISTS `idx_email` (`email`);

-- ================================================================
-- 7. AJUSTES NA TABELA INSTRUMENTOS
-- ================================================================
ALTER TABLE `instrumentos`
  ADD COLUMN IF NOT EXISTS `referencia` VARCHAR(255) NULL AFTER `modelo`,
  ADD COLUMN IF NOT EXISTS `numero_serie` VARCHAR(255) NULL AFTER `cor`,
  ADD INDEX IF NOT EXISTS `idx_tipo` (`tipo`),
  ADD INDEX IF NOT EXISTS `idx_marca` (`marca`);

-- ================================================================
-- 8. TABELA DE HISTÓRICO DE STATUS (AUDITORIA)
-- ================================================================
CREATE TABLE IF NOT EXISTS `preos_historico` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `preos_id` INT(11) UNSIGNED NOT NULL,
  `status_anterior` VARCHAR(50) NULL,
  `status_novo` VARCHAR(50) NOT NULL,
  `usuario_id` INT(11) UNSIGNED NULL,
  `observacao` TEXT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_preos_id` (`preos_id`),
  INDEX `idx_usuario_id` (`usuario_id`),
  INDEX `idx_criado_em` (`criado_em`),
  FOREIGN KEY (`preos_id`) REFERENCES `preos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 9. TRIGGER PARA REGISTRAR HISTÓRICO DE STATUS
-- ================================================================
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS `trg_preos_status_change`
AFTER UPDATE ON `preos`
FOR EACH ROW
BEGIN
  IF OLD.status != NEW.status THEN
    INSERT INTO `preos_historico` 
      (`preos_id`, `status_anterior`, `status_novo`, `usuario_id`)
    VALUES 
      (NEW.id, OLD.status, NEW.status, NULL);
  END IF;
END$$

DELIMITER ;

-- ================================================================
-- 10. FUNÇÃO PARA GERAR NÚMERO DE PRÉ-OS
-- ================================================================
DELIMITER $$

CREATE FUNCTION IF NOT EXISTS `gerar_numero_preos`()
RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
  DECLARE ano_atual VARCHAR(4);
  DECLARE mes_atual VARCHAR(2);
  DECLARE contador INT;
  DECLARE numero VARCHAR(50);
  
  SET ano_atual = YEAR(CURDATE());
  SET mes_atual = LPAD(MONTH(CURDATE()), 2, '0');
  
  SELECT COUNT(*) + 1 INTO contador
  FROM preos
  WHERE YEAR(criado_em) = YEAR(CURDATE())
    AND MONTH(criado_em) = MONTH(CURDATE());
  
  SET numero = CONCAT('PREOS-', ano_atual, mes_atual, '-', LPAD(contador, 4, '0'));
  
  RETURN numero;
END$$

DELIMITER ;

-- ================================================================
-- FIM DO SCRIPT
-- ================================================================