<?php
/*
    Activity log (SRS: audit trail). Records who did an important action, when, and on what.
    Used for cancellations, POS voids, payments, archiving/deactivating records and
    automatic reservation expiry. Shown to the Owner on views/activity_log.php.

    Logging must never break the action it describes, so failures are only written
    to the PHP error log.
*/

// $userId null = done automatically by the system (e.g. an expired reservation)
function logActivity(PDO $db, $action, $entityType, $entityId = null, $entityRef = null, $details = null, $userId = 'current') {
    try {
        if ($userId === 'current') {
            $userId = !empty($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
        }

        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action, entity_type, entity_id, entity_ref, details)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            mb_substr((string) $action, 0, 50),
            mb_substr((string) $entityType, 0, 30),
            $entityId !== null ? intval($entityId) : null,
            $entityRef !== null ? mb_substr((string) $entityRef, 0, 60) : null,
            $details !== null && $details !== '' ? mb_substr((string) $details, 0, 2000) : null,
        ]);
    } catch (Exception $e) {
        error_log('Activity log: ' . $e->getMessage());
    }
}

// Readable label for a record, e.g. "JO-3959A", "ABC 1234", "Capacitors" (empty string if not found)
function activityRef(PDO $db, $entityType, $id) {
    $queries = [
        'Job order' => "SELECT job_order_number FROM job_order WHERE job_order_id = ?",
        'Vehicle' => "SELECT plate_number FROM vehicle WHERE vehicle_id = ?",
        'Part' => "SELECT part_name FROM part WHERE part_id = ?",
        'Supplier' => "SELECT supplier_name FROM supplier WHERE supplier_id = ?",
        'Service' => "SELECT service_name FROM service WHERE service_id = ?",
        'Staff account' => "SELECT username FROM user WHERE user_id = ?",
        'Customer' => "SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) FROM customer WHERE customer_id = ?",
        'Invoice' => "SELECT invoice_number FROM invoice WHERE invoice_id = ?",
    ];

    if (!isset($queries[$entityType])) {
        return '';
    }

    try {
        $stmt = $db->prepare($queries[$entityType]);
        $stmt->execute([intval($id)]);
        return (string) ($stmt->fetchColumn() ?: '');
    } catch (Exception $e) {
        return '';
    }
}
