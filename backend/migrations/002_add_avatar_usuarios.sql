-- Migration: Adiciona campo avatar_url na tabela usuarios
-- Data: 2026-03-06
-- Autor: Sistema Adonis

ALTER TABLE `usuarios` 
ADD COLUMN `avatar_url` VARCHAR(255) NULL DEFAULT NULL AFTER `senhahash`,
ADD COLUMN `ativo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `tipo`,
ADD COLUMN `ultimo_acesso` DATETIME NULL DEFAULT NULL AFTER `ativo`;

-- Índice para busca rápida por usuários ativos
CREATE INDEX `idx_ativo` ON `usuarios` (`ativo`);

-- Criar tabela logs_acesso se não existir (para histórico de login)
CREATE TABLE IF NOT EXISTS `logs_acesso` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `tipo_acao` ENUM('login','logout','login_falha','atualizar_perfil','alterar_senha') NOT NULL DEFAULT 'login',
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_tipo` (`tipo_acao`),
  CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
