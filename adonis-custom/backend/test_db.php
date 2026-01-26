<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Teste de Conexão - Adonis Custom</h3>";

// Teste 1: Carregar config
echo "<p>1. Carregando config.php... ";
require_once __DIR__ . '/config/config.php';
echo "✅ OK</p>";

// Teste 2: Carregar Database
echo "<p>2. Carregando Database.php... ";
require_once __DIR__ . '/models/Database.php';
echo "✅ OK</p>";

// Teste 3: Conectar ao banco
echo "<p>3. Conectando ao banco... ";
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "✅ CONECTADO!</p>";
        
        // Teste 4: Listar tabelas
        echo "<p>4. Tabelas no banco:</p><ul>";
        $stmt = $conn->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
        
        // Teste 5: Contar serviços
        echo "<p>5. Serviços cadastrados: ";
        $stmt = $conn->query("SELECT COUNT(*) as total FROM servicos");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<strong>" . $result['total'] . "</strong></p>";
        
        // Teste 6: Listar serviços
        if ($result['total'] > 0) {
            echo "<p>6. Lista de serviços:</p><ul>";
            $stmt = $conn->query("SELECT id, nome, valor_base FROM servicos");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<li>#{$row['id']} - {$row['nome']} - R$ {$row['valor_base']}</li>";
            }
            echo "</ul>";
        }
        
        echo "<hr><h3 style='color: green;'>✅ TUDO FUNCIONANDO!</h3>";
        
    } else {
        echo "❌ Falha na conexão</p>";
    }
} catch (Exception $e) {
    echo "❌ ERRO:</p>";
    echo "<pre style='background: #fee; padding: 15px; border: 2px solid red;'>";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine();
    echo "</pre>";
}
?>
