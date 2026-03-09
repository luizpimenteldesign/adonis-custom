<?php
/**
 * _favicon.php
 * Injetado automaticamente em todas as páginas PHP do admin via .htaccess auto_prepend_file.
 * Usa output buffering para inserir o <link> de favicon após o <head>.
 */
ob_start(function($buffer) {
    $favicon = '<link rel="icon" type="image/png" href="/public/assets/img/favicon.png">'
             . '<link rel="shortcut icon" type="image/png" href="/public/assets/img/favicon.png">'
             . '<link rel="apple-touch-icon" href="/public/assets/img/favicon.png">';
    // Insere logo após <head> (antes do primeiro <meta ou <title)
    return preg_replace('/<head([^>]*)>/i', '<head$1>' . $favicon, $buffer, 1);
});
