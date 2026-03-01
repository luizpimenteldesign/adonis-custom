<?php
require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: text/plain; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

echo "=== COLUNAS DA TABELA pre_os ===\n";
$cols = $conn->query("DESCRIBE pre_os")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) echo $c['Field'] . ' [' . $c['Type'] . "]\n";

echo "\n=== COLUNAS DA TABELA clientes ===\n";
$cols2 = $conn->query("DESCRIBE clientes")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols2 as $c) echo $c['Field'] . ' [' . $c['Type'] . "]\n";

echo "\n=== COLUNAS DA TABELA instrumentos ===\n";
$cols3 = $conn->query("DESCRIBE instrumentos")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols3 as $c) echo $c['Field'] . ' [' . $c['Type'] . "]\n";

echo "\n=== PRIMEIRAS 3 LINHAS DE pre_os (SELECT *) ===\n";
$rows = $conn->query("SELECT * FROM pre_os LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) { echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n"; }

echo "\n=== QUERY COMPLETA DO DASHBOARD (LIMIT 5) ===\n";
try {
    $stmt = $conn->query(
        "SELECT p.id, p.status, p.criado_em, p.atualizado_em, p.valor_orcamento, p.prazo_orcamento,
                c.nome as cliente_nome, c.telefone,
                i.tipo as instr_tipo, i.marca as instr_marca, i.modelo as instr_modelo
         FROM pre_os p
         LEFT JOIN clientes c ON p.cliente_id = c.id
         LEFT JOIN instrumentos i ON p.instrumento_id = i.id
         ORDER BY p.atualizado_em DESC LIMIT 5"
    );
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo count($res) . " linhas retornadas\n";
    foreach ($res as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
}

echo "\n=== QUERY COM STATUS=Orcada ===\n";
try {
    $stmt = $conn->prepare("SELECT p.id, p.status, c.nome FROM pre_os p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.status = :s LIMIT 5");
    $stmt->execute([':s' => 'Orcada']);
    $res2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo count($res2) . " linhas\n";
    foreach ($res2 as $r) echo json_encode($r) . "\n";
} catch (Exception $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
}
