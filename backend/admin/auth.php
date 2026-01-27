<?php
/**
 * VERIFICAÇÃO DE AUTENTICAÇÃO - SISTEMA ADONIS
 * Inclua este arquivo no topo de páginas protegidas
 */

session_start();

// Verificar se está logado
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

// Verificar timeout de sessão (30 minutos)
$timeout = 1800; // 30 minutos em segundos
if (isset($_SESSION['login_timestamp']) && (time() - $_SESSION['login_timestamp']) > $timeout) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// Atualizar timestamp
$_SESSION['login_timestamp'] = time();

// Função auxiliar para verificar permissão
function verificarPermissao($tipo_minimo = 'admin') {
    $hierarquia = ['supervisor' => 1, 'admin' => 2];
    
    $nivel_usuario = $hierarquia[$_SESSION['admin_tipo']] ?? 0;
    $nivel_requerido = $hierarquia[$tipo_minimo] ?? 999;
    
    if ($nivel_usuario < $nivel_requerido) {
        header('HTTP/1.1 403 Forbidden');
        die('Acesso negado. Permissão insuficiente.');
    }
}
?>