<?php
require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ─────────────────────────────────────────────────────────────────────────────
// GET ?categorias_insumo=1  — lista categorias distintas de insumos
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['categorias_insumo'])) {
    try {
        $rows = $conn->query(
            "SELECT DISTINCT categoria FROM insumos WHERE ativo = 1 AND categoria IS NOT NULL AND categoria != '' ORDER BY categoria"
        )->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['ok' => true, 'categorias' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// GET ?categorias=1  — categorias de SERVIÇOS (compatibilidade)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['categorias'])) {
    try {
        $rows = $conn->query(
            "SELECT DISTINCT categoria FROM servicos WHERE ativo = 1 AND categoria IS NOT NULL AND categoria != '' ORDER BY categoria"
        )->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['ok' => true, 'categorias' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// GET ?categoria=X  — serviços de uma categoria específica
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['categoria'])) {
    try {
        $stmt = $conn->prepare("SELECT id, nome FROM servicos WHERE ativo = 1 AND categoria = ? ORDER BY nome");
        $stmt->execute([$_GET['categoria']]);
        echo json_encode(['ok' => true, 'servicos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// GET ?por_servicos=1&servicos_ids[]=1&servicos_ids[]=2
// Retorna insumos vinculados a um conjunto de serviços separados em fixos/variáveis
// Usado pelo modal de orçamento em detalhes.php
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['por_servicos'])) {
    $ids = isset($_GET['servicos_ids']) && is_array($_GET['servicos_ids'])
        ? array_map('intval', $_GET['servicos_ids'])
        : [];

    if (empty($ids)) {
        echo json_encode(['ok' => true, 'fixos' => [], 'variaveis' => []]);
        exit;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "
            SELECT
                i.id,
                i.nome,
                i.marca,
                i.modelo,
                i.categoria,
                i.unidade,
                i.valorunitario,
                i.quantidadeestoque,
                i.tipo_insumo,
                si.tipo_vinculo,
                si.quantidade_padrao,
                si.servico_id,
                s.nome AS servico_nome
            FROM servicos_insumos si
            INNER JOIN insumos i ON i.id = si.insumo_id AND i.ativo = 1
            INNER JOIN servicos s ON s.id = si.servico_id
            WHERE si.servico_id IN ($placeholders)
            ORDER BY si.tipo_vinculo DESC, i.categoria, i.nome
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fixos     = array_values(array_filter($rows, fn($r) => ($r['tipo_vinculo'] ?? 'variavel') === 'fixo'));
        $variaveis = array_values(array_filter($rows, fn($r) => ($r['tipo_vinculo'] ?? 'variavel') === 'variavel'));

        echo json_encode(['ok' => true, 'fixos' => $fixos, 'variaveis' => $variaveis]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// GET  — listar todos ou buscar um por ID
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM insumos WHERE id = ?");
            $stmt->execute([$id]);
            $insumo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$insumo) {
                echo json_encode(['ok' => false, 'erro' => 'Nao encontrado']);
                exit;
            }
            // Vínculos detalhados com servicos_insumos
            $stmt2 = $conn->prepare(
                "SELECT servico_id, tipo_vinculo, quantidade_padrao FROM servicos_insumos WHERE insumo_id = ?"
            );
            $stmt2->execute([$id]);
            $vinculos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $insumo['servicos'] = array_column($vinculos, 'servico_id'); // compatibilidade
            $insumo['vinculos'] = $vinculos; // [{servico_id, tipo_vinculo, quantidade_padrao}]
            echo json_encode(['ok' => true, 'insumo' => $insumo]);
        } else {
            $q   = isset($_GET['q'])   ? trim($_GET['q'])   : '';
            $cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';
            $sql = "
                SELECT
                    i.*,
                    GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ') AS servicos_nomes
                FROM insumos i
                LEFT JOIN servicos_insumos si ON si.insumo_id = i.id
                LEFT JOIN servicos s ON s.id = si.servico_id
            ";
            $where  = [];
            $params = [];
            if ($q) {
                $where[]  = '(i.nome LIKE ? OR i.marca LIKE ? OR i.modelo LIKE ? OR i.categoria LIKE ?)';
                $qlike    = '%' . $q . '%';
                $params   = array_merge($params, [$qlike, $qlike, $qlike, $qlike]);
            }
            if ($cat) {
                $where[]  = 'i.categoria = ?';
                $params[] = $cat;
            }
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' GROUP BY i.id ORDER BY i.categoria, i.nome';
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['ok' => true, 'insumos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — criar insumo
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data        = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $nome        = trim($data['nome']      ?? '');
    $marca       = trim($data['marca']     ?? '');
    $modelo      = trim($data['modelo']    ?? '');
    $categoria   = trim($data['categoria'] ?? '');
    $unidade     = trim($data['unidade']   ?? '');
    $valor       = (float) str_replace(',', '.', $data['valorunitario']     ?? 0);
    $estoque     = (float) str_replace(',', '.', $data['quantidadeestoque'] ?? 0);
    $tipo_insumo = in_array($data['tipo_insumo'] ?? '', ['fixo','variavel']) ? $data['tipo_insumo'] : 'variavel';
    $qtd_padrao  = (float) str_replace(',', '.', $data['quantidade_padrao'] ?? 1);
    $ativo       = isset($data['ativo']) ? (int)$data['ativo'] : 1;
    // vinculos: [{servico_id, tipo_vinculo, quantidade_padrao}] OU array simples de IDs (legado)
    $vinculos_raw    = isset($data['vinculos'])  && is_array($data['vinculos'])  ? $data['vinculos']  : [];
    $servicos_legado = isset($data['servicos'])  && is_array($data['servicos'])  ? $data['servicos']  : [];

    if (!$nome || !$unidade) {
        echo json_encode(['ok' => false, 'erro' => 'Nome e unidade sao obrigatorios']);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO insumos
                (nome, marca, modelo, categoria, unidade, valorunitario, quantidadeestoque, tipo_insumo, quantidade_padrao, ativo)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([$nome, $marca ?: null, $modelo ?: null, $categoria ?: null, $unidade, $valor, $estoque, $tipo_insumo, $qtd_padrao, $ativo]);
        $novo_id = $conn->lastInsertId();

        if ($vinculos_raw) {
            $ins = $conn->prepare("
                INSERT IGNORE INTO servicos_insumos (servico_id, insumo_id, tipo_vinculo, quantidade_padrao)
                VALUES (?,?,?,?)
            ");
            foreach ($vinculos_raw as $v) {
                $sid  = (int)($v['servico_id'] ?? $v['servicoid'] ?? $v);
                $tipo = in_array($v['tipo_vinculo'] ?? '', ['fixo','variavel']) ? $v['tipo_vinculo'] : 'variavel';
                $qtd  = (float)($v['quantidade_padrao'] ?? 1);
                $ins->execute([$sid, $novo_id, $tipo, $qtd]);
            }
        } elseif ($servicos_legado) {
            $ins = $conn->prepare(
                "INSERT IGNORE INTO servicos_insumos (servico_id, insumo_id, tipo_vinculo, quantidade_padrao) VALUES (?,'variavel',1)"
            );
            // legado: array simples de IDs
            $ins2 = $conn->prepare(
                "INSERT IGNORE INTO servicos_insumos (servico_id, insumo_id, tipo_vinculo, quantidade_padrao) VALUES (?,?,'variavel',1)"
            );
            foreach ($servicos_legado as $sid) $ins2->execute([(int)$sid, $novo_id]);
        }

        echo json_encode(['ok' => true, 'id' => $novo_id]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PUT — editar insumo
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) { echo json_encode(['ok' => false, 'erro' => 'ID obrigatorio']); exit; }

    $data        = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome        = trim($data['nome']      ?? '');
    $marca       = trim($data['marca']     ?? '');
    $modelo      = trim($data['modelo']    ?? '');
    $categoria   = trim($data['categoria'] ?? '');
    $unidade     = trim($data['unidade']   ?? '');
    $valor       = (float) str_replace(',', '.', $data['valorunitario']     ?? 0);
    $estoque     = (float) str_replace(',', '.', $data['quantidadeestoque'] ?? 0);
    $tipo_insumo = in_array($data['tipo_insumo'] ?? '', ['fixo','variavel']) ? $data['tipo_insumo'] : 'variavel';
    $qtd_padrao  = (float) str_replace(',', '.', $data['quantidade_padrao'] ?? 1);
    $ativo       = isset($data['ativo']) ? (int)$data['ativo'] : 1;
    $vinculos_raw    = isset($data['vinculos'])  && is_array($data['vinculos'])  ? $data['vinculos']  : [];
    $servicos_legado = isset($data['servicos'])  && is_array($data['servicos'])  ? $data['servicos']  : [];

    // Reativação rápida (legado)
    if ($nome === '_reativar_' && $unidade === '_reativar_') {
        try {
            $conn->prepare("UPDATE insumos SET ativo = 1 WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
        }
        exit;
    }

    if (!$nome || !$unidade) {
        echo json_encode(['ok' => false, 'erro' => 'Nome e unidade sao obrigatorios']);
        exit;
    }

    try {
        $conn->prepare("
            UPDATE insumos
            SET nome=?, marca=?, modelo=?, categoria=?, unidade=?, valorunitario=?,
                quantidadeestoque=?, tipo_insumo=?, quantidade_padrao=?, ativo=?
            WHERE id=?
        ")->execute([$nome, $marca ?: null, $modelo ?: null, $categoria ?: null, $unidade, $valor, $estoque, $tipo_insumo, $qtd_padrao, $ativo, $id]);

        // Recria vínculos
        $conn->prepare("DELETE FROM servicos_insumos WHERE insumo_id = ?")->execute([$id]);

        if ($vinculos_raw) {
            $ins = $conn->prepare("
                INSERT IGNORE INTO servicos_insumos (servico_id, insumo_id, tipo_vinculo, quantidade_padrao)
                VALUES (?,?,?,?)
            ");
            foreach ($vinculos_raw as $v) {
                $sid  = (int)($v['servico_id'] ?? $v['servicoid'] ?? $v);
                $tipo = in_array($v['tipo_vinculo'] ?? '', ['fixo','variavel']) ? $v['tipo_vinculo'] : 'variavel';
                $qtd  = (float)($v['quantidade_padrao'] ?? 1);
                $ins->execute([$sid, $id, $tipo, $qtd]);
            }
        } elseif ($servicos_legado) {
            $ins = $conn->prepare(
                "INSERT IGNORE INTO servicos_insumos (servico_id, insumo_id, tipo_vinculo, quantidade_padrao) VALUES (?,?,'variavel',1)"
            );
            foreach ($servicos_legado as $sid) $ins->execute([(int)$sid, $id]);
        }

        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE — desativar ou excluir
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) { echo json_encode(['ok' => false, 'erro' => 'ID obrigatorio']); exit; }
    $excluir = isset($_GET['excluir']) && $_GET['excluir'] == '1';
    try {
        if ($excluir) {
            $conn->prepare("DELETE FROM servicos_insumos WHERE insumo_id = ?")->execute([$id]);
            $conn->prepare("DELETE FROM insumos WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true, 'excluido' => true]);
        } else {
            $conn->prepare("UPDATE insumos SET ativo = 0 WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true, 'excluido' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'erro' => 'Metodo nao suportado']);
