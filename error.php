<?php
/*
    Apache error pages in the DASPMS design (see ErrorDocument in .htaccess).
    error.php?code=404 for a page that doesn't exist, error.php?code=403 for blocked files/folders.
*/
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/error_page.php';

$code = intval($_GET['code'] ?? 404) === 403 ? 403 : 404;

$actions = [];
if (isLoggedIn()) {
    $actions[] = ['label' => 'Go to my dashboard', 'href' => 'views/' . dashboardForRole($_SESSION['role'] ?? ''), 'primary' => true];
    $actions[] = ['label' => '← Back to home', 'href' => ''];
} else {
    $actions[] = ['label' => 'Back to home', 'href' => '', 'primary' => true];
    $actions[] = ['label' => 'Log in', 'href' => 'views/login.php'];
}

if ($code === 403) {
    renderErrorPage(403, 'Access denied', 'This page is not available', "You don't have access to this address. Use the buttons below to get back to the system.", $actions);
}

renderErrorPage(404, 'Page not found', "We can't find that page", 'The link may be mistyped, or the page was moved. Check the address, or use the buttons below.', $actions);
