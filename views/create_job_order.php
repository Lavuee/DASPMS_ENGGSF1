<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('job_orders.manage');

// New job orders are created in the popup on Job Orders (same form, same controller).
// Old links and bookmarks to this page open that popup instead.
header("Location: job_orders.php?new=1");
exit;
