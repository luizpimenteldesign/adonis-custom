<?php
/**
 * Model: Cliente
 * Gerencia dados dos clientes
 */

class Cliente {
    private $conn;
    private $table = 'clientes';

    // Propriedades
    public $id;
    public $nome;
    public $telefone;
    public $email;
    public $endereco;
    public $criado_em;
    public $atualizado_em;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * CRIAR CLIENTE
     */
    public function criar() {
        $query = "INSERT INTO " . $this->table . " 
                  SET nome = :nome,
                      telefone = :telefone,
                      email = :email,
                      endereco = :endereco";

        $stmt = $this->conn->prepare($query);

        // Sanitizar
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->telefone = htmlspecialchars(strip_tags($this->telefone));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->endereco = htmlspecialchars(strip_tags($this->endereco));

        // Bind
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':telefone', $this->telefone);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':endereco', $this->endereco);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * BUSCAR POR ID
     */
    public function buscarPorId() {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * BUSCAR POR TELEFONE
     */
    public function buscarPorTelefone($telefone) {
        $query = "SELECT * FROM " . $this->table . " WHERE telefone = :telefone LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':telefone', $telefone);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ATUALIZAR CLIENTE
     */
    public function atualizar() {
        $query = "UPDATE " . $this->table . " 
                  SET nome = :nome,
                      email = :email,
                      endereco = :endereco
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->endereco = htmlspecialchars(strip_tags($this->endereco));

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':endereco', $this->endereco);

        return $stmt->execute();
    }

    /**
     * LISTAR TODOS
     */
    public function listarTodos($limite = 100, $offset = 0) {
        $query = "SELECT * FROM " . $this->table . " 
                  ORDER BY nome ASC 
                  LIMIT :limite OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /**
     * DELETAR CLIENTE
     */
    public function deletar() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }
}