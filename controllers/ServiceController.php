<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

require_once '../config/Database.php';
require_once '../includes/upload.php';
require_once '../models/Service.php';
require_once '../models/ServiceImage.php';
require_once '../includes/business_rules.php';

const SERVICE_IMAGE_DIR = '../assets/images/services/';

requirePermission('services.manage', '../views/login.php');

// Copies the submitted form fields onto the Service model.
function fillServiceFromPost(Service $service) {
    $service->service_name = trim($_POST['service_name'] ?? '');
    $service->category = trim($_POST['category'] ?? '');
    $service->base_price = floatval($_POST['base_price'] ?? 0);
    $service->warranty_days = intval($_POST['warranty_days'] ?? 0);
    $service->description = trim($_POST['description'] ?? '');
    $service->full_description = trim($_POST['full_description'] ?? '');
    $service->features = trim($_POST['features'] ?? '');
    $service->requires_down_payment = isset($_POST['requires_down_payment']) ? 1 : 0;

    if ($service->service_name === '' || $service->category === '') {
        throw new Exception("Service name and category are required.");
    }

    if ($service->base_price < 0 || $service->warranty_days < 0) {
        throw new Exception("Price and warranty days cannot be negative.");
    }

    enforceMinimumServiceFee($service->base_price, 'Base price');
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();
    $service = new Service($db);

    try {
        if ($_POST['action'] === 'add') {
            fillServiceFromPost($service);
            $service->image = uploadImage('image', SERVICE_IMAGE_DIR, 'service');
            $extraImages = uploadImages('extra_images', SERVICE_IMAGE_DIR, 'service', ServiceImage::MAX_PER_ITEM);

            if ($service->create()) {
                (new ServiceImage($db))->addMany($db->lastInsertId(), $extraImages);
                $_SESSION['success_message'] = "Service added successfully.";
            } else {
                deleteImageFiles(SERVICE_IMAGE_DIR, $extraImages);
                $_SESSION['error_message'] = "Failed to add service.";
            }
        }

        if ($_POST['action'] === 'edit') {
            $service->service_id = intval($_POST['service_id'] ?? 0);

            $priceStmt = $db->prepare("SELECT base_price FROM service WHERE service_id = ? LIMIT 1");
            $priceStmt->execute([$service->service_id]);
            $previousPrice = $priceStmt->fetchColumn();

            // SRS UC09 + pricing policy: only the Owner changes an existing service's price
            if (!can('pricing.manage') && $previousPrice !== false) {
                $_POST['base_price'] = $previousPrice;
            }

            fillServiceFromPost($service);
            $service->image = uploadImage('image', SERVICE_IMAGE_DIR, 'service', basename($_POST['existing_image'] ?? ''));

            if ($service->update()) {
                if ($previousPrice !== false && abs(floatval($previousPrice) - floatval($service->base_price)) >= 0.005) {
                    logActivity($db, 'Changed service price', 'Service', $service->service_id, activityRef($db, 'Service', $service->service_id),
                        '₱' . number_format(floatval($previousPrice), 2) . ' → ₱' . number_format(floatval($service->base_price), 2));
                }

                $serviceImages = new ServiceImage($db);

                // Extra photos: remove the ticked ones, save the typed order, then append new uploads.
                $removeIds = array_map('intval', (array) ($_POST['remove_images'] ?? []));
                deleteImageFiles(SERVICE_IMAGE_DIR, $serviceImages->removeMany($service->service_id, $removeIds));
                $serviceImages->updateOrder($service->service_id, (array) ($_POST['image_order'] ?? []));

                $slotsLeft = ServiceImage::MAX_PER_ITEM - $serviceImages->countForItem($service->service_id);
                $serviceImages->addMany($service->service_id, uploadImages('extra_images', SERVICE_IMAGE_DIR, 'service', max(0, $slotsLeft)));

                $_SESSION['success_message'] = "Service updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update service.";
            }
        }

        if ($_POST['action'] === 'delete') {
            $service->service_id = intval($_POST['service_id'] ?? 0);

            if ($service->deactivate()) {
                logActivity($db, 'Deactivated service', 'Service', $service->service_id, activityRef($db, 'Service', $service->service_id));
                $_SESSION['success_message'] = "Service deactivated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to deactivate service.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

header("Location: ../views/services.php");
exit;
