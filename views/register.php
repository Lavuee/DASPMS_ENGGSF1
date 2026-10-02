<?php
require_once __DIR__ . '/../includes/auth.php';

$_SESSION['auth_panel'] = 'register';

header("Location: login.php?panel=register");
exit;
?>