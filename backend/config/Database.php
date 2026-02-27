<?php
/**
 * DATABASE - SISTEMA ADONIS CUSTOM
 * Classe de conexão com o banco de dados via PDO
 * Compatível com login.php, dashboard.php e demais arquivos admin
 */

class Database {

    private $host     = 'localhost';
    private $db_name  = 'luizpi39_adns_app';
    private $username = 'luizpi39_adns';
    private $password = 'a[Ne3KC][3OT';
    private $charset  = 'utf8mb4';
    private $conn     = null;

    /**
     * Retorna a conexão PDO (singleton)
     */
    public function getConnection(): PDO {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";

            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new PDOException('Erro ao conectar com o banco de dados: ' . $e->getMessage());
        }

        return $this->conn;
    }

    /**
     * Alias para compatibilidade com código legado que usa connect()
     */
    public function connect(): PDO {
        return $this->getConnection();
    }
}
