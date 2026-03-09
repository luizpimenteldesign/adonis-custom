<?php
/**
 * API: Análise de insumos de uma Pré-OS
 * v6 — Coluna correta: valor_unitario (com underscore)
 */

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode(['sucesso'=>false,'erro'=>'Erro fatal: '.$error['message'].' em '.basename($error['file']).':'.$error['line']]);
        exit;
    }
});

// session_start() ANTES de qualquer header
require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json; charset=utf-8');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['sucesso'=>false,'erro'=>"PHP: $errstr (linha $errline)"]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['sucesso'=>false,'erro'=>'Exceção: '.$e->getMessage()]);
    exit;
});

$db   = new Database();
$conn = $db->getConnection();
if (!$conn) { echo json_encode(['sucesso'=>false,'erro'=>'Falha na conexão BD']); exit; }

// Detecta colunas opcionais da tabela insumos
$cols_insumos = [];
try {
    $res = $conn->query('SHOW COLUMNS FROM insumos');
    foreach ($res->fetchAll(PDO::FETCH_ASSOC) as $col) $cols_insumos[] = $col['Field'];
} catch (Exception $e) {
    echo json_encode(['sucesso'=>false,'erro'=>'Erro ao ler estrutura BD: '.$e->getMessage()]);
    exit;
}

$tem_estoque   = in_array('estoque',     $cols_insumos);
$tem_tipo      = in_array('tipo_insumo', $cols_insumos);
$tem_categoria = in_array('categoria',   $cols_insumos);
$tem_ativo     = in_array('ativo',       $cols_insumos);

function _sel($tem_estoque, $tem_tipo, $tem_categoria) {
    $c = ['i.id','i.nome','i.unidade','i.valorunitario'];
    $c[] = $tem_estoque   ? 'i.estoque'             : '0 AS estoque';
    $c[] = $tem_tipo      ? 'i.tipo_insumo'         : "'variavel' AS tipo_insumo";
    $c[] = $tem_categoria ? 'i.categoria'           : "'' AS categoria";
    return implode(', ', $c);
}

$sel = _sel($tem_estoque, $tem_tipo, $tem_categoria);

// ── GET: lista insumos de uma categoria ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['categoria'])) {
    $categoria = trim($_GET['categoria']);
    $busca     = trim($_GET['q'] ?? '');
    $pre_os_id = (int)($_GET['pre_os_id'] ?? 0);
    if (!$pre_os_id) { echo json_encode(['sucesso'=>false,'erro'=>'ID inválido']); exit; }

    $sql = "SELECT DISTINCT $sel
        FROM insumos i
        INNER JOIN servicos_insumos si ON si.insumo_id = i.id
        INNER JOIN pre_os_servicos pos ON pos.servico_id = si.servico_id
        WHERE pos.pre_os_id = :pre_os_id";
    $params = [':pre_os_id' => $pre_os_id];

    if ($tem_ativo) $sql .= ' AND i.ativo = 1';
    if ($tem_categoria && $categoria !== 'Todos') { $sql .= ' AND i.categoria = :cat'; $params[':cat'] = $categoria; }
    if ($busca) { $sql .= ' AND i.nome LIKE :busca'; $params[':busca'] = "%$busca%"; }
    $sql .= ' ORDER BY i.nome LIMIT 100';

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso'=>true,'insumos'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo json_encode(['sucesso'=>false,'erro'=>'Erro busca: '.$e->getMessage()]);
    }
    exit;
}

