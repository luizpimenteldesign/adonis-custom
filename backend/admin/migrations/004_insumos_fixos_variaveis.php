<?php
/**
 * Migration 004 — Insumos Fixos vs Variáveis
 *
 * O que faz:
 *   1. Garante colunas novas em insumos (categoria, tipo_insumo, quantidade_padrao)
 *   2. Cria a tabela servicos_insumos (padrão definitivo) se não existir
 *   3. Adiciona coluna tipo_vinculo em servicos_insumos se faltar
 *   4. Migra dados das tabelas legadas (insumos_servicos) se existirem
 *
 * COMO RODAR: acesse /backend/admin/migrations/004_insumos_fixos_variaveis.php
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/Database.php';

header('Content-Type: text/html; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

$resultados = [];

function col_existe($conn, $tabela, $coluna) {
    return (int) $conn->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = '{$tabela}'
         AND COLUMN_NAME = '{$coluna}'"
    )->fetchColumn() > 0;
}

function tabela_existe($conn, $tabela) {
    return (int) $conn->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = '{$tabela}'"
    )->fetchColumn() > 0;
}

// ─── PASSO 1 — Colunas novas em `insumos` ───
$colunas_insumos = [
    ['col'=>'categoria',        'sql'=>"ALTER TABLE insumos ADD COLUMN categoria VARCHAR(80) DEFAULT NULL AFTER modelo",                                         'label'=>'insumos.categoria'],
    ['col'=>'tipo_insumo',      'sql'=>"ALTER TABLE insumos ADD COLUMN tipo_insumo ENUM('fixo','variavel') NOT NULL DEFAULT 'variavel' AFTER categoria",        'label'=>'insumos.tipo_insumo'],
    ['col'=>'quantidade_padrao','sql'=>"ALTER TABLE insumos ADD COLUMN quantidade_padrao DECIMAL(10,3) NOT NULL DEFAULT 1.000 AFTER tipo_insumo",               'label'=>'insumos.quantidade_padrao'],
];
foreach ($colunas_insumos as $c) {
    try {
        if (col_existe($conn, 'insumos', $c['col'])) {
            $resultados[] = ['status'=>'skip','label'=>$c['label'],'msg'=>'Já existe, pulado.'];
        } else {
            $conn->exec($c['sql']);
            $resultados[] = ['status'=>'ok','label'=>$c['label'],'msg'=>'Criado com sucesso.'];
        }
    } catch (Exception $e) {
        $resultados[] = ['status'=>'erro','label'=>$c['label'],'msg'=>$e->getMessage()];
    }
}

// ─── PASSO 2 — Cria `servicos_insumos` se não existir ───
try {
    if (tabela_existe($conn, 'servicos_insumos')) {
        $resultados[] = ['status'=>'skip','label'=>'Tabela servicos_insumos','msg'=>'Já existe, pulado.'];
    } else {
        $conn->exec("
            CREATE TABLE servicos_insumos (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                servico_id        INT NOT NULL,
                insumo_id         INT NOT NULL,
                tipo_vinculo      ENUM('fixo','variavel') NOT NULL DEFAULT 'variavel',
                quantidade_padrao DECIMAL(10,3) NOT NULL DEFAULT 1.000,
                criado_em         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unico (servico_id, insumo_id),
                INDEX idx_servico (servico_id),
                INDEX idx_insumo  (insumo_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $resultados[] = ['status'=>'ok','label'=>'Tabela servicos_insumos','msg'=>'Criada com sucesso.'];
    }
} catch (Exception $e) {
    $resultados[] = ['status'=>'erro','label'=>'Tabela servicos_insumos','msg'=>$e->getMessage()];
}

// ─── PASSO 3 — Garante tipo_vinculo na tabela (caso já existisse sem ela) ───
try {
    if (!col_existe($conn, 'servicos_insumos', 'tipo_vinculo')) {
        $conn->exec("ALTER TABLE servicos_insumos ADD COLUMN tipo_vinculo ENUM('fixo','variavel') NOT NULL DEFAULT 'variavel' AFTER insumo_id");
        $resultados[] = ['status'=>'ok','label'=>'servicos_insumos.tipo_vinculo','msg'=>'Coluna adicionada.'];
    } else {
        $resultados[] = ['status'=>'skip','label'=>'servicos_insumos.tipo_vinculo','msg'=>'Já existe, pulado.'];
    }
} catch (Exception $e) {
    $resultados[] = ['status'=>'erro','label'=>'servicos_insumos.tipo_vinculo','msg'=>$e->getMessage()];
}

// ─── PASSO 4 — Migra dados legados de `insumos_servicos` se existir ───
try {
    if (tabela_existe($conn, 'insumos_servicos')) {
        $migrados = $conn->exec("
            INSERT IGNORE INTO servicos_insumos (servico_id, insumo_id, tipo_vinculo, quantidade_padrao)
            SELECT servicoid, insumoid, 'variavel', 1.000
            FROM insumos_servicos
        ");
        $resultados[] = ['status'=>'ok','label'=>'Migração legado insumos_servicos','msg'=> $migrados . ' vínculos migrados.'];
    } else {
        $resultados[] = ['status'=>'skip','label'=>'Migração legado insumos_servicos','msg'=>'Tabela legada não encontrada (OK, banco está limpo).'];
    }
} catch (Exception $e) {
    $resultados[] = ['status'=>'erro','label'=>'Migração legado insumos_servicos','msg'=>$e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Migration 004 — Adonis</title>
<style>
body{font-family:monospace;background:#0f1117;color:#e2e8f0;padding:32px;max-width:760px;margin:0 auto}
h2{color:#f97316;margin-bottom:24px}
.item{display:flex;gap:12px;align-items:flex-start;padding:10px 14px;border-radius:6px;margin-bottom:8px;background:#1e2130}
.badge{padding:3px 10px;border-radius:4px;font-size:12px;font-weight:700;white-space:nowrap}
.ok{background:#16a34a;color:#fff}
.skip{background:#334155;color:#94a3b8}
.erro{background:#dc2626;color:#fff}
.label{font-weight:700;color:#94a3b8;font-size:13px}
.msg{font-size:13px;color:#cbd5e1;margin-top:2px}
.footer{margin-top:32px;padding:16px;background:#1e2130;border-radius:8px;color:#94a3b8;font-size:13px}
a{color:#f97316}
</style>
</head>
<body>
<h2>⚙️ Migration 004 — Insumos Fixos vs Variáveis</h2>
<?php foreach ($resultados as $r): ?>
<div class="item">
    <span class="badge <?php echo $r['status']; ?>"><?php echo strtoupper($r['status']); ?></span>
    <div>
        <div class="label"><?php echo htmlspecialchars($r['label']); ?></div>
        <div class="msg"><?php echo htmlspecialchars($r['msg']); ?></div>
    </div>
</div>
<?php endforeach; ?>
<div class="footer">
    Migration concluída. <a href="../insumos.php">← Voltar para Insumos</a>
</div>
</body>
</html>
