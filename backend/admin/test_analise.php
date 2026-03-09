<?php
/**
 * Diagnóstico do erro em analise_insumos.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTE ANALISE_INSUMOS.PHP ===\n\n";

// 1. Testa se o arquivo existe
if (!file_exists('analise_insumos.php')) {
    die("ERRO: analise_insumos.php não encontrado!\n");
}
echo "1. Arquivo existe: OK\n";

// 2. Testa sintaxe PHP
$output = [];
$return = 0;
exec('php -l analise_insumos.php 2>&1', $output, $return);
if ($return !== 0) {
    echo "2. Sintaxe PHP: ERRO\n";
    echo implode("\n", $output) . "\n";
    die();
}
echo "2. Sintaxe PHP: OK\n";

// 3. Testa require de auth.php
if (!file_exists('auth.php')) {
    die("3. auth.php não encontrado!\n");
}
echo "3. auth.php existe: OK\n";

// 4. Testa require de Database.php
if (!file_exists('../config/Database.php')) {
    die("4. ../config/Database.php não encontrado!\n");
}
echo "4. Database.php existe: OK\n";

// 5. Testa se session já está ativa
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "5. Sessão já ativa: OK (ID: ".session_id().")\n";
} else {
    echo "5. Sessão inativa - iniciando...\n";
    session_start();
}

// 6. Testa conexão BD
try {
    require_once '../config/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        echo "6. Conexão BD: OK\n";
    } else {
        die("6. Conexão BD: FALHOU (sem exceção)\n");
    }
} catch (Exception $e) {
    die("6. Conexão BD: ERRO - ".$e->getMessage()."\n");
}

// 7. Testa query simples
try {
    $stmt = $conn->query("SELECT 1");
    echo "7. Query teste: OK\n";
} catch (Exception $e) {
    die("7. Query teste: ERRO - ".$e->getMessage()."\n");
}

// 8. Testa se tabela pre_os existe
try {
    $check = $conn->query("SHOW TABLES LIKE 'pre_os'");
    if ($check->rowCount() > 0) {
        echo "8. Tabela pre_os: OK\n";
    } else {
        die("8. Tabela pre_os: NÃO EXISTE!\n");
    }
} catch (Exception $e) {
    die("8. Erro ao verificar tabela: ".$e->getMessage()."\n");
}

// 9. Testa se pre_os_id=33 existe
try {
    $stmt = $conn->prepare("SELECT id, status FROM pre_os WHERE id = :id");
    $stmt->execute([':id' => 33]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pedido) {
        echo "9. Pedido ID=33: EXISTE (status: ".$pedido['status'].")\n";
    } else {
        echo "9. Pedido ID=33: NÃO ENCONTRADO\n";
    }
} catch (Exception $e) {
    die("9. Erro ao buscar pedido: ".$e->getMessage()."\n");
}

echo "\n=== TODOS OS TESTES PASSARAM ===\n";
echo "O erro 500 deve estar em outro lugar. Verifique o error_log do servidor.\n";
