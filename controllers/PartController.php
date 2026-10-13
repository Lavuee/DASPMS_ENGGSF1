<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

require_once '../config/Database.php';
require_once '../includes/upload.php';
require_once '../models/Part.php';
require_once '../models/PartImage.php';
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

const PART_IMAGE_DIR = '../assets/images/parts/';

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

/*
    Cashier limits (SRS): a new part can be registered with its selling price (UC10), but changing the
    price of an existing part, seeing or setting the cost price, and archiving are for the Owner.
    For a Cashier edit, the saved price and cost are put back so the form cannot change them.
*/
function keepSavedPricing(PDO $db, $partId) {
    $stmt = $db->prepare("SELECT unit_price, cost_price FROM part WHERE part_id = ? LIMIT 1");
    $stmt->execute([intval($partId)]);
    $saved = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$saved) {
        throw new Exception("Part not found.");
    }

    $_POST['unit_price'] = $saved['unit_price'];
    $_POST['cost_price'] = $saved['cost_price'] ?? '';
}

function requireOwnerArchive() {
    if (!can('inventory.archive')) {
        throw new Exception("Only the Owner can archive, restore or delete parts.");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $database = new Database();
    $db = $database->getConnection();
    $part = new Part($db);
    $canManagePricing = can('pricing.manage');

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
            if (!$canManagePricing) {
                $_POST['cost_price'] = ''; // Cashier does not handle cost prices
            }
            fillPartFromPost($part);
            $part->image = uploadImage('image', PART_IMAGE_DIR, 'part');
            $extraImages = uploadImages('extra_images', PART_IMAGE_DIR, 'part', PartImage::MAX_PER_ITEM);

            if ($part->create()) {
                (new PartImage($db))->addMany($db->lastInsertId(), $extraImages);
                $_SESSION['success_message'] = "Part added successfully.";
            } else {
                deleteImageFiles(PART_IMAGE_DIR, $extraImages);
                $_SESSION['error_message'] = "Part already exists or failed to add.";
            }

            redirectInventory('active');
        }

        if ($_POST['action'] === 'edit') {
            $part->part_id = intval($_POST['part_id']);

            $priceStmt = $db->prepare("SELECT unit_price FROM part WHERE part_id = ? LIMIT 1");
            $priceStmt->execute([$part->part_id]);
            $previousPrice = $priceStmt->fetchColumn();

            if (!$canManagePricing) {
                keepSavedPricing($db, $part->part_id);
            }

            fillPartFromPost($part);
            $part->image = uploadImage('image', PART_IMAGE_DIR, 'part', basename($_POST['existing_image'] ?? ''));
            $previousQuantity = $part->currentQuantity($part->part_id);

            $db->beginTransaction();
            $updated = $part->update();

            // A higher quantity than before means new stock arrived: record it in the Stock-In log.
            if ($updated && $previousQuantity !== null && intval($part->quantity_on_hand) > $previousQuantity) {
                $updated = $part->logStockIn(
                    $part->part_id,
                    intval($part->quantity_on_hand) - $previousQuantity,
                    $part->cost_price,
                    $part->supplier_id,
                    intval($_SESSION['user_id'] ?? 0),
                    cleanPostValue('stock_in_reference'),
                    cleanPostValue('stock_in_notes')
                );
            }

            if ($updated) {
                $db->commit();
            } elseif ($db->inTransaction()) {
                $db->rollBack();
            }

            if ($updated) {
                $partImages = new PartImage($db);

                // Extra photos: remove the ticked ones, save the typed order, then append new uploads.
                $removeIds = array_map('intval', (array) ($_POST['remove_images'] ?? []));
                deleteImageFiles(PART_IMAGE_DIR, $partImages->removeMany($part->part_id, $removeIds));
                $partImages->updateOrder($part->part_id, (array) ($_POST['image_order'] ?? []));

                $slotsLeft = PartImage::MAX_PER_ITEM - $partImages->countForItem($part->part_id);
                $partImages->addMany($part->part_id, uploadImages('extra_images', PART_IMAGE_DIR, 'part', max(0, $slotsLeft)));

                if ($previousPrice !== false && abs(floatval($previousPrice) - floatval($part->unit_price)) >= 0.005) {
                    logActivity($db, 'Changed part price', 'Part', $part->part_id, activityRef($db, 'Part', $part->part_id),
                        '₱' . number_format(floatval($previousPrice), 2) . ' → ₱' . number_format(floatval($part->unit_price), 2));
                }

                $_SESSION['success_message'] = "Part updated successfully.";
            } else {
                $_SESSION['error_message'] = "Part already exists or failed to update.";
            }

            redirectInventory('active');
        }

        if ($_POST['action'] === 'delete' || $_POST['action'] === 'archive') {
            requireOwnerArchive();
            $part->part_id = intval($_POST['part_id']);

            if ($part->archive()) {
                logActivity($db, 'Archived part', 'Part', $part->part_id, activityRef($db, 'Part', $part->part_id));
                $_SESSION['success_message'] = "Part archived successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to archive part.";
            }

            redirectInventory('active');
        }

        if ($_POST['action'] === 'restore') {
            requireOwnerArchive();
            $part->part_id = intval($_POST['part_id']);

            if ($part->restore()) {
                logActivity($db, 'Restored part', 'Part', $part->part_id, activityRef($db, 'Part', $part->part_id));
                $_SESSION['success_message'] = "Part restored successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to restore part.";
            }

            redirectInventory('archived');
        }

        if ($_POST['action'] === 'permanent_delete') {
            requireOwnerArchive();
            $part->part_id = intval($_POST['part_id']);

            if ($part->hasTransactionHistory()) {
                $_SESSION['error_message'] = "This part cannot be permanently deleted because it has existing transaction, order, POS, job order, or stock-in records. Keep it archived instead.";
                redirectInventory('archived');
            }

            $deletedPartName = activityRef($db, 'Part', $part->part_id); // read before the row is gone
            $extraImageFiles = array_column((new PartImage($db))->listForItem($part->part_id), 'image');

            if ($part->deletePermanent()) {
                // The part_image rows are removed by the foreign key; clean up their files too.
                deleteImageFiles(PART_IMAGE_DIR, $extraImageFiles);
                logActivity($db, 'Deleted part', 'Part', $part->part_id, $deletedPartName, 'Deleted permanently from the archive.');
                $_SESSION['success_message'] = "Part permanently deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to permanently delete part. Make sure the part is archived and has no transaction history.";
            }

            redirectInventory('archived');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $_SESSION['error_message'] = $e->getMessage();

        $redirectStatus = cleanPostValue('redirect_status', 'active');
        redirectInventory($redirectStatus);
    }
}

header("Location: ../views/inventory.php");
exit;
?>