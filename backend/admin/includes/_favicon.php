<?php
/**
 * _favicon.php
 * Injetado automaticamente em todas as páginas PHP do admin via .htaccess auto_prepend_file.
 * Usa output buffering para inserir as tags de favicon logo após <head>.
 */
ob_start(function($buffer) {
    $favicon = '<link rel="icon" type="image/png" href="/public/assets/img/favicon.png">'
             . '<link rel="shortcut icon" type="image/png" href="/public/assets/img/favicon.png">'
             . '<link rel="apple-touch-icon" href="/public/assets/img/favicon.png">';
    return preg_replace('/<head([^>]*)>/i', '<head$1>' . $favicon, $buffer, 1);
});
