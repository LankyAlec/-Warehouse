<?php

/* Escape HTML */
if (!function_exists('h')) {
    function h($s){
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

/* Redirect helper */
if (!function_exists('redirect')) {
    function redirect(string $path){
        header("Location: " . BASE_URL . $path);
        exit;
    }
}

/* Check root */
if (!function_exists('is_root')) {
    function is_root(): bool {
        return (($_SESSION['privilegi'] ?? '') === 'root');
    }
}

/* Require root (BLOCCA ACCESSO) */
if (!function_exists('require_root')) {
    function require_root(){
        if (empty($_SESSION['utente_id']) || !is_root()) {
            redirect('/dashboard.php');
        }
    }
}
