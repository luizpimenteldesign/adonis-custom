<?php
/**
 * API: Análise de insumos de uma Pré-OS
 * GET  ?pre_os_id=X                 → categorias + insumos fixos pré-selecionados
 * GET  ?pre_os_id=X&categoria=Y&q=Z → lista insumos da categoria para seleção manual
 * POST                              → salva insumos confirmados em pre_os_insumos
 */
require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

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
        WHERE pos.pre_os_id = :pre_os_id AND i.ativo = 1
    ";
    $params = [':pre_os_id' => $pre_os_id];

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
        echo json_encode(['sucesso'=>false,'erro'=>$e->getMessage()]);
    }
    exit;
}

// ── GET: dados iniciais do pedido ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pre_os_id = (int)($_GET['pre_os_id'] ?? 0);
    if (!$pre_os_id) { echo json_encode(['erro'=>'ID inválido']); exit; }

    // Pedido
    $stmt = $conn->prepare("
        SELECT p.id, p.status, p.observacoes,
               c.nome AS cliente_nome,
               i.tipo AS instrumento_tipo, i.marca AS instrumento_marca, i.modelo AS instrumento_modelo
        FROM pre_os p
        JOIN clientes c ON p.cliente_id = c.id
        JOIN instrumentos i ON p.instrumento_id = i.id
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
    try {
        $servico_ids = array_column($servicos, 'id');
        if (!empty($servico_ids)) {
            $ph = implode(',', array_fill(0, count($servico_ids), '?'));
            $stmt_cat = $conn->prepare("
                SELECT DISTINCT i.categoria
                FROM insumos i
                INNER JOIN servicos_insumos si ON si.insumo_id = i.id
                WHERE si.servico_id IN ($ph) AND i.ativo=1
                  AND i.categoria IS NOT NULL AND i.categoria != ''
                ORDER BY i.categoria
            ");
            $stmt_cat->execute($servico_ids);
            $categorias = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {}
    // Garante "Todos" sempre presente
    array_unshift($categorias, 'Todos');

    // Insumos FIXOS pré-selecionados pelos serviços (tipo_vinculo = 'fixo')
    $insumos_fixos = [];
    try {
        $servico_ids = array_column($servicos, 'id');
        if (!empty($servico_ids)) {
            $ph = implode(',', array_fill(0, count($servico_ids), '?'));
            $stmt_f = $conn->prepare("
                SELECT si.insumo_id, si.quantidade_padrao AS quantidade,
                       i.nome, i.unidade, i.valorunitario, i.estoque, i.tipo_insumo, i.categoria
                FROM servicos_insumos si
                JOIN insumos i ON si.insumo_id = i.id
                WHERE si.servico_id IN ($ph)
                  AND si.tipo_vinculo = 'fixo'
                  AND i.ativo = 1
            ");
            $stmt_f->execute($servico_ids);
            $insumos_fixos = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}

    // Insumos já salvos para este pedido (se reabrindo análise)
    $insumos_selecionados = [];
    try {
        $stmt_sel = $conn->prepare("
            SELECT poi.insumo_id, poi.quantidade, poi.cliente_fornece,
                   i.nome, i.unidade, i.valorunitario, i.estoque, i.tipo_insumo, i.categoria
            FROM pre_os_insumos poi
            JOIN insumos i ON poi.insumo_id = i.id
            WHERE poi.pre_os_id = :id
        ");
        $stmt_sel->execute([':id' => $pre_os_id]);
        $insumos_selecionados = $stmt_sel->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    // Se não há insumos salvos ainda, pré-carrega os fixos
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
}

// ── POST: salvar insumos confirmados ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true);
    $pre_os_id = (int)($body['pre_os_id'] ?? 0);
    $insumos   = $body['insumos'] ?? [];

    if (!$pre_os_id) { echo json_encode(['erro'=>'ID inválido']); exit; }

    try {
        $conn->beginTransaction();
        $conn->prepare("DELETE FROM pre_os_insumos WHERE pre_os_id=:id")->execute([':id'=>$pre_os_id]);

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

        $conn->prepare("UPDATE pre_os SET status='Em analise', atualizado_em=NOW() WHERE id=:id")
             ->execute([':id'=>$pre_os_id]);

        try {
            $conn->prepare("
                INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, valor_novo)
                VALUES (:uid,'pre_os',:eid,'edicao','Em analise')
            ")->execute([':uid'=>$_SESSION['usuario_id']??null,':eid'=>$pre_os_id]);
        } catch (Exception $e) {}

        $conn->commit();

        $total_servicos = (float)$conn->prepare("
            SELECT COALESCE(SUM(s.valor_base),0) FROM pre_os_servicos ps
            JOIN servicos s ON ps.servico_id=s.id WHERE ps.pre_os_id=:id
        ")->execute([':id'=>$pre_os_id]) ? $conn->query("SELECT COALESCE(SUM(s.valor_base),0) FROM pre_os_servicos ps JOIN servicos s ON ps.servico_id=s.id WHERE ps.pre_os_id=$pre_os_id")->fetchColumn() : 0;

        echo json_encode([
            'sucesso'        => true,
            'total_servicos' => round($total_servicos, 2),
            'total_insumos'  => round($total_insumos, 2),
            'total_orcamento'=> round($total_servicos + $total_insumos, 2),
        ]);
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['erro'=>$e->getMessage()]);
    }
    exit;
}

echo json_encode(['erro'=>'Método não suportado']);
