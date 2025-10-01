<?php
require_once 'includes/auth.php';

try {
    $pdo = getDatabaseConnection();
    $auth = new Auth($pdo);
    $auth->logout();
} catch (Exception $e) {
    // Even if database fails, destroy session
    session_start();
    session_destroy();
}

header('Location: /login.php');
exit;
?>