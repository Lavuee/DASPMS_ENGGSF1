<?php
require_once __DIR__ . '/../includes/auth.php';

require_once '../config/Database.php';
require_once '../includes/upload.php';
require_once '../models/Part.php';
require_once '../controllers/RestockAlert.php';

requirePermission('inventory.manage', '../views/login.php');

function cleanPostValue($key, $default = '') {
    return trim($_POST[$key] ?? $default);
}

function redirectInventory($status = 'active') {
    $status = $status === 'archived' ? 'archived' : 'active';
    header("Location: ../views/inventory.php?status=" . $status);
    exit;
}

function validatePartInputs() {
    $partName = cleanPostValue('part_name');
    $category = cleanPostValue('category');
    $description = cleanPostValue('description');
    $quantity = intval($_POST['quantity_on_hand'] ?? -1);
    $unitPrice = floatval($_POST['unit_price'] ?? -1);
    $costPrice = $_POST['cost_price'] ?? '';
    $lowStockThreshold = $_POST['low_stock_threshold'] ?? '';

    if ($partName === '') {
        throw new Exception("Part name is required.");
    }

    if ($category === '') {
        throw new Exception("Category is required.");
    }

    if ($description === '') {
        throw new Exception("Short description is required.");
    }

    if ($quantity < 0) {
        throw new Exception("Quantity cannot be negative.");
    }

    if ($unitPrice <= 0) {
        throw new Exception("Unit price must be greater than zero.");
    }

    if ($costPrice !== '' && floatval($costPrice) < 0) {
        throw new Exception("Cost price cannot be negative.");
    }

    if ($lowStockThreshold !== '' && intval($lowStockThreshold) < 0) {
        throw new Exception("Low stock threshold cannot be negative.");
    }
}

// Validates the submitted form and copies it onto the Part model.
function fillPartFromPost(Part $part) {
    validatePartInputs();

    $unitPrice = floatval($_POST['unit_price']);

    $part->part_name = cleanPostValue('part_name');
    $part->category = cleanPostValue('category');
    $part->brand = cleanPostValue('brand');
    $part->description = cleanPostValue('description');
    $part->specification = cleanPostValue('specification');
    $part->compatibility = cleanPostValue('compatibility');
    $part->unit = cleanPostValue('unit', 'piece');
    $part->full_description = cleanPostValue('full_description');
    $part->quantity_on_hand = intval($_POST['quantity_on_hand']);
    $part->unit_price = $unitPrice;
    // Cost defaults to 70% of the selling price when left blank.
    $part->cost_price = ($_POST['cost_price'] ?? '') !== '' ? floatval($_POST['cost_price']) : ($unitPrice * 0.7);
    $part->low_stock_threshold = ($_POST['low_stock_threshold'] ?? '') !== '' ? intval($_POST['low_stock_threshold']) : 5;
    $part->supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $database = new Database();
    $db = $database->getConnection();
    $part = new Part($db);

    try {
        if ($_POST['action'] === 'send_restock_alert') {
            $part_id = intval($_POST['part_id']);
            
            $result = RestockAlert::manualSend($db, $part_id);
            
            if ($result['success']) {
                $_SESSION['success_message'] = $result['message'];
            } else {
                $_SESSION['error_message'] = $result['message'];
            }
            
            $redirectStatus = cleanPostValue('redirect_status', 'active');
            redirectInventory($redirectStatus);
        }
        
        if ($_POST['action'] === 'add') {
            fillPartFromPost($part);
            $part->image = uploadImage('image', '../assets/images/parts/', 'part');

            if ($part->create()) {
                $_SESSION['success_message'] = "Part added successfully.";
            } else {
                $_SESSION['error_message'] = "Part already exists or failed to add.";
            }

            redirectInventory('active');
        }

        if ($_POST['action'] === 'edit') {
            $part->part_id = intval($_POST['part_id']);
            fillPartFromPost($part);
            $part->image = uploadImage('image', '../assets/images/parts/', 'part', basename($_POST['existing_image'] ?? ''));

            if ($part->update()) {
                $_SESSION['success_message'] = "Part updated successfully.";
            } else {
                $_SESSION['error_message'] = "Part already exists or failed to update.";
            }

            redirectInventory('active');
        }

        if ($_POST['action'] === 'delete' || $_POST['action'] === 'archive') {
            $part->part_id = intval($_POST['part_id']);

            if ($part->archive()) {
                $_SESSION['success_message'] = "Part archived successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to archive part.";
            }

            redirectInventory('active');
        }

        if ($_POST['action'] === 'restore') {
            $part->part_id = intval($_POST['part_id']);

            if ($part->restore()) {
                $_SESSION['success_message'] = "Part restored successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to restore part.";
            }

            redirectInventory('archived');
        }

        if ($_POST['action'] === 'permanent_delete') {
            $part->part_id = intval($_POST['part_id']);

            if ($part->hasTransactionHistory()) {
                $_SESSION['error_message'] = "This part cannot be permanently deleted because it has existing transaction, order, POS, job order, or stock-in records. Keep it archived instead.";
                redirectInventory('archived');
            }

            if ($part->deletePermanent()) {
                $_SESSION['success_message'] = "Part permanently deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to permanently delete part. Make sure the part is archived and has no transaction history.";
            }

            redirectInventory('archived');
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();

        $redirectStatus = cleanPostValue('redirect_status', 'active');
        redirectInventory($redirectStatus);
    }
}

header("Location: ../views/inventory.php");
exit;
?>