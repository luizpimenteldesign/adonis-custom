<?php
/**
 * Model: InstrumentoFoto
 * Gerencia fotos dos instrumentos (até 5 por instrumento)
 */

class InstrumentoFoto {
    private $conn;
    private $table = 'instrumento_fotos';

    public $id;
    public $instrumento_id;
    public $pre_os_id;
    public $caminho;
    public $ordem;
    public $criado_em;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * ADICIONAR FOTO
     */
    public function adicionar() {
        $query = "INSERT INTO " . $this->table . " 
                  SET instrumento_id = :instrumento_id,
                      pre_os_id = :pre_os_id,
                      caminho = :caminho,
                      ordem = :ordem";

        $stmt = $this->conn->prepare($query);

        $this->caminho = htmlspecialchars(strip_tags($this->caminho));

        $stmt->bindParam(':instrumento_id', $this->instrumento_id);
        $stmt->bindParam(':pre_os_id', $this->pre_os_id);
        $stmt->bindParam(':caminho', $this->caminho);
        $stmt->bindParam(':ordem', $this->ordem);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * LISTAR FOTOS DE UM INSTRUMENTO
     */
    public function listarPorInstrumento($instrumento_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE instrumento_id = :instrumento_id 
                  ORDER BY ordem ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instrumento_id', $instrumento_id);
        $stmt->execute();

        return $stmt;
    }

    /**
     * CONTAR FOTOS DE UM INSTRUMENTO
     */
    public function contarPorInstrumento($instrumento_id) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " 
                  WHERE instrumento_id = :instrumento_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instrumento_id', $instrumento_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    /**
     * DELETAR FOTO
     */
    public function deletar() {
        // Buscar caminho da foto antes de deletar
        $query = "SELECT caminho FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && file_exists($row['caminho'])) {
            unlink($row['caminho']); // Deletar arquivo físico
        }

        // Deletar registro do banco
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    /**
     * DELETAR TODAS FOTOS DE UM INSTRUMENTO
     */
    public function deletarPorInstrumento($instrumento_id) {
        // Buscar todas fotos
        $query = "SELECT caminho FROM " . $this->table . " WHERE instrumento_id = :instrumento_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instrumento_id', $instrumento_id);
        $stmt->execute();

        // Deletar arquivos físicos
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (file_exists($row['caminho'])) {
                unlink($row['caminho']);
            }
        }

        // Deletar registros
        $query = "DELETE FROM " . $this->table . " WHERE instrumento_id = :instrumento_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instrumento_id', $instrumento_id);
        return $stmt->execute();
    }
}
