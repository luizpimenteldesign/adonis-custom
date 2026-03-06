<?php
require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$resultados = [];

// Tabela insumos
try {
    $db->exec("CREATE TABLE IF NOT EXISTS insumos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        unidade VARCHAR(30) NOT NULL,
        valor_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        quantidade_estoque DECIMAL(10,3) NOT NULL DEFAULT 0,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criadoem DATETIME DEFAULT CURRENT_TIMESTAMP,
        atualizadoem DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $resultados[] = ['ok' => true, 'msg' => 'Tabela insumos criada com sucesso'];
} catch (Exception $e) {
    $resultados[] = ['ok' => false, 'msg' => 'Tabela insumos: ' . $e->getMessage()];
}

// Tabela insumos_servicos (pivot N:N)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS insumos_servicos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        insumoid INT NOT NULL,
        servicoid INT NOT NULL,
        UNIQUE KEY unico (insumoid, servicoid),
        FOREIGN KEY (insumoid) REFERENCES insumos(id) ON DELETE CASCADE,
        FOREIGN KEY (servicoid) REFERENCES servicos(id) ON DELETE CASCADE
    )");
    $resultados[] = ['ok' => true, 'msg' => 'Tabela insumos_servicos criada com sucesso'];
} catch (Exception $e) {
    $resultados[] = ['ok' => false, 'msg' => 'Tabela insumos_servicos: ' . $e->getMessage()];
}

// Inserir 2 exemplos apenas se a tabela estiver vazia
try {
    $count = $db->query("SELECT COUNT(*) FROM insumos")->fetchColumn();
    if ($count == 0) {
        $db->exec("INSERT INTO insumos (nome, unidade, valor_unitario, quantidade_estoque) VALUES
            ('Encordamento para Guitarra 09-42', 'conjunto', 35.00, 12.000),
            ('Solda 60/40 Fio 0.8mm', 'metro', 4.50, 50.000)
        ");
        $resultados[] = ['ok' => true, 'msg' => '2 insumos de exemplo inseridos'];
    } else {
        $resultados[] = ['ok' => null, 'msg' => 'Tabela insumos ja possui dados — exemplos nao inseridos'];
    }
} catch (Exception $e) {
    $resultados[] = ['ok' => false, 'msg' => 'Exemplos: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Migração — Insumos</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <style>
        body { font-family: sans-serif; background: #f8f9fa; padding: 40px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; max-width: 520px; margin: 0 auto; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        h2 { margin: 0 0 20px; font-size: 18px; }
        .item { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .item:last-child { border-bottom: none; }
        .icon-ok   { color: #1a73e8; }
        .icon-err  { color: #d93025; }
        .icon-info { color: #f9ab00; }
        .footer { margin-top: 20px; font-size: 13px; color: #666; }
    </style>
</head>
<body>
<div class="card">
    <h2>Migracao — Insumos</h2>
    <?php foreach ($resultados as $r): ?>
    <div class="item">
        <?php if ($r['ok'] === true): ?>
            <span class="material-symbols-outlined icon-ok">check_circle</span>
        <?php elseif ($r['ok'] === false): ?>
            <span class="material-symbols-outlined icon-err">error</span>
        <?php else: ?>
            <span class="material-symbols-outlined icon-info">info</span>
        <?php endif; ?>
        <?php echo htmlspecialchars($r['msg']); ?>
    </div>
    <?php endforeach; ?>
    <p class="footer">Concluido. Voce pode fechar esta pagina.</p>
</div>
</body>
</html>
