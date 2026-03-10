<?php
/**
 * api_relatorios.php — endpoint JSON para todos os relatórios
 */
require_once 'auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db   = new Database();
$conn = $db->getConnection();

$tipo  = $_GET['tipo']  ?? '';
$de    = $_GET['de']    ?? date('Y-m-01');
$ate   = $_GET['ate']   ?? date('Y-m-d');

$de  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)  ? $de  : date('Y-m-01');
$ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) ? $ate : date('Y-m-d');
$ateHora = $ate . ' 23:59:59';

try {
    switch ($tipo) {

        // ── 1. FINANCEIRO ─────────────────────────────────────────
        case 'financeiro':
            $stmt = $conn->prepare("
                SELECT
                    DATE_FORMAT(atualizado_em,'%Y-%m') as mes,
                    COUNT(*)                           as qtd,
                    SUM(valor_orcamento)               as total,
                    AVG(valor_orcamento)               as ticket
                FROM pre_os
                WHERE status = 'Entregue'
                  AND atualizado_em BETWEEN :de AND :ate
                GROUP BY mes ORDER BY mes ASC
            ");
            $stmt->execute([':de'=>$de, ':ate'=>$ateHora]);
            $porMes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $conn->prepare("
                SELECT
                    CASE
                        WHEN valor_orcamento < 100               THEN 'Até R\$ 100'
                        WHEN valor_orcamento BETWEEN 100 AND 300  THEN 'R\$ 100–300'
                        WHEN valor_orcamento BETWEEN 300 AND 600  THEN 'R\$ 300–600'
                        WHEN valor_orcamento BETWEEN 600 AND 1000 THEN 'R\$ 600–1000'
                        ELSE 'Acima de R\$ 1000'
                    END AS faixa,
                    COUNT(*) as qtd,
                    SUM(valor_orcamento) as total
                FROM pre_os
                WHERE status = 'Entregue'
                  AND atualizado_em BETWEEN :de AND :ate
                GROUP BY faixa ORDER BY MIN(valor_orcamento) ASC
            ");
            $stmt2->execute([':de'=>$de, ':ate'=>$ateHora]);
            $faixas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $stmt3 = $conn->prepare("
                SELECT COUNT(*) as qtd, SUM(valor_orcamento) as total, AVG(valor_orcamento) as ticket
                FROM pre_os
                WHERE status = 'Entregue' AND atualizado_em BETWEEN :de AND :ate
            ");
            $stmt3->execute([':de'=>$de, ':ate'=>$ateHora]);
            $totais = $stmt3->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['ok'=>true,'porMes'=>$porMes,'faixas'=>$faixas,'totais'=>$totais]);
            break;

        // ── 2. PEDIDOS POR STATUS ──────────────────────────────────
        case 'status':
            $stmt = $conn->prepare("
                SELECT status, COUNT(*) as qtd
                FROM pre_os
                WHERE criado_em BETWEEN :de AND :ate
                GROUP BY status ORDER BY qtd DESC
            ");
            $stmt->execute([':de'=>$de, ':ate'=>$ateHora]);
            $porStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $conn->prepare("
                SELECT
                    AVG(DATEDIFF(atualizado_em, criado_em)) as media_dias,
                    MIN(DATEDIFF(atualizado_em, criado_em)) as minimo,
                    MAX(DATEDIFF(atualizado_em, criado_em)) as maximo
                FROM pre_os
                WHERE status = 'Entregue' AND criado_em BETWEEN :de AND :ate
            ");
            $stmt2->execute([':de'=>$de, ':ate'=>$ateHora]);
            $tmed = $stmt2->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['ok'=>true,'porStatus'=>$porStatus,'tempoMedio'=>$tmed]);
            break;

        // ── 3. CLIENTES ────────────────────────────────────────────
        case 'clientes':
            $stmt = $conn->prepare("
                SELECT c.id, c.nome, c.telefone,
                       COUNT(p.id)            as pedidos,
                       SUM(p.valor_orcamento) as receita,
                       MAX(p.atualizado_em)   as ultimo
                FROM clientes c
                JOIN pre_os p ON p.cliente_id = c.id
                WHERE p.criado_em BETWEEN :de AND :ate
                GROUP BY c.id ORDER BY receita DESC LIMIT 50
            ");
            $stmt->execute([':de'=>$de, ':ate'=>$ateHora]);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $conn->prepare("
                SELECT COUNT(DISTINCT cliente_id) as total
                FROM pre_os WHERE criado_em BETWEEN :de AND :ate
            ");
            $stmt2->execute([':de'=>$de, ':ate'=>$ateHora]);
            $totCli = $stmt2->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['ok'=>true,'clientes'=>$clientes,'totalClientes'=>$totCli]);
            break;

        // ── 4. SERVIÇOS MAIS EXECUTADOS ────────────────────────────
        case 'servicos':
            $stmt = $conn->prepare("
                SELECT s.nome, s.valor_base,
                       COUNT(ps.id)      as qtd,
                       SUM(s.valor_base) as receita_base
                FROM pre_os_servicos ps
                JOIN servicos s ON ps.servico_id = s.id
                JOIN pre_os   p ON ps.pre_os_id  = p.id
                WHERE p.criado_em BETWEEN :de AND :ate
                GROUP BY s.id ORDER BY qtd DESC LIMIT 30
            ");
            $stmt->execute([':de'=>$de, ':ate'=>$ateHora]);
            $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok'=>true,'servicos'=>$servicos]);
            break;

        // ── 5. INSUMOS / ESTOQUE ───────────────────────────────────
        case 'insumos':
            // usa quantidadeestoque (nome real da coluna no banco)
            $stmt = $conn->query("
                SELECT
                    nome, categoria, unidade, tipo_insumo,
                    quantidadeestoque                          AS estoque,
                    valorunitario,
                    (quantidadeestoque * valorunitario)        AS valor_total
                FROM insumos
                ORDER BY quantidadeestoque ASC
            ");
            $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $zerados  = array_filter($insumos, fn($i) => (float)$i['estoque'] <= 0);
            $criticos = array_filter($insumos, fn($i) => (float)$i['estoque'] > 0 && (float)$i['estoque'] < 5);
            $valorTotal = array_sum(array_column($insumos, 'valor_total'));

            echo json_encode([
                'ok'        => true,
                'insumos'   => array_values($insumos),
                'zerados'   => count($zerados),
                'criticos'  => count($criticos),
                'valorTotal'=> $valorTotal,
                'total'     => count($insumos),
            ]);
            break;

        // ── 6. TEMPO MÉDIO DE EXECUÇÃO ─────────────────────────────
        case 'tempo':
            $stmt = $conn->prepare("
                SELECT
                    AVG(DATEDIFF(atualizado_em, criado_em)) as media_total,
                    MIN(DATEDIFF(atualizado_em, criado_em)) as minimo,
                    MAX(DATEDIFF(atualizado_em, criado_em)) as maximo
                FROM pre_os
                WHERE status = 'Entregue' AND criado_em BETWEEN :de AND :ate
            ");
            $stmt->execute([':de'=>$de, ':ate'=>$ateHora]);
            $geral = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt2 = $conn->prepare("
                SELECT h.status,
                       AVG(DATEDIFF(h.criado_em, p.criado_em)) as media_dias,
                       COUNT(*) as qtd
                FROM status_historico h
                JOIN pre_os p ON h.pre_os_id = p.id
                WHERE p.criado_em BETWEEN :de AND :ate
                GROUP BY h.status ORDER BY media_dias ASC
            ");
            $stmt2->execute([':de'=>$de, ':ate'=>$ateHora]);
            $porEtapa = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok'=>true,'geral'=>$geral,'porEtapa'=>$porEtapa]);
            break;

        // ── 7. INSTRUMENTOS ────────────────────────────────────────
        case 'instrumentos':
            $stmt = $conn->prepare("
                SELECT i.tipo, i.marca,
                       COUNT(p.id)            as pedidos,
                       SUM(p.valor_orcamento) as receita
                FROM pre_os p
                JOIN instrumentos i ON p.instrumento_id = i.id
                WHERE p.criado_em BETWEEN :de AND :ate
                GROUP BY i.tipo, i.marca ORDER BY pedidos DESC LIMIT 30
            ");
            $stmt->execute([':de'=>$de, ':ate'=>$ateHora]);
            $instrs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $conn->prepare("
                SELECT i.tipo, COUNT(p.id) as pedidos
                FROM pre_os p JOIN instrumentos i ON p.instrumento_id = i.id
                WHERE p.criado_em BETWEEN :de AND :ate
                GROUP BY i.tipo ORDER BY pedidos DESC
            ");
            $stmt2->execute([':de'=>$de, ':ate'=>$ateHora]);
            $porTipo = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok'=>true,'instrumentos'=>$instrs,'porTipo'=>$porTipo]);
            break;

        default:
            echo json_encode(['ok'=>false,'erro'=>'Tipo inválido']);
    }

} catch (Exception $e) {
    echo json_encode(['ok'=>false,'erro'=>$e->getMessage()]);
}
