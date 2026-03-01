<?php
/**
 * MIGRATION — corrige banco para versão atual do sistema Adonis
 * Acesse UMA vez pelo navegador logado, depois apague o arquivo.
 */
require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: text/plain; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

$steps = [];

// 1. Adiciona prazo_orcamento se não existir
try {
    $conn->query("ALTER TABLE pre_os ADD COLUMN prazo_orcamento INT(11) NULL DEFAULT NULL AFTER valor_orcamento");
    $steps[] = "[OK] Coluna prazo_orcamento adicionada";
} catch (\PDOException $e) {
    $steps[] = "[SKIP] prazo_orcamento: " . $e->getMessage();
}

// 2. Corrige o ENUM do status para incluir todos os estados
try {
    $conn->query("ALTER TABLE pre_os MODIFY COLUMN status ENUM(
        'Pre-OS',
        'Em analise',
        'Orcada',
        'Aguardando aprovacao',
        'Aprovada',
        'Pagamento recebido',
        'Instrumento recebido',
        'Servico iniciado',
        'Em desenvolvimento',
        'Servico finalizado',
        'Pronto para retirada',
        'Aguardando pagamento retirada',
        'Entregue',
        'Reprovada',
        'Cancelada'
    ) NOT NULL DEFAULT 'Pre-OS'");
    $steps[] = "[OK] ENUM status atualizado com todos os estados";
} catch (\PDOException $e) {
    $steps[] = "[ERRO] ENUM: " . $e->getMessage();
}

// 3. Verifica se tabela pre_os_servicos existe (para totais de serviços)
try {
    $conn->query("SELECT 1 FROM pre_os_servicos LIMIT 1");
    $steps[] = "[OK] Tabela pre_os_servicos existe";
} catch (\PDOException $e) {
    // Cria tabela caso não exista
    $conn->query("CREATE TABLE IF NOT EXISTS pre_os_servicos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pre_os_id INT NOT NULL,
        servico_id INT NOT NULL,
        criado_em DATETIME DEFAULT NOW(),
        FOREIGN KEY (pre_os_id) REFERENCES pre_os(id) ON DELETE CASCADE
    )");
    $steps[] = "[OK] Tabela pre_os_servicos criada";
}

// 4. Testa a query principal do dashboard
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
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $steps[] = "[OK] Query do dashboard OK — " . count($rows) . " linha(s) retornada(s)";
    foreach ($rows as $r) $steps[] = "  > #" . $r['id'] . " [" . $r['status'] . "] " . ($r['cliente_nome'] ?? 'sem nome');
} catch (\PDOException $e) {
    $steps[] = "[ERRO] Query dashboard: " . $e->getMessage();
}

echo implode("\n", $steps) . "\n\nMigration concluída. Pode apagar este arquivo.\n";
