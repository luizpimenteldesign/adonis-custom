<?php
/**
 * API: Análise de insumos de uma Pré-OS
 * GET  ?pre_os_id=X                 → categorias + insumos fixos pré-selecionados
 * GET  ?pre_os_id=X&categoria=Y&q=Z → lista insumos da categoria para seleção manual
 * POST                              → salva insumos confirmados em pre_os_insumos
 * 
 * v2 — Nunca dispara HTTP 500: tratamento global + LEFT JOINs + validação de estrutura BD
 */

// Tratamento global de erros — garante JSON sempre
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['sucesso'=>false,'erro'=>"Erro PHP: $errstr (linha $errline)"]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['sucesso'=>false,'erro'=>'Exceção: '.$e->getMessage()]);
    exit;
});

header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'auth.php';
    require_once '../config/Database.php';
} catch (Exception $e) {
    echo json_encode(['sucesso'=>false,'erro'=>'Erro ao carregar dependências: '.$e->getMessage()]);
    exit;
}

$db   = new Database();
$conn = $db->getConnection();

if (!$conn) {
    echo json_encode(['sucesso'=>false,'erro'=>'Falha na conexão com banco de dados']);
    exit;
}

// ── GET: lista insumos de uma categoria ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['categoria'])) {
    $categoria = trim($_GET['categoria']);
    $busca     = trim($_GET['q'] ?? '');
    $pre_os_id = (int)($_GET['pre_os_id'] ?? 0);

    if (!$pre_os_id) { echo json_encode(['sucesso'=>false,'erro'=>'ID inválido']); exit; }

    $sql = "
        SELECT DISTINCT i.id, i.nome, i.unidade, i.valorunitario, i.estoque,
               i.tipo_insumo, i.categoria
        FROM insumos i
        INNER JOIN servicos_insumos si ON si.insumo_id = i.id
        INNER JOIN pre_os_servicos pos ON pos.servico_id = si.servico_id
        WHERE pos.pre_os_id = :pre_os_id
    ";
    $params = [':pre_os_id' => $pre_os_id];

    // Coluna 'ativo' é opcional — se não existir, ignora
    try {
        $test = $conn->query("SHOW COLUMNS FROM insumos LIKE 'ativo'");
        if ($test->rowCount() > 0) $sql .= " AND i.ativo = 1";
    } catch (Exception $e) {}

    if ($categoria !== 'Todos') {
        $sql .= ' AND i.categoria = :cat';
        $params[':cat'] = $categoria;
    }
    if ($busca) {
        $sql .= ' AND i.nome LIKE :busca';
        $params[':busca'] = '%'.$busca.'%';
    }
    $sql .= ' ORDER BY i.nome LIMIT 100';

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso'=>true,'insumos'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo json_encode(['sucesso'=>false,'erro'=>'Erro ao buscar insumos: '.$e->getMessage()]);
    }
    exit;
}

// ── GET: dados iniciais do pedido ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pre_os_id = (int)($_GET['pre_os_id'] ?? 0);
    if (!$pre_os_id) { echo json_encode(['erro'=>'ID inválido']); exit; }

    try {
        // LEFT JOIN em instrumento — pode não existir
        $stmt = $conn->prepare("
            SELECT p.id, p.status, p.observacoes,
                   c.nome AS cliente_nome,
                   i.tipo AS instrumento_tipo, i.marca AS instrumento_marca, i.modelo AS instrumento_modelo
            FROM pre_os p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN instrumentos i ON p.instrumento_id = i.id
            WHERE p.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $pre_os_id]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pedido) { echo json_encode(['erro'=>'Pedido não encontrado']); exit; }

        // Serviços
        $stmt_s = $conn->prepare("
            SELECT s.id, s.nome FROM pre_os_servicos ps
            JOIN servicos s ON ps.servico_id = s.id
            WHERE ps.pre_os_id = :id
        ");
        $stmt_s->execute([':id' => $pre_os_id]);
        $servicos = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

        // Categorias dos insumos vinculados aos serviços
        $categorias = [];
        $servico_ids = array_column($servicos, 'id');
        if (!empty($servico_ids)) {
            $ph = implode(',', array_fill(0, count($servico_ids), '?'));
            $sql_cat = "
                SELECT DISTINCT i.categoria
                FROM insumos i
                INNER JOIN servicos_insumos si ON si.insumo_id = i.id
                WHERE si.servico_id IN ($ph)
                  AND i.categoria IS NOT NULL AND i.categoria != ''
            ";
            // Adiciona filtro 'ativo' se coluna existir
            try {
                $test = $conn->query("SHOW COLUMNS FROM insumos LIKE 'ativo'");
                if ($test->rowCount() > 0) $sql_cat .= " AND i.ativo=1";
            } catch (Exception $e) {}
            $sql_cat .= " ORDER BY i.categoria";

            try {
                $stmt_cat = $conn->prepare($sql_cat);
                $stmt_cat->execute($servico_ids);
                $categorias = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {}
        }
        array_unshift($categorias, 'Todos');

        // Insumos FIXOS pré-selecionados (tipo_vinculo = 'fixo')
        $insumos_fixos = [];
        if (!empty($servico_ids)) {
            $ph = implode(',', array_fill(0, count($servico_ids), '?'));
            
            // Verifica se coluna quantidade_padrao existe
            $usa_qtd_padrao = false;
            try {
                $test = $conn->query("SHOW COLUMNS FROM servicos_insumos LIKE 'quantidade_padrao'");
                $usa_qtd_padrao = ($test->rowCount() > 0);
            } catch (Exception $e) {}

            $sql_fixo = "
                SELECT si.insumo_id, ".($usa_qtd_padrao ? "si.quantidade_padrao" : "1")." AS quantidade,
                       i.nome, i.unidade, i.valorunitario, i.estoque, i.tipo_insumo, i.categoria
                FROM servicos_insumos si
                JOIN insumos i ON si.insumo_id = i.id
                WHERE si.servico_id IN ($ph)
            ";
            
            // Verifica se coluna tipo_vinculo existe
            try {
                $test = $conn->query("SHOW COLUMNS FROM servicos_insumos LIKE 'tipo_vinculo'");
                if ($test->rowCount() > 0) {
                    $sql_fixo .= " AND si.tipo_vinculo = 'fixo'";
                }
            } catch (Exception $e) {}

            // Verifica se coluna ativo existe em insumos
            try {
                $test = $conn->query("SHOW COLUMNS FROM insumos LIKE 'ativo'");
                if ($test->rowCount() > 0) $sql_fixo .= " AND i.ativo = 1";
            } catch (Exception $e) {}

            try {
                $stmt_f = $conn->prepare($sql_fixo);
                $stmt_f->execute($servico_ids);
                $insumos_fixos = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }

        // Insumos já salvos para este pedido
        $insumos_selecionados = [];
        try {
            // Verifica se tabela pre_os_insumos existe
            $check = $conn->query("SHOW TABLES LIKE 'pre_os_insumos'");
            if ($check->rowCount() > 0) {
                $stmt_sel = $conn->prepare("
                    SELECT poi.insumo_id, poi.quantidade, poi.cliente_fornece,
                           i.nome, i.unidade, i.valorunitario, i.estoque, i.tipo_insumo, i.categoria
                    FROM pre_os_insumos poi
                    JOIN insumos i ON poi.insumo_id = i.id
                    WHERE poi.pre_os_id = :id
                ");
                $stmt_sel->execute([':id' => $pre_os_id]);
                $insumos_selecionados = $stmt_sel->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {}

        // Se não há salvos, pré-carrega os fixos
        if (empty($insumos_selecionados)) {
            $insumos_selecionados = array_map(function($ins) {
                return [
                    'insumo_id'      => $ins['insumo_id'],
                    'quantidade'     => (float)$ins['quantidade'],
                    'cliente_fornece'=> 0,
                    'nome'           => $ins['nome'],
                    'unidade'        => $ins['unidade'],
                    'valorunitario'  => $ins['valorunitario'],
                    'estoque'        => $ins['estoque'],
                    'tipo_insumo'    => $ins['tipo_insumo'],
                    'categoria'      => $ins['categoria'],
                ];
            }, $insumos_fixos);
        }

        echo json_encode([
            'sucesso'              => true,
            'pedido'               => $pedido,
            'servicos'             => $servicos,
            'categorias'           => $categorias,
            'insumos_selecionados' => $insumos_selecionados,
        ]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['erro'=>'Erro BD: '.$e->getMessage()]);
        exit;
    }
}

// ── POST: salvar insumos confirmados ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true);
    $pre_os_id = (int)($body['pre_os_id'] ?? 0);
    $insumos   = $body['insumos'] ?? [];

    if (!$pre_os_id) { echo json_encode(['sucesso'=>false,'erro'=>'ID inválido']); exit; }

    try {
        // Verifica se tabela pre_os_insumos existe — se não, cria temporariamente ou retorna erro amigável
        $check = $conn->query("SHOW TABLES LIKE 'pre_os_insumos'");
        if ($check->rowCount() === 0) {
            echo json_encode(['sucesso'=>false,'erro'=>'Tabela pre_os_insumos não existe no banco. Contate o administrador.']);
            exit;
        }

        $conn->beginTransaction();

        // Limpa insumos anteriores
        $conn->prepare("DELETE FROM pre_os_insumos WHERE pre_os_id = :id")
             ->execute([':id' => $pre_os_id]);

        // Insere os confirmados
        $stmt_ins = $conn->prepare("
            INSERT INTO pre_os_insumos (pre_os_id, insumo_id, quantidade, valorunitario, cliente_fornece)
            VALUES (:pre_os_id, :insumo_id, :qtd, :valor, :cf)
        ");
        $total_insumos = 0.0;
        foreach ($insumos as $ins) {
            $cf    = (int)($ins['cliente_fornece'] ?? 0);
            $qtd   = (float)($ins['quantidade'] ?? 1);
            $valor = (float)($ins['valorunitario'] ?? 0);
            $stmt_ins->execute([
                ':pre_os_id' => $pre_os_id,
                ':insumo_id' => (int)$ins['insumo_id'],
                ':qtd'       => $qtd,
                ':valor'     => $valor,
                ':cf'        => $cf,
            ]);
            if (!$cf) $total_insumos += $qtd * $valor;
        }

        // Atualiza status
        $conn->prepare("UPDATE pre_os SET status='Em analise', atualizado_em=NOW() WHERE id=:id")
             ->execute([':id' => $pre_os_id]);

        // Auditoria (opcional — se tabela não existir, ignora)
        try {
            $check_aud = $conn->query("SHOW TABLES LIKE 'auditoria'");
            if ($check_aud->rowCount() > 0) {
                $conn->prepare("
                    INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, valor_novo)
                    VALUES (:uid, 'pre_os', :eid, 'edicao', 'Em analise')
                ")->execute([':uid' => $_SESSION['usuario_id'] ?? null, ':eid' => $pre_os_id]);
            }
        } catch (Exception $e) {}

        $conn->commit();

        // Total dos serviços (query parametrizada)
        $stmt_sv = $conn->prepare("
            SELECT COALESCE(SUM(s.valor_base), 0)
            FROM pre_os_servicos ps
            JOIN servicos s ON ps.servico_id = s.id
            WHERE ps.pre_os_id = :id
        ");
        $stmt_sv->execute([':id' => $pre_os_id]);
        $total_servicos = (float)$stmt_sv->fetchColumn();

        echo json_encode([
            'sucesso'         => true,
            'total_servicos'  => round($total_servicos, 2),
            'total_insumos'   => round($total_insumos, 2),
            'total_orcamento' => round($total_servicos + $total_insumos, 2),
        ]);

    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['sucesso'=>false,'erro'=>'Erro BD POST: '.$e->getMessage()]);
    }
    exit;
}

echo json_encode(['sucesso'=>false,'erro'=>'Método não suportado']);
