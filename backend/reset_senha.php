<?php
/**
 * ARQUIVO TEMPORÁRIO - DELETAR APÓS USO!
 */
require_once 'config/Database.php';

$db   = new Database();
$conn = $db->getConnection();

$nova_senha = 'admin123';
$novo_hash  = password_hash($nova_senha, PASSWORD_BCRYPT);

$stmt = $conn->prepare("UPDATE usuarios SET senha_hash = :hash, ativo = 1 WHERE email = 'admin@adonis.com'");
$stmt->execute([':hash' => $novo_hash]);

echo '<h2>&#x2705; Senha atualizada com sucesso!</h2>';
echo '<p>E-mail: <strong>admin@adonis.com</strong></p>';
echo '<p>Senha: <strong>admin123</strong></p>';
echo '<p>Hash gerado: <code>' . $novo_hash . '</code></p>';
echo '<hr>';
echo '<p style="color:red"><strong>&#x26A0;&#xFE0F; DELETE ESTE ARQUIVO AGORA pelo cPanel ou FTP!</strong></p>';
echo '<p><a href="admin/login.php">&#x2192; Ir para o login</a></p>';
