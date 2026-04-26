<?php
// Router for PHP built-in server
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$uri = trim($uri, '/');

// Serve existing files directly
if ($uri === '' || file_exists(__DIR__ . '/' . $uri)) {
    return false;
}

// Route category pages
if (preg_match('/^[a-z_]+$/', $uri)) {
    require __DIR__ . '/category.php';
    return true;
}

// Everything else 404
http_response_code(404);
echo "404 Not Found";
return true;
