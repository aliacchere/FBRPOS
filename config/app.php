<?php
// Simple app configuration without Laravel dependencies
return [
    'name' => $_ENV['APP_NAME'] ?? 'DPS POS FBR Integrated',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => 'Asia/Karachi',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'key' => $_ENV['APP_KEY'] ?? 'base64:' . base64_encode(random_bytes(32)),
    'cipher' => 'AES-256-CBC',
];