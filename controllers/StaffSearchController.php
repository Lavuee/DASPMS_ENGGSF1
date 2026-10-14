<?php
/*
    Quick search for the staff header (read-only).
    GET ?q=term  ->  JSON { groups: [ { label, items: [ { title, meta, href } ] } ] }
    Each group is only searched when the logged-in role may open that page (includes/rbac.php).
    Results link to the list page with ?q=..., which fills that page's own search box.
*/
require_once __DIR__ . '/../includes/auth.php';
require_once '../config/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') === 'Customer') {
    http_response_code(403);
    echo json_encode(['groups' => []]);
    exit;
}

$term = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($term) < 2) {
    echo json_encode(['groups' => []]);
    exit;
}
$term = mb_substr($term, 0, 60);
$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
$limit = 5;

$groups = [];

try {
    $db = (new Database())->getConnection();

    $find = function ($sql, array $params) use ($db) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $link = function ($page, $q) {
        return $page . '?q=' . rawurlencode($q);
    };

    if (can('job_orders.view')) {
        $rows = $find("
            SELECT jo.job_order_number, jo.status, c.first_name, c.last_name, v.plate_number, v.make, v.model
            FROM job_order jo
            LEFT JOIN customer c ON c.customer_id = jo.customer_id
            LEFT JOIN vehicle v ON v.vehicle_id = jo.vehicle_id
            WHERE jo.job_order_number LIKE ? OR v.plate_number LIKE ?
               OR CONCAT_WS(' ', c.first_name, c.last_name) LIKE ?
            ORDER BY jo.date_created DESC
            LIMIT $limit
        ", [$like, $like, $like]);

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'title' => $r['job_order_number'] . ' · ' . trim($r['first_name'] . ' ' . $r['last_name']),
                'meta' => trim(($r['make'] ?? '') . ' ' . ($r['model'] ?? '')) . ($r['plate_number'] ? ' · ' . $r['plate_number'] : '') . ' · ' . $r['status'],
                'href' => $link('job_orders.php', $r['job_order_number']),
            ];
        }
        if ($items) $groups[] = ['label' => 'Job orders', 'items' => $items];
    }

    if (can('service_requests.review')) {
        $rows = $find("
            SELECT sr.request_number, sr.status, c.first_name, c.last_name, s.service_name
            FROM service_request sr
            LEFT JOIN customer c ON c.customer_id = sr.customer_id
            LEFT JOIN service s ON s.service_id = sr.service_id
            WHERE sr.request_number LIKE ? OR CONCAT_WS(' ', c.first_name, c.last_name) LIKE ?
            ORDER BY sr.created_at DESC
            LIMIT $limit
        ", [$like, $like]);

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'title' => $r['request_number'] . ' · ' . trim($r['first_name'] . ' ' . $r['last_name']),
                'meta' => ($r['service_name'] ?? 'Service') . ' · ' . $r['status'],
                'href' => $link('service_requests.php', $r['request_number']),
            ];
        }
        if ($items) $groups[] = ['label' => 'Service requests', 'items' => $items];
    }

    if (can('billing.manage')) {
        $rows = $find("
            SELECT i.invoice_number, i.invoice_type, i.payment_status, i.total_amount, c.first_name, c.last_name
            FROM invoice i
            LEFT JOIN customer c ON c.customer_id = i.customer_id
            WHERE i.invoice_number LIKE ? OR CONCAT_WS(' ', c.first_name, c.last_name) LIKE ?
            ORDER BY i.date_issued DESC
            LIMIT $limit
        ", [$like, $like]);

        $items = [];
        foreach ($rows as $r) {
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            $items[] = [
                'title' => $r['invoice_number'] . ($name !== '' ? ' · ' . $name : ''),
                'meta' => $r['invoice_type'] . ' · ₱' . number_format((float) $r['total_amount'], 2) . ' · ' . $r['payment_status'],
                'href' => $link('invoice_history.php', $r['invoice_number']),
            ];
        }
        if ($items) $groups[] = ['label' => 'Invoices', 'items' => $items];
    }

    if (can('customers.manage')) {
        $rows = $find("
            SELECT first_name, last_name, contact_number, email, status
            FROM customer
            WHERE CONCAT_WS(' ', first_name, last_name) LIKE ? OR contact_number LIKE ? OR email LIKE ?
            ORDER BY last_name, first_name
            LIMIT $limit
        ", [$like, $like, $like]);

        $items = [];
        foreach ($rows as $r) {
            $name = trim($r['first_name'] . ' ' . $r['last_name']);
            $items[] = [
                'title' => $name,
                'meta' => ($r['contact_number'] ?: ($r['email'] ?: '—')) . ' · ' . $r['status'],
                'href' => $link('customers.php', $name),
            ];
        }
        if ($items) $groups[] = ['label' => 'Customers', 'items' => $items];
    }

    if (can('vehicles.manage')) {
        $rows = $find("
            SELECT v.plate_number, v.make, v.model, v.year, c.first_name, c.last_name
            FROM vehicle v
            LEFT JOIN customer c ON c.customer_id = v.customer_id
            WHERE v.plate_number LIKE ? OR CONCAT_WS(' ', v.make, v.model) LIKE ?
            ORDER BY v.plate_number
            LIMIT $limit
        ", [$like, $like]);

        $items = [];
        foreach ($rows as $r) {
            $plate = $r['plate_number'] ?: '—';
            $items[] = [
                'title' => $plate . ' · ' . trim($r['make'] . ' ' . $r['model']),
                'meta' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) . ($r['year'] ? ' · ' . $r['year'] : ''),
                'href' => $link('vehicles.php', $r['plate_number'] ?: trim($r['make'] . ' ' . $r['model'])),
            ];
        }
        if ($items) $groups[] = ['label' => 'Vehicles', 'items' => $items];
    }

    if (can('inventory.manage') || can('inventory.view')) {
        $page = can('inventory.manage') ? 'inventory.php' : 'inventory_view.php';
        $rows = $find("
            SELECT part_name, brand, quantity_on_hand, low_stock_threshold
            FROM part
            WHERE is_active = 1 AND (part_name LIKE ? OR brand LIKE ? OR category LIKE ?)
            ORDER BY part_name
            LIMIT $limit
        ", [$like, $like, $like]);

        $items = [];
        foreach ($rows as $r) {
            $qty = intval($r['quantity_on_hand']);
            $state = $qty <= 0 ? 'Out of stock' : ($qty <= intval($r['low_stock_threshold']) ? 'Low stock' : 'In stock');
            $items[] = [
                'title' => $r['part_name'],
                'meta' => ($r['brand'] ? $r['brand'] . ' · ' : '') . $qty . ' left · ' . $state,
                'href' => $link($page, $r['part_name']),
            ];
        }
        if ($items) $groups[] = ['label' => 'Parts', 'items' => $items];
    }
} catch (Exception $e) {
    error_log('Staff search: ' . $e->getMessage());
}

echo json_encode(['groups' => $groups], JSON_UNESCAPED_UNICODE);
