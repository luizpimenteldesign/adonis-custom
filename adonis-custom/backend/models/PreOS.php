<?php
/**
 * Model: PreOS
 * Gerencia Pré-Ordens de Serviço (Pedidos de Orçamento)
 * OBRIGATÓRIO: Sempre vinculada a um INSTRUMENTO
 */

class PreOS {
    private $conn;
    private $table = 'pre_os';

    // Propriedades
    public $id;
    public $cliente_id;
    public $instrumento_id; // OBRIGATÓRIO - instrumento é central
    public $observacoes;
    public $status;
    public $public_token;
    public $public_token_active;
    public $token_expires_at;
    public $criado_em;
    public $atualizado_em;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * CRIAR PRÉ-OS
     * Instrumento deve existir antes de criar Pré-OS
     */
    public function criar() {
        $query = "INSERT INTO " . $this->table . " 
                  SET cliente_id = :cliente_id,
                      instrumento_id = :instrumento_id,
                      observacoes = :observacoes,
                      status = :status,
                      public_token = :public_token,
                      public_token_active = :public_token_active,
                      token_expires_at = :token_expires_at";

        $stmt = $this->conn->prepare($query);

        // Gerar token público se não existir
        if (empty($this->public_token)) {
            $this->public_token = $this->gerarToken();
        }

        // Status padrão
        if (empty($this->status)) {
            $this->status = 'Pre-OS';
        }

        // Token ativo por padrão
        if (!isset($this->public_token_active)) {
            $this->public_token_active = 1;
        }

        // Sanitizar
        $this->observacoes = htmlspecialchars(strip_tags($this->observacoes));

        // Bind
        $stmt->bindParam(':cliente_id', $this->cliente_id);
        $stmt->bindParam(':instrumento_id', $this->instrumento_id);
        $stmt->bindParam(':observacoes', $this->observacoes);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':public_token', $this->public_token);
        $stmt->bindParam(':public_token_active', $this->public_token_active);
        $stmt->bindParam(':token_expires_at', $this->token_expires_at);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * BUSCAR PRÉ-OS POR ID
     */
    public function buscarPorId() {
        $query = "SELECT p.*, 
                         c.nome as cliente_nome, 
                         c.telefone as cliente_telefone, 
                         c.email as cliente_email,
                         i.tipo as instrumento_tipo,
                         i.marca as instrumento_marca,
                         i.modelo as instrumento_modelo,
                         i.cor as instrumento_cor,
                         i.referencia as instrumento_referencia,
                         i.numero_serie as instrumento_numero_serie
                  FROM " . $this->table . " p
                  INNER JOIN clientes c ON p.cliente_id = c.id
                  INNER JOIN instrumentos i ON p.instrumento_id = i.id
                  WHERE p.id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * BUSCAR PRÉ-OS POR TOKEN PÚBLICO
     */
    public function buscarPorToken($token) {
        $query = "SELECT p.*, 
                         c.nome as cliente_nome, 
                         c.telefone as cliente_telefone,
                         i.tipo as instrumento_tipo,
                         i.marca as instrumento_marca,
                         i.modelo as instrumento_modelo
                  FROM " . $this->table . " p
                  INNER JOIN clientes c ON p.cliente_id = c.id
                  INNER JOIN instrumentos i ON p.instrumento_id = i.id
                  WHERE p.public_token = :token
                  AND p.public_token_active = 1
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * LISTAR TODAS PRÉ-OS (Admin)
     */
    public function listarTodas($limite = 50, $offset = 0) {
        $query = "SELECT p.id, p.status, p.criado_em,
                         c.nome as cliente_nome,
                         c.telefone as cliente_telefone,
                         i.tipo as instrumento_tipo,
                         i.marca as instrumento_marca,
                         i.modelo as instrumento_modelo
                  FROM " . $this->table . " p
                  INNER JOIN clientes c ON p.cliente_id = c.id
                  INNER JOIN instrumentos i ON p.instrumento_id = i.id
                  ORDER BY p.criado_em DESC
                  LIMIT :limite OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /**
     * LISTAR PRÉ-OS POR STATUS
     */
    public function listarPorStatus($status) {
        $query = "SELECT p.*, 
                         c.nome as cliente_nome,
                         c.telefone as cliente_telefone,
                         i.tipo as instrumento_tipo,
                         i.marca as instrumento_marca
                  FROM " . $this->table . " p
                  INNER JOIN clientes c ON p.cliente_id = c.id
                  INNER JOIN instrumentos i ON p.instrumento_id = i.id
                  WHERE p.status = :status
                  ORDER BY p.criado_em DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();

        return $stmt;
    }

    /**
     * ATUALIZAR STATUS
     */
    public function atualizarStatus() {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':status', $this->status);

        return $stmt->execute();
    }

    /**
     * ATUALIZAR PRÉ-OS COMPLETA
     */
    public function atualizar() {
        $query = "UPDATE " . $this->table . " 
                  SET observacoes = :observacoes,
                      status = :status,
                      public_token_active = :public_token_active
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->observacoes = htmlspecialchars(strip_tags($this->observacoes));

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':observacoes', $this->observacoes);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':public_token_active', $this->public_token_active);

        return $stmt->execute();
    }