// ── GET: dados iniciais do pedido ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pre_os_id = (int)($_GET['pre_os_id'] ?? 0);
    if (!$pre_os_id) { echo json_encode(['erro'=>'ID inválido']); exit; }

    try {
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

        $stmt_s = $conn->prepare("SELECT s.id, s.nome FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id = s.id WHERE ps.pre_os_id = :id");
        $stmt_s->execute([':id' => $pre_os_id]);
        $servicos = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
        $servico_ids = array_column($servicos, 'id');

        // Categorias
        $categorias = ['Todos'];
        if (!empty($servico_ids) && $tem_categoria) {
            $ph = implode(',', array_fill(0, count($servico_ids), '?'));
            $sql_cat = "SELECT DISTINCT i.categoria FROM insumos i
                INNER JOIN servicos_insumos si ON si.insumo_id = i.id
                WHERE si.servico_id IN ($ph) AND i.categoria IS NOT NULL AND i.categoria != ''";
            if ($tem_ativo) $sql_cat .= ' AND i.ativo=1';
            $sql_cat .= ' ORDER BY i.categoria';
            try {
                $stmt_cat = $conn->prepare($sql_cat);
                $stmt_cat->execute($servico_ids);
                $categorias = array_merge(['Todos'], $stmt_cat->fetchAll(PDO::FETCH_COLUMN));
            } catch (Exception $e) {}
        }

        // Insumos fixos
        $insumos_fixos = [];
        if (!empty($servico_ids)) {
            $ph = implode(',', array_fill(0, count($servico_ids), '?'));
            $usa_qtd = false;
            try { $t = $conn->query("SHOW COLUMNS FROM servicos_insumos LIKE 'quantidade_padrao'"); $usa_qtd = $t->rowCount() > 0; } catch(Exception $e){}
            $qtd_col = $usa_qtd ? 'si.quantidade_padrao' : '1';

            $sql_fixo = "SELECT si.insumo_id, $qtd_col AS quantidade, $sel
                FROM servicos_insumos si JOIN insumos i ON si.insumo_id = i.id
                WHERE si.servico_id IN ($ph)";
            try { $t = $conn->query("SHOW COLUMNS FROM servicos_insumos LIKE 'tipo_vinculo'"); if($t->rowCount()>0) $sql_fixo .= " AND si.tipo_vinculo='fixo'"; } catch(Exception $e){}
            if ($tem_ativo) $sql_fixo .= ' AND i.ativo=1';
            try {
                $stmt_f = $conn->prepare($sql_fixo);
                $stmt_f->execute($servico_ids);
                $insumos_fixos = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }

        // Insumos já salvos — coluna valor_unitario (com underscore)
        $insumos_selecionados = [];
        try {
            $chk = $conn->query("SHOW TABLES LIKE 'pre_os_insumos'");
            if ($chk->rowCount() > 0) {
                $stmt_sel = $conn->prepare("
                    SELECT poi.insumo_id, poi.quantidade, poi.cliente_fornece,
                           poi.valor_unitario AS valorunitario,
                           $sel
                    FROM pre_os_insumos poi JOIN insumos i ON poi.insumo_id = i.id
                    WHERE poi.pre_os_id = :id");
                $stmt_sel->execute([':id' => $pre_os_id]);
                $insumos_selecionados = $stmt_sel->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {}

        if (empty($insumos_selecionados)) {
            $insumos_selecionados = array_map(fn($ins) => [
                'insumo_id'       => $ins['insumo_id'],
                'quantidade'      => (float)$ins['quantidade'],
                'cliente_fornece' => 0,
                'nome'            => $ins['nome'],
                'unidade'         => $ins['unidade'],
                'valorunitario'   => $ins['valorunitario'],
                'estoque'         => $ins['estoque'],
                'tipo_insumo'     => $ins['tipo_insumo'],
                'categoria'       => $ins['categoria'],
            ], $insumos_fixos);
        }

        echo json_encode(['sucesso'=>true,'pedido'=>$pedido,'servicos'=>$servicos,'categorias'=>$categorias,'insumos_selecionados'=>$insumos_selecionados]);

    } catch (PDOException $e) {
        echo json_encode(['erro'=>'Erro BD: '.$e->getMessage()]);
    }
    exit;
}

// ── POST: salvar insumos confirmados ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true);
    $pre_os_id = (int)($body['pre_os_id'] ?? 0);
    $insumos   = $body['insumos'] ?? [];
    if (!$pre_os_id) { echo json_encode(['sucesso'=>false,'erro'=>'ID inválido']); exit; }

    try {
        $chk = $conn->query("SHOW TABLES LIKE 'pre_os_insumos'");
        if ($chk->rowCount() === 0) {
            echo json_encode(['sucesso'=>false,'erro'=>'Tabela pre_os_insumos não existe.']);
            exit;
        }

        $conn->beginTransaction();
        $conn->prepare("DELETE FROM pre_os_insumos WHERE pre_os_id = :id")->execute([':id'=>$pre_os_id]);

        // INSERT usa valor_unitario (nome real da coluna no banco)
        $stmt_ins = $conn->prepare("
            INSERT INTO pre_os_insumos (pre_os_id, insumo_id, quantidade, valor_unitario, cliente_fornece)
            VALUES (:pid, :iid, :qtd, :val, :cf)
        ");
        $total_insumos = 0.0;
        foreach ($insumos as $ins) {
            $cf  = (int)($ins['cliente_fornece'] ?? 0);
            $qtd = (float)($ins['quantidade'] ?? 1);
            $val = (float)($ins['valorunitario'] ?? 0);
            $stmt_ins->execute([':pid'=>$pre_os_id,':iid'=>(int)$ins['insumo_id'],':qtd'=>$qtd,':val'=>$val,':cf'=>$cf]);
            if (!$cf) $total_insumos += $qtd * $val;
        }

        $conn->prepare("UPDATE pre_os SET status='Em analise', atualizado_em=NOW() WHERE id=:id")->execute([':id'=>$pre_os_id]);
        $conn->commit();

        $stmt_sv = $conn->prepare("SELECT COALESCE(SUM(s.valor_base),0) FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id=s.id WHERE ps.pre_os_id=:id");
        $stmt_sv->execute([':id'=>$pre_os_id]);
        $total_servicos = (float)$stmt_sv->fetchColumn();

        echo json_encode(['sucesso'=>true,'total_servicos'=>round($total_servicos,2),'total_insumos'=>round($total_insumos,2),'total_orcamento'=>round($total_servicos+$total_insumos,2)]);

    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['sucesso'=>false,'erro'=>'Erro BD POST: '.$e->getMessage()]);
    }
    exit;
}

echo json_encode(['sucesso'=>false,'erro'=>'Método não suportado']);
