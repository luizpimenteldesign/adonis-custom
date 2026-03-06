<?php
require_once 'auth.php';
require_once '../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

$admin_id = $_SESSION['admin_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php?msg=erro:Método inválido');
    exit;
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    header('Location: perfil.php?msg=erro:Erro no upload do arquivo');
    exit;
}

$file = $_FILES['avatar'];
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowed)) {
    header('Location: perfil.php?msg=erro:Formato inválido. Use JPG, PNG, GIF ou WEBP');
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) { // 5MB
    header('Location: perfil.php?msg=erro:Arquivo muito grande. Máximo 5MB');
    exit;
}

// Cria pasta uploads/avatars se não existir
$upload_dir = __DIR__ . '/../uploads/avatars';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Nome único para o arquivo
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'avatar_' . $admin_id . '_' . time() . '.' . $ext;
$filepath = $upload_dir . '/' . $filename;
$relative_path = '../uploads/avatars/' . $filename;

// Move o arquivo
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    header('Location: perfil.php?msg=erro:Falha ao salvar arquivo');
    exit;
}

// Resize da imagem para 200x200
try {
    list($width, $height) = getimagesize($filepath);
    $new_width = 200;
    $new_height = 200;

    $thumb = imagecreatetruecolor($new_width, $new_height);

    switch ($file['type']) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($filepath);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($filepath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($filepath);
            break;
        default:
            throw new Exception('Tipo não suportado');
    }

    // Crop para quadrado
    $crop_size = min($width, $height);
    $crop_x = ($width - $crop_size) / 2;
    $crop_y = ($height - $crop_size) / 2;

    imagecopyresampled($thumb, $source, 0, 0, $crop_x, $crop_y, $new_width, $new_height, $crop_size, $crop_size);

    // Salva otimizado
    switch ($file['type']) {
        case 'image/jpeg':
            imagejpeg($thumb, $filepath, 85);
            break;
        case 'image/png':
            imagepng($thumb, $filepath, 8);
            break;
        case 'image/gif':
            imagegif($thumb, $filepath);
            break;
        case 'image/webp':
            imagewebp($thumb, $filepath, 85);
            break;
    }

    imagedestroy($source);
    imagedestroy($thumb);

} catch (Exception $e) {
    // Se falhar o resize, mantém o original
}

// Remove avatar antigo se existir
try {
    $stmt = $conn->prepare('SELECT avatar_url FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$admin_id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($old && $old['avatar_url']) {
        $old_file = __DIR__ . '/' . $old['avatar_url'];
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }
} catch (Exception $e) {}

// Atualiza banco de dados
try {
    $stmt = $conn->prepare('UPDATE usuarios SET avatar_url = ?, atualizado_em = NOW() WHERE id = ?');
    $stmt->execute([$relative_path, $admin_id]);

    // Log de auditoria
    try {
        $stmt_log = $conn->prepare('INSERT INTO logs_acesso (usuario_id, tipo_acao, ip, user_agent) VALUES (?, "atualizar_perfil", ?, ?)');
        $stmt_log->execute([
            $admin_id,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {}

    header('Location: perfil.php?msg=sucesso:Avatar atualizado com sucesso!');
    exit;

} catch (Exception $e) {
    header('Location: perfil.php?msg=erro:' . $e->getMessage());
    exit;
}
