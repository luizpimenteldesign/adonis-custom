<?php
require_once __DIR__ . '/Database.php';

class Usuario {
    private $conn;
    private $table = 'usuarios';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function login($email, $senha) {
        $query = "SELECT id, nome, email, senha_hash, tipo FROM " . $this->table . " 
                  WHERE email = :email AND ativo = 1 LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($senha, $row['senha_hash'])) {
                $token = bin2hex(random_bytes(32));
                $this->atualizarToken($row['id'], $token);

                return [
                    'success' => true,
                    'usuario' => [
                        'id' => $row['id'],
                        'nome' => $row['nome'],
                        'email' => $row['email'],
                        'tipo' => $row['tipo'],
                        'token' => $token
                    ]
                ];
            }
        }

        return ['success' => false, 'message' => 'Credenciais inválidas'];
    }

    public function validarToken($token) {
        $query = "SELECT id, nome, email, tipo FROM " . $this->table . " 
                  WHERE token = :token AND ativo = 1 LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    }

    private function atualizarToken($id, $token) {
        $query = "UPDATE " . $this->table . " SET token = :token WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
