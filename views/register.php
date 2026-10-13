<?php
require_once __DIR__ . '/../includes/auth.php';

// Sign up lives on the log in page (one sliding page); this opens it in Sign up mode.
header("Location: login.php?panel=register");
exit;
