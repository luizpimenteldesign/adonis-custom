<?php
/**
 * Migration 005 — Limpa vínculos incorretos de servicos_insumos
 *
 * Os 1065 vínculos migrados do legado foram criados sem critério
 * (ex: capacitor cerâmico vinculado a "ajuste de nut").
 *
 * Esta migration limpa TUDO de servicos_insumos para recomeçar
 * do zero via servicos.php com os vínculos corretos.
 *
 * COMO RODAR: acesse /backend/admin/migrations/005_limpar_vinculos_insumos.php
 * ⚠️  ATENÇÃO: esta operação é irreversível.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/Database.php';

header('Content-Type: text/html; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

$resultados = [];

$confirmado = isset($_GET['confirmar']) && $_GET['confirmar'] === 'sim';

if (!$confirmado) {
    // Mostra contagem antes de confirmar
    try {
        $total = $conn->query("SELECT COUNT(*) FROM servicos_insumos")->fetchColumn();
        $resultados[] = ['status'=>'aviso','label'=>'Vínculos encontrados','msg'=> $total . ' vínculos em servicos_insumos. Clique em Confirmar para limpar.'];
    } catch (Exception $e) {
        $resultados[] = ['status'=>'erro','label'=>'Erro','msg'=>$e->getMessage()];
    }
} else {
    try {
        $total_antes = $conn->query("SELECT COUNT(*) FROM servicos_insumos")->fetchColumn();
        $conn->exec("TRUNCATE TABLE servicos_insumos");
        $resultados[] = ['status'=>'ok','label'=>'servicos_insumos limpa','msg'=> $total_antes . ' vínculos removidos com sucesso. Tabela pronta para receber vínculos corretos.'];
    } catch (Exception $e) {
        $resultados[] = ['status'=>'erro','label'=>'Erro ao limpar','msg'=>$e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Migration 005 — Adonis</title>
<style>
body{font-family:monospace;background:#0f1117;color:#e2e8f0;padding:32px;max-width:760px;margin:0 auto}
h2{color:#f97316;margin-bottom:8px}
.sub{color:#94a3b8;font-size:13px;margin-bottom:24px}
.item{display:flex;gap:12px;align-items:flex-start;padding:10px 14px;border-radius:6px;margin-bottom:8px;background:#1e2130}
.badge{padding:3px 10px;border-radius:4px;font-size:12px;font-weight:700;white-space:nowrap}
.ok{background:#16a34a;color:#fff}
.aviso{background:#b45309;color:#fff}
.erro{background:#dc2626;color:#fff}
.label{font-weight:700;color:#94a3b8;font-size:13px}
.msg{font-size:13px;color:#cbd5e1;margin-top:2px}
.footer{margin-top:32px;padding:16px;background:#1e2130;border-radius:8px;color:#94a3b8;font-size:13px;display:flex;gap:16px;flex-wrap:wrap;align-items:center}
.btn{display:inline-block;padding:8px 20px;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer}
.btn-danger{background:#dc2626;color:#fff}
.btn-cancel{background:#334155;color:#e2e8f0}
a{color:#f97316}
</style>
</head>
<body>
<h2>⚠️ Migration 005 — Limpar Vínculos Insumos</h2>
<div class="sub">Remove todos os vínculos incorretos de servicos_insumos para recadastro correto.</div>

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
<?php if (!$confirmado): ?>
    <span style="color:#fbbf24">&#9888; Esta ação não pode ser desfeita.</span>
    <a href="?confirmar=sim" class="btn btn-danger">Confirmar e Limpar</a>
    <a href="../insumos.php" class="btn btn-cancel">Cancelar</a>
<?php else: ?>
    <span>Concluído.</span>
    <a href="../insumos.php" style="color:#f97316">← Voltar para Insumos</a>
    <a href="../servicos.php" style="color:#f97316">→ Ir para Serviços</a>
<?php endif; ?>
</div>
</body>
</html>
