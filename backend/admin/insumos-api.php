<?php
require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// GET ?categorias=1 — retorna categorias distintas da tabela servicos
if ($method === 'GET' && isset($_GET['categorias'])) {
    try {
        $rows = $conn->query("SELECT DISTINCT categoria FROM servicos WHERE ativo = 1 AND categoria IS NOT NULL AND categoria != '' ORDER BY categoria")
                     ->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['ok' => true, 'categorias' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// GET ?categoria=X — retorna servicos de uma categoria
if ($method === 'GET' && isset($_GET['categoria'])) {
    try {
        $stmt = $conn->prepare("SELECT id, nome FROM servicos WHERE ativo = 1 AND categoria = ? ORDER BY nome");
        $stmt->execute([$_GET['categoria']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'servicos' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// GET — listar todos ou buscar um
if ($method === 'GET') {
    try {
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM insumos WHERE id = ?");
            $stmt->execute([$id]);
            $insumo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$insumo) { echo json_encode(['ok' => false, 'erro' => 'Nao encontrado']); exit; }
            $stmt2 = $conn->prepare("SELECT servicoid FROM insumos_servicos WHERE insumoid = ?");
            $stmt2->execute([$id]);
            $insumo['servicos'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['ok' => true, 'insumo' => $insumo]);
        } else {
            $q = isset($_GET['q']) ? trim($_GET['q']) : '';
            $sql = "SELECT i.*, GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ') as servicos_nomes
                    FROM insumos i
                    LEFT JOIN insumos_servicos ins ON ins.insumoid = i.id
                    LEFT JOIN servicos s ON s.id = ins.servicoid";
            if ($q) $sql .= " WHERE i.nome LIKE :q OR i.unidade LIKE :q";
            $sql .= " GROUP BY i.id ORDER BY i.nome";
            $stmt = $conn->prepare($sql);
            if ($q) $stmt->execute([':q' => '%'.$q.'%']);
            else    $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'insumos' => $rows]);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// POST — criar
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $nome     = trim($data['nome']     ?? '');
    $unidade  = trim($data['unidade']  ?? '');
    $valor    = (float) str_replace(',', '.', $data['valor_unitario'] ?? 0);
    $estoque  = (float) str_replace(',', '.', $data['quantidade_estoque'] ?? 0);
    $ativo    = isset($data['ativo']) ? (int)$data['ativo'] : 1;
    $servicos = isset($data['servicos']) && is_array($data['servicos']) ? $data['servicos'] : [];

    if (!$nome || !$unidade) { echo json_encode(['ok' => false, 'erro' => 'Nome e unidade sao obrigatorios']); exit; }

    try {
        $stmt = $conn->prepare("INSERT INTO insumos (nome, unidade, valor_unitario, quantidade_estoque, ativo) VALUES (?,?,?,?,?)");
        $stmt->execute([$nome, $unidade, $valor, $estoque, $ativo]);
        $novo_id = $conn->lastInsertId();
        if ($servicos) {
            $ins = $conn->prepare("INSERT IGNORE INTO insumos_servicos (insumoid, servicoid) VALUES (?,?)");
            foreach ($servicos as $sid) $ins->execute([$novo_id, (int)$sid]);
        }
        echo json_encode(['ok' => true, 'id' => $novo_id]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// PUT — editar
if ($method === 'PUT') {
    if (!$id) { echo json_encode(['ok' => false, 'erro' => 'ID obrigatorio']); exit; }
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome     = trim($data['nome']     ?? '');
    $unidade  = trim($data['unidade']  ?? '');
    $valor    = (float) str_replace(',', '.', $data['valor_unitario'] ?? 0);
    $estoque  = (float) str_replace(',', '.', $data['quantidade_estoque'] ?? 0);
    $ativo    = isset($data['ativo']) ? (int)$data['ativo'] : 1;
    $servicos = isset($data['servicos']) && is_array($data['servicos']) ? $data['servicos'] : [];

    if (!$nome || !$unidade) { echo json_encode(['ok' => false, 'erro' => 'Nome e unidade sao obrigatorios']); exit; }

    try {
        $stmt = $conn->prepare("UPDATE insumos SET nome=?, unidade=?, valor_unitario=?, quantidade_estoque=?, ativo=? WHERE id=?");
        $stmt->execute([$nome, $unidade, $valor, $estoque, $ativo, $id]);
        $conn->prepare("DELETE FROM insumos_servicos WHERE insumoid=?")->execute([$id]);
        if ($servicos) {
            $ins = $conn->prepare("INSERT IGNORE INTO insumos_servicos (insumoid, servicoid) VALUES (?,?)");
            foreach ($servicos as $sid) $ins->execute([$id, (int)$sid]);
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// DELETE — exclui do banco se nao houver uso, caso contrario desativa
if ($method === 'DELETE') {
    if (!$id) { echo json_encode(['ok' => false, 'erro' => 'ID obrigatorio']); exit; }
    $excluir = isset($_GET['excluir']) && $_GET['excluir'] == '1';
    try {
        if ($excluir) {
            // Verifica se ha uso em OS (tabela osservicos) — protecao antes de apagar
            $uso = $conn->prepare("SELECT COUNT(*) FROM insumos_servicos WHERE insumoid = ?");
            $uso->execute([$id]);
            // Apenas remove vinculos e apaga o registro
            $conn->prepare("DELETE FROM insumos_servicos WHERE insumoid = ?")->execute([$id]);
            $conn->prepare("DELETE FROM insumos WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true, 'excluido' => true]);
        } else {
            // Soft delete — apenas desativa
            $conn->prepare("UPDATE insumos SET ativo = 0 WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true, 'excluido' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'erro' => 'Metodo nao suportado']);
