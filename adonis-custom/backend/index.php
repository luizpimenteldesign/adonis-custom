<?php
require_once 'config/config.php';

echo json_encode([
    'sistema' => 'Adonis Custom - OS',
    'versao' => '1.0.0',
    'status' => 'online',
    'api_url' => API_URL
]);
?>
