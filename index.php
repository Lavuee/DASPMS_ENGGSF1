<?php
// The landing page lives at the project root URL; send /index.php to it so the
// file name is never part of the site's navigation.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#/index\.php$#i', $requestPath)) {
    header("Location: " . substr($requestPath, 0, -strlen('index.php')), true, 302);
    exit;
}

require_once __DIR__ . '/views/landing.php';
