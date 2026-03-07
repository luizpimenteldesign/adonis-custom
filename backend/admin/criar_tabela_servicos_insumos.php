<?php
/**
 * MIGRAÇÃO: Cria tabela servicos_insumos
 * Execute uma vez e delete este arquivo
 */
require_once 'auth.php';
require_once '../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

$resultado = [];

// 1. Criar tabela servicos_insumos
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS servicos_insumos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            servico_id INT NOT NULL,
            insumo_id INT NOT NULL,
            quantidade_padrao DECIMAL(10,3) DEFAULT 1.000,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_servico_insumo (servico_id, insumo_id),
            FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE CASCADE,
            FOREIGN KEY (insumo_id) REFERENCES insumos(id) ON DELETE CASCADE,
            INDEX idx_servico (servico_id),
            INDEX idx_insumo (insumo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $resultado[] = "✅ Tabela 'servicos_insumos' criada com sucesso";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        $resultado[] = "ℹ️ Tabela 'servicos_insumos' já existe";
    } else {
        $resultado[] = "❌ Erro ao criar tabela 'servicos_insumos': " . $e->getMessage();
    }
}

// 2. Verificar se a tabela antiga insumos_servicos existe e migrar dados
try {
    $check = $conn->query("SHOW TABLES LIKE 'insumos_servicos'")->fetch();
    
    if ($check) {
        $resultado[] = "ℹ️ Tabela antiga 'insumos_servicos' encontrada";
        
        // Verificar estrutura da tabela antiga
        $cols = $conn->query("SHOW COLUMNS FROM insumos_servicos")->fetchAll(PDO::FETCH_ASSOC);
        $temServicoId = false;
        $temInsumoId = false;
        
        foreach ($cols as $col) {
            if ($col['Field'] === 'servico_id') $temServicoId = true;
            if ($col['Field'] === 'insumo_id') $temInsumoId = true;
        }
        
        if ($temServicoId && $temInsumoId) {
            // Migrar dados da tabela antiga para a nova
            $migrados = $conn->exec("
                INSERT IGNORE INTO servicos_insumos (servico_id, insumo_id, quantidade_padrao)
                SELECT servico_id, insumo_id, COALESCE(quantidade_padrao, 1.000)
                FROM insumos_servicos
            ");
            
            if ($migrados > 0) {
                $resultado[] = "✅ {$migrados} vínculos migrados de 'insumos_servicos' para 'servicos_insumos'";
            } else {
                $resultado[] = "ℹ️ Nenhum dado novo para migrar (pode já ter sido migrado anteriormente)";
            }
        } else {
            $resultado[] = "⚠️ Tabela 'insumos_servicos' tem estrutura diferente - migração manual necessária";
        }
    } else {
        $resultado[] = "ℹ️ Tabela antiga 'insumos_servicos' não encontrada (OK)";
    }
} catch (PDOException $e) {
    $resultado[] = "⚠️ Erro ao verificar tabela antiga: " . $e->getMessage();
}

// 3. Verificar se a tabela insumos existe
try {
    $check = $conn->query("SHOW TABLES LIKE 'insumos'")->fetch();
    if (!$check) {
        $resultado[] = "❌ ATENÇÃO: Tabela 'insumos' não existe! Crie-a primeiro.";
    } else {
        $resultado[] = "✅ Tabela 'insumos' encontrada";
    }
} catch (PDOException $e) {
    $resultado[] = "❌ Erro ao verificar tabela 'insumos': " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração - Servicos Insumos</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 700px;
            margin: 60px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .box {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            margin: 0 0 10px;
            font-size: 24px;
            color: #1a1a1a;
        }
        .subtitle {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .result {
            padding: 12px 16px;
            margin: 8px 0;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
        }
        .result:first-of-type {
            margin-top: 20px;
        }
        .success { background: #e6f4ea; color: #1e8e3e; border-left: 4px solid #1e8e3e; }
        .info { background: #e8f0fe; color: #1967d2; border-left: 4px solid #1967d2; }
        .warning { background: #fef7e0; color: #f9ab00; border-left: 4px solid #f9ab00; }
        .error { background: #fce8e6; color: #c5221f; border-left: 4px solid #c5221f; }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 13px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #7c3aed;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 12px;
            transition: opacity 0.15s;
        }
        .btn:hover { opacity: 0.85; }
    </style>
</head>
<body>
    <div class="box">
        <h1>✅ Migração Concluída</h1>
        <div class="subtitle">Tabela servicos_insumos criada com sucesso</div>

        <?php foreach ($resultado as $msg):
            $classe = 'info';
            if (strpos($msg, '✅') !== false) $classe = 'success';
            elseif (strpos($msg, '⚠️') !== false) $classe = 'warning';
            elseif (strpos($msg, '❌') !== false) $classe = 'error';
        ?>
        <div class="result <?php echo $classe; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>

        <div class="footer">
            <strong>Próximos passos:</strong><br>
            1. Feche esta página<br>
            2. Acesse a página de Serviços para vincular insumos<br>
            3. Delete este arquivo: <code>backend/admin/criar_tabela_servicos_insumos.php</code>
            
            <a href="servicos.php" class="btn">Ir para Serviços →</a>
        </div>
    </div>
</body>
</html>
