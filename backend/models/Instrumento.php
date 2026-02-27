<?php
/**
 * Model: Instrumento
 * Entidade CENTRAL do Sistema Adonis - Luthieria
 * OBRIGATÓRIA em toda OS e Pré-OS
 */

class Instrumento {
    private $conn;
    private $table = 'instrumentos';

    public $id;
    public $cliente_id;
    public $tipo;
    public $tipo_outro;
    public $marca;
    public $marca_outro;
    public $modelo;
    public $modelo_outro;
    public $cor;
    public $cor_outro;
    public $referencia;
    public $numero_serie;
    public $criado_em;
    public $atualizado_em;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * CRIAR INSTRUMENTO
     * Sempre vinculado a um cliente
     */
    public function criar() {
        $query = "INSERT INTO " . $this->table . " 
                  SET cliente_id = :cliente_id,
                      tipo = :tipo,
                      tipo_outro = :tipo_outro,
                      marca = :marca,
                      marca_outro = :marca_outro,
                      modelo = :modelo,
                      modelo_outro = :modelo_outro,
                      cor = :cor,
                      cor_outro = :cor_outro,
                      referencia = :referencia,
                      numero_serie = :numero_serie";

        $stmt = $this->conn->prepare($query);

        // Sanitizar dados (tratando NULL corretamente)
        $this->cliente_id = htmlspecialchars(strip_tags($this->cliente_id));
        $this->tipo = htmlspecialchars(strip_tags($this->tipo));
        $this->tipo_outro = $this->tipo_outro ? htmlspecialchars(strip_tags($this->tipo_outro)) : null;
        $this->marca = htmlspecialchars(strip_tags($this->marca));
        $this->marca_outro = $this->marca_outro ? htmlspecialchars(strip_tags($this->marca_outro)) : null;
        $this->modelo = htmlspecialchars(strip_tags($this->modelo));
        $this->modelo_outro = $this->modelo_outro ? htmlspecialchars(strip_tags($this->modelo_outro)) : null;
        $this->cor = $this->cor ? htmlspecialchars(strip_tags($this->cor)) : null;
        $this->cor_outro = $this->cor_outro ? htmlspecialchars(strip_tags($this->cor_outro)) : null;
        $this->referencia = $this->referencia ? htmlspecialchars(strip_tags($this->referencia)) : null;
        $this->numero_serie = $this->numero_serie ? htmlspecialchars(strip_tags($this->numero_serie)) : null;

        // Bind
        $stmt->bindParam(':cliente_id', $this->cliente_id);
        $stmt->bindParam(':tipo', $this->tipo);
        $stmt->bindParam(':tipo_outro', $this->tipo_outro);
        $stmt->bindParam(':marca', $this->marca);
        $stmt->bindParam(':marca_outro', $this->marca_outro);
        $stmt->bindParam(':modelo', $this->modelo);
        $stmt->bindParam(':modelo_outro', $this->modelo_outro);
        $stmt->bindParam(':cor', $this->cor);
        $stmt->bindParam(':cor_outro', $this->cor_outro);
        $stmt->bindParam(':referencia', $this->referencia);
        $stmt->bindParam(':numero_serie', $this->numero_serie);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * BUSCAR INSTRUMENTO POR ID
     */
    public function buscarPorId() {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->cliente_id = $row['cliente_id'];
            $this->tipo = $row['tipo'];
            $this->tipo_outro = $row['tipo_outro'];
            $this->marca = $row['marca'];
            $this->marca_outro = $row['marca_outro'];
            $this->modelo = $row['modelo'];
            $this->modelo_outro = $row['modelo_outro'];
            $this->cor = $row['cor'];
            $this->cor_outro = $row['cor_outro'];
            $this->referencia = $row['referencia'];
            $this->numero_serie = $row['numero_serie'];
            $this->criado_em = $row['criado_em'];
            $this->atualizado_em = $row['atualizado_em'];
            return true;
        }
        return false;
    }

    /**
     * LISTAR INSTRUMENTOS DE UM CLIENTE
     */
    public function listarPorCliente($cliente_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE cliente_id = :cliente_id 
                  ORDER BY criado_em DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->execute();

        return $stmt;
    }

    /**
     * ATUALIZAR INSTRUMENTO
     */
    public function atualizar() {
        $query = "UPDATE " . $this->table . " 
                  SET tipo = :tipo,
                      tipo_outro = :tipo_outro,
                      marca = :marca,
                      marca_outro = :marca_outro,
                      modelo = :modelo,
                      modelo_outro = :modelo_outro,
                      cor = :cor,
                      cor_outro = :cor_outro,
                      referencia = :referencia,
                      numero_serie = :numero_serie
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitizar (tratando NULL corretamente)
        $this->tipo = htmlspecialchars(strip_tags($this->tipo));
        $this->tipo_outro = $this->tipo_outro ? htmlspecialchars(strip_tags($this->tipo_outro)) : null;
        $this->marca = htmlspecialchars(strip_tags($this->marca));
        $this->marca_outro = $this->marca_outro ? htmlspecialchars(strip_tags($this->marca_outro)) : null;
        $this->modelo = htmlspecialchars(strip_tags($this->modelo));
        $this->modelo_outro = $this->modelo_outro ? htmlspecialchars(strip_tags($this->modelo_outro)) : null;
        $this->cor = $this->cor ? htmlspecialchars(strip_tags($this->cor)) : null;
        $this->cor_outro = $this->cor_outro ? htmlspecialchars(strip_tags($this->cor_outro)) : null;
        $this->referencia = $this->referencia ? htmlspecialchars(strip_tags($this->referencia)) : null;
        $this->numero_serie = $this->numero_serie ? htmlspecialchars(strip_tags($this->numero_serie)) : null;

        // Bind
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':tipo', $this->tipo);
        $stmt->bindParam(':tipo_outro', $this->tipo_outro);
        $stmt->bindParam(':marca', $this->marca);
        $stmt->bindParam(':marca_outro', $this->marca_outro);
        $stmt->bindParam(':modelo', $this->modelo);
        $stmt->bindParam(':modelo_outro', $this->modelo_outro);
        $stmt->bindParam(':cor', $this->cor);
        $stmt->bindParam(':cor_outro', $this->cor_outro);
        $stmt->bindParam(':referencia', $this->referencia);
        $stmt->bindParam(':numero_serie', $this->numero_serie);

        return $stmt->execute();
    }

    /**
     * DELETAR INSTRUMENTO
     * CUIDADO: Cascateará para fotos, pre_os e os
     */
    public function deletar() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }
}