    /**
     * REVOGAR TOKEN PÚBLICO
     */
    public function revogarToken() {
        $query = "UPDATE " . $this->table . " 
                  SET public_token_active = 0
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * DELETAR PRÉ-OS
     */
    public function deletar() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    /**
     * CONVERTER PRÉ-OS EM OS
     * Retorna o ID da OS criada
     */
    public function converterParaOS($numero_os, $valor_total, $prazo_dias) {
        try {
            $this->conn->beginTransaction();

            // Buscar dados da Pré-OS
            $pre_os = $this->buscarPorId();

            if (!$pre_os) {
                throw new Exception('Pré-OS não encontrada');
            }

            // Criar OS
            $query_os = "INSERT INTO os 
                         SET numero_os = :numero_os,
                             pre_os_id = :pre_os_id,
                             cliente_id = :cliente_id,
                             instrumento_id = :instrumento_id,
                             status = 'Em execucao',
                             valor_total = :valor_total,
                             prazo_dias = :prazo_dias,
                             observacoes = :observacoes";

            $stmt = $this->conn->prepare($query_os);
            $stmt->bindParam(':numero_os', $numero_os);
            $stmt->bindParam(':pre_os_id', $this->id);
            $stmt->bindParam(':cliente_id', $pre_os['cliente_id']);
            $stmt->bindParam(':instrumento_id', $pre_os['instrumento_id']);
            $stmt->bindParam(':valor_total', $valor_total);
            $stmt->bindParam(':prazo_dias', $prazo_dias);
            $stmt->bindParam(':observacoes', $pre_os['observacoes']);
            $stmt->execute();

            $os_id = $this->conn->lastInsertId();

            // Copiar serviços da Pré-OS para OS
            $query_servicos = "INSERT INTO os_servicos (os_id, servico_id, quantidade, valor_unitario, valor_total)
                               SELECT :os_id, ps.servico_id, ps.quantidade, s.valor_base, (s.valor_base * ps.quantidade)
                               FROM pre_os_servicos ps
                               INNER JOIN servicos s ON ps.servico_id = s.id
                               WHERE ps.pre_os_id = :pre_os_id";

            $stmt_srv = $this->conn->prepare($query_servicos);
            $stmt_srv->bindParam(':os_id', $os_id);
            $stmt_srv->bindParam(':pre_os_id', $this->id);
            $stmt_srv->execute();

            // Atualizar status da Pré-OS
            $this->status = 'Aprovada';
            $this->atualizarStatus();

            // Revogar token público
            $this->revogarToken();

            $this->conn->commit();
            return $os_id;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    /**
     * GERAR TOKEN PÚBLICO ÚNICO
     */
    private function gerarToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * CONTAR PRÉ-OS POR STATUS
     */
    public function contarPorStatus($status) {
        $query = "SELECT COUNT(*) as total 
                  FROM " . $this->table . " 
                  WHERE status = :status";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
}