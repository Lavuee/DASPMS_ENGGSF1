<?php
session_start();

$_SESSION['auth_panel'] = 'register';

header("Location: login.php?panel=register");
exit;
?>