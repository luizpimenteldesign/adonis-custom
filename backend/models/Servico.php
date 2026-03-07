<?php
require_once __DIR__ . '/Database.php';

class Servico {
    private $conn;
    private $table = 'servicos';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function listar() {
        // Busca serviços com suas categorias da tabela de relacionamento
        $query = "
            SELECT 
                s.id,
                s.nome,
                s.descricao,
                s.valor_base,
                s.prazo_base,
                s.prazo_padrao_dias,
                s.ativo,
                s.criado_em,
                s.atualizado_em,
                GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ', ') as categorias,
                GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ',') as categoria_lista
            FROM servicos s
            LEFT JOIN servico_categorias sc ON sc.servico_id = s.id
            LEFT JOIN categorias_servico c ON c.id = sc.categoria_id AND c.ativo = 1
            WHERE s.ativo = 1
            GROUP BY s.id
            ORDER BY s.nome ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processa cada serviço para adicionar a categoria principal
        foreach ($servicos as &$servico) {
            // Se tem categorias, pega a primeira como categoria principal
            if (!empty($servico['categoria_lista'])) {
                $cats = explode(',', $servico['categoria_lista']);
                $servico['categoria'] = $cats[0];
            } else {
                $servico['categoria'] = '';
            }
            
            // Remove o campo auxiliar
            unset($servico['categoria_lista']);
        }
        
        return $servicos;
    }

    public function buscarPorId($id) {
        $query = "
            SELECT 
                s.*,
                GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ', ') as categorias
            FROM servicos s
            LEFT JOIN servico_categorias sc ON sc.servico_id = s.id
            LEFT JOIN categorias_servico c ON c.id = sc.categoria_id AND c.ativo = 1
            WHERE s.id = :id AND s.ativo = 1
            GROUP BY s.id
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $servico = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Adiciona categoria principal (primeira da lista)
            if (!empty($servico['categorias'])) {
                $cats = explode(', ', $servico['categorias']);
                $servico['categoria'] = $cats[0];
            } else {
                $servico['categoria'] = '';
            }
            
            return $servico;
        }
        
        return false;
    }
}
?>