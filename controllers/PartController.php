<?php
session_start();

require_once '../config/Database.php';
require_once '../models/Part.php';

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['role'] === 'Customer' ||
    $_SESSION['role'] === 'Head Mechanic'
) {
    header("Location: ../views/login.php");
    exit;
}

function uploadPartImage($fileInputName, $existingImage = '') {
    if (
        !isset($_FILES[$fileInputName]) ||
        $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return $existingImage;
    }

    if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Image upload failed.");
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $originalName = $_FILES[$fileInputName]['name'];
    $tmpName = $_FILES[$fileInputName]['tmp_name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception("Invalid image type. Only JPG, PNG, and WEBP are allowed.");
    }

    $newFileName = 'part-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $uploadDir = '../assets/images/parts/';
    $destination = $uploadDir . $newFileName;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new Exception("Unable to save uploaded image.");
    }

    return $newFileName;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $database = new Database();
    $db = $database->getConnection();
    $part = new Part($db);

    try {
        if ($_POST['action'] === 'add') {
            $part->part_name = trim($_POST['part_name']);
            $part->category = trim($_POST['category']);
            $part->description = trim($_POST['description']);
            $part->full_description = trim($_POST['full_description']);
            $part->quantity_on_hand = $_POST['quantity_on_hand'];
            $part->unit_price = $_POST['unit_price'];
            $part->cost_price = $_POST['cost_price'] !== '' ? $_POST['cost_price'] : ((float) $_POST['unit_price'] * 0.7);
            $part->low_stock_threshold = $_POST['low_stock_threshold'] !== '' ? $_POST['low_stock_threshold'] : 5;
            $part->supplier_reference = trim($_POST['supplier_reference'] ?? '');
            $part->image = uploadPartImage('image');

            if ($part->create()) {
                $_SESSION['success_message'] = "Part added successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to add part.";
            }
        }

        if ($_POST['action'] === 'edit') {
            $part->part_id = $_POST['part_id'];
            $part->part_name = trim($_POST['part_name']);
            $part->category = trim($_POST['category']);
            $part->description = trim($_POST['description']);
            $part->full_description = trim($_POST['full_description']);
            $part->quantity_on_hand = $_POST['quantity_on_hand'];
            $part->unit_price = $_POST['unit_price'];
            $part->cost_price = $_POST['cost_price'] !== '' ? $_POST['cost_price'] : ((float) $_POST['unit_price'] * 0.7);
            $part->low_stock_threshold = $_POST['low_stock_threshold'] !== '' ? $_POST['low_stock_threshold'] : 5;
            $part->supplier_reference = trim($_POST['supplier_reference'] ?? '');
            $part->image = uploadPartImage('image', $_POST['existing_image'] ?? '');

            if ($part->update()) {
                $_SESSION['success_message'] = "Part updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update part.";
            }
        }

        if ($_POST['action'] === 'delete') {
            $part->part_id = $_POST['part_id'];

            if ($part->deactivate()) {
                $_SESSION['success_message'] = "Part deactivated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to deactivate part.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    header("Location: ../views/inventory.php");
    exit;
}

header("Location: ../views/inventory.php");
exit;
?>