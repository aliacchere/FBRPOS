<?php
// Load environment variables
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Check if installation is complete
if (file_exists('storage/installed.lock')) {
    // Installation is complete, redirect to login
    header('Location: /login.php');
    exit;
} else {
    // Installation not complete, redirect to installer
    header('Location: /install/');
    exit;
}
?>