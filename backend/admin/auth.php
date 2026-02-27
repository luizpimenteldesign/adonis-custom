<?php
session_start();

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

// Timeout 30 minutos
if (isset($_SESSION['login_timestamp']) && (time() - $_SESSION['login_timestamp']) > 1800) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
?>
