-- ============================================================
-- MIGRAÇÃO v5.0 — Novos sub-status e campos de pagamento
-- Adonis Custom — 27/02/2026
-- Execute este script UMA VEZ no banco de dados MySQL/MariaDB
-- ============================================================

-- 1. Adicionar novos campos na tabela status_historico (seguros: IF NOT EXISTS)
ALTER TABLE status_historico
    ADD COLUMN IF NOT EXISTS forma_pagamento      VARCHAR(50)    NULL AFTER motivo,
    ADD COLUMN IF NOT EXISTS parcelas             TINYINT        NULL AFTER forma_pagamento,
    ADD COLUMN IF NOT EXISTS valor_final          DECIMAL(10,2)  NULL AFTER parcelas,
    ADD COLUMN IF NOT EXISTS por_parcela          DECIMAL(10,2)  NULL AFTER valor_final,
    ADD COLUMN IF NOT EXISTS descricao_pagamento  VARCHAR(120)   NULL AFTER por_parcela;

-- 2. Expandir ENUM de status em pre_os (se usar ENUM)
-- Só execute se sua coluna `status` for do tipo ENUM:
-- ALTER TABLE pre_os MODIFY COLUMN status ENUM(
--     'Pre-OS','Em analise','Orcada','Aguardando aprovacao',
--     'Aprovada',
--     'Pagamento recebido','Instrumento recebido',
--     'Servico iniciado','Em desenvolvimento','Servico finalizado',
--     'Pronto para retirada','Aguardando pagamento retirada','Entregue',
--     'Reprovada','Cancelada'
-- ) NOT NULL DEFAULT 'Pre-OS';

-- Se o campo for VARCHAR (recomendado), nenhuma alteração necessária.

-- 3. Expandir ENUM em status_historico (se usar ENUM):
-- ALTER TABLE status_historico MODIFY COLUMN status ENUM(
--     'Pre-OS','Em analise','Orcada','Aguardando aprovacao',
--     'Aprovada',
--     'Pagamento recebido','Instrumento recebido',
--     'Servico iniciado','Em desenvolvimento','Servico finalizado',
--     'Pronto para retirada','Aguardando pagamento retirada','Entregue',
--     'Reprovada','Cancelada'
-- ) NOT NULL;

-- Fim da migração v5.0
