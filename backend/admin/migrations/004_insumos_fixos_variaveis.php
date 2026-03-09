<?php
/**
 * Migration 004 — Insumos Fixos vs Variáveis
 *
 * Adiciona:
 *   - insumos.categoria        (varchar 80)  — agrupa por tipo de material
 *   - insumos.tipo_insumo      (enum)        — 'fixo' ou 'variavel'
 *   - insumos.quantidade_padrao(decimal)     — qtd automática quando fixo
 *   - insumosservicos.tipo_vinculo  (enum)   — fixo/variável POR serviço
 *   - insumosservicos.quantidade_padrao      — qtd padrão POR serviço
 *
 * COMO RODAR: acesse /backend/admin/migrations/004_insumos_fixos_variaveis.php
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/Database.php';

header('Content-Type: text/html; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

$resultados = [];

$migracoes = [
    'insumos.categoria' => [
        'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'insumos' AND COLUMN_NAME = 'categoria'",
        'sql'   => "ALTER TABLE insumos ADD COLUMN categoria VARCHAR(80) DEFAULT NULL AFTER modelo",
        'label' => 'Coluna insumos.categoria'
    ],
    'insumos.tipo_insumo' => [
        'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'insumos' AND COLUMN_NAME = 'tipo_insumo'",
        'sql'   => "ALTER TABLE insumos ADD COLUMN tipo_insumo ENUM('fixo','variavel') NOT NULL DEFAULT 'variavel' AFTER categoria",
        'label' => 'Coluna insumos.tipo_insumo'
    ],
    'insumos.quantidade_padrao' => [
        'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'insumos' AND COLUMN_NAME = 'quantidade_padrao'",
        'sql'   => "ALTER TABLE insumos ADD COLUMN quantidade_padrao DECIMAL(10,3) NOT NULL DEFAULT 1.000 AFTER tipo_insumo",
        'label' => 'Coluna insumos.quantidade_padrao'
    ],
    'insumosservicos.tipo_vinculo' => [
        'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'insumosservicos' AND COLUMN_NAME = 'tipo_vinculo'",
        'sql'   => "ALTER TABLE insumosservicos ADD COLUMN tipo_vinculo ENUM('fixo','variavel') NOT NULL DEFAULT 'variavel' AFTER servicoid",
        'label' => 'Coluna insumosservicos.tipo_vinculo'
    ],
    'insumosservicos.quantidade_padrao' => [
        'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'insumosservicos' AND COLUMN_NAME = 'quantidade_padrao'",
        'sql'   => "ALTER TABLE insumosservicos ADD COLUMN quantidade_padrao DECIMAL(10,3) NOT NULL DEFAULT 1.000 AFTER tipo_vinculo",
        'label' => 'Coluna insumosservicos.quantidade_padrao'
    ],
];

foreach ($migracoes as $key => $m) {
    try {
        $existe = (int) $conn->query($m['check'])->fetchColumn();
        if ($existe) {
            $resultados[] = ['status' => 'skip', 'label' => $m['label'], 'msg' => 'Já existe, pulado.'];
        } else {
            $conn->exec($m['sql']);
            $resultados[] = ['status' => 'ok', 'label' => $m['label'], 'msg' => 'Criado com sucesso.'];
        }
    } catch (Exception $e) {
        $resultados[] = ['status' => 'erro', 'label' => $m['label'], 'msg' => $e->getMessage()];
    }
}

// Tenta popular categoria nos insumos existentes com base na coluna 'categoria' antiga (VARCHAR)
// (o banco atual já tem a coluna 'categoria' em alguns registros legados)
try {
    $conn->exec("
        UPDATE insumosservicos isv
        INNER JOIN insumos i ON i.id = isv.insumoid
        SET isv.tipo_vinculo = 'variavel'
        WHERE isv.tipo_vinculo = 'variavel'
    ");
    $resultados[] = ['status' => 'ok', 'label' => 'Dados padrão insumosservicos', 'msg' => 'tipo_vinculo=variavel aplicado nos vínculos existentes.'];
} catch (Exception $e) {
    $resultados[] = ['status' => 'skip', 'label' => 'Dados padrão insumosservicos', 'msg' => $e->getMessage()];
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
