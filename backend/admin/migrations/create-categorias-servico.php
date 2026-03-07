<?php
/**
 * Migração: cria tabelas categorias_servico e servico_categorias
 * Executar UMA vez via browser: /backend/admin/migrations/create-categorias-servico.php
 */
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/../config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$resultados = [];

// 1. Tabela categorias_servico
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS categorias_servico (
        id   INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $resultados[] = ['ok' => true, 'msg' => 'Tabela categorias_servico criada (ou já existia).'];
} catch (Exception $e) {
    $resultados[] = ['ok' => false, 'msg' => 'categorias_servico: ' . $e->getMessage()];
}

// 2. Tabela pivô servico_categorias
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS servico_categorias (
        servico_id   INT NOT NULL,
        categoria_id INT NOT NULL,
        PRIMARY KEY (servico_id, categoria_id),
        FOREIGN KEY (servico_id)   REFERENCES servicos(id) ON DELETE CASCADE,
        FOREIGN KEY (categoria_id) REFERENCES categorias_servico(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $resultados[] = ['ok' => true, 'msg' => 'Tabela servico_categorias criada (ou já existia).'];
} catch (Exception $e) {
    $resultados[] = ['ok' => false, 'msg' => 'servico_categorias: ' . $e->getMessage()];
}

// 3. Seed das 3 categorias
$cats = ['Reparo', 'Customização', 'Construção'];
foreach ($cats as $cat) {
    $existe = $conn->prepare("SELECT id FROM categorias_servico WHERE nome = ?");
    $existe->execute([$cat]);
    if (!$existe->fetch()) {
        try {
            $conn->prepare("INSERT INTO categorias_servico (nome) VALUES (?)")->execute([$cat]);
            $resultados[] = ['ok' => true, 'msg' => "Categoria '{$cat}' inserida."];
        } catch (Exception $e) {
            $resultados[] = ['ok' => false, 'msg' => "Categoria '{$cat}': " . $e->getMessage()];
        }
    } else {
        $resultados[] = ['ok' => true, 'msg' => "Categoria '{$cat}' já existe."];
    }
}

echo '<pre style="font-family:monospace;font-size:14px">';
foreach ($resultados as $r) {
    $icon = $r['ok'] ? '&#10003;' : '&#10007;';
    $cor  = $r['ok'] ? 'green' : 'red';
    echo "<span style='color:{$cor}'>{$icon} {$r['msg']}</span>\n";
}
echo '</pre>';
echo '<p><a href=\"../servicos.php\">Voltar para Serviços</a></p>';
