<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =====================================================
// ADONIS CUSTOM - CONFIGURAÇÃO PRINCIPAL
// =====================================================

// Configuração do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'luizpi39_adns_app');
define('DB_USER', 'luizpi39_adns');
define('DB_PASS', 'a[Ne3KC][3OT');
define('DB_CHARSET', 'utf8mb4');

// Configuração de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configuração de erros (DESABILITAR EM PRODUÇÃO)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Headers CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configurações do sistema
define('TOKEN_EXPIRATION_DAYS', 30);
define('PUBLIC_TOKEN_LENGTH', 64);
define('ENABLE_AI', false);

// URLs base (AJUSTAR EM PRODUÇÃO)
define('BASE_URL', 'http://localhost');
define('API_URL', BASE_URL . '/backend/api');

// Configuração de notificações
define('WHATSAPP_ENABLED', true);
define('EMAIL_ENABLED', false);
define('ADMIN_PHONE', '27999999999');
define('ADMIN_EMAIL', 'admin@adonis.local');

?>
