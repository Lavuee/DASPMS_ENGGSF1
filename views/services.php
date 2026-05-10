<?php
session_start();

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['role'] === 'Customer' ||
    $_SESSION['role'] === 'Head Mechanic'
) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$stmt = $db->prepare("
    SELECT *
    FROM service
    WHERE is_active = 1
    ORDER BY category ASC, service_name ASC
");
$stmt->execute();

$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

function serviceImagePath($image) {
    if (!$image) {
        return '../assets/images/services/default.png';
    }

    return '../assets/images/services/' . htmlspecialchars($image);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Services - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .services-page {
        width: 100%;
        max-width: 100%;
    }

    .services-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .services-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .services-count-text {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .service-add-btn {
        min-height: 44px;
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-size: 0.9rem;
        font-weight: 800;
        padding: 0.55rem 1rem;
        border-radius: 999px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .service-add-btn i {
        font-size: 0.95rem;
    }

    .service-add-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .services-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 1.5rem;
    }

    .services-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .services-table {
        width: 100%;
        min-width: 1080px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .services-table thead th {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 0.9rem 0.85rem;
        border-bottom: 1px solid #dcdfe4;
        background: transparent;
        white-space: nowrap;
    }

    .services-table tbody td {
        padding: 1rem 0.85rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.95rem;
    }

    .services-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .services-table th:last-child,
    .services-table td:last-child {
        min-width: 150px;
    }

    .service-thumb {
        width: 64px;
        height: 48px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #fff;
    }

    .service-name {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        white-space: nowrap;
    }

    .service-category-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
        background: #fff4cc;
        color: var(--black);
    }

    .service-description-cell {
        max-width: 380px;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        line-height: 1.45;
    }

    .service-price {
        color: #047857;
        font-size: 0.95rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .service-action-group {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .service-action-form {
        margin: 0;
        display: inline-flex;
    }

    .icon-action-btn {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    .icon-action-btn:hover {
        transform: translateY(-1px);
    }

    .icon-action-btn.edit-btn:hover {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .icon-action-btn.delete-btn:hover {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #be123c;
    }

    .services-empty-state {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
    }

    .services-empty-state i {
        display: block;
        color: var(--dashboard-primary);
        font-size: 2.25rem;
        margin-bottom: 0.75rem;
    }

    .services-empty-state .fw-bold {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900 !important;
    }

    .services-pagination-wrap {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.35rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .service-page-btn {
        min-width: 38px;
        min-height: 38px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.45rem 0.75rem;
        transition: 0.2s ease;
    }

    .service-page-btn:hover:not(:disabled) {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .service-page-btn.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .service-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .modal-content {
        border-radius: 20px;
        border: 1px solid var(--border-light);
        overflow: hidden;
    }

    .modal-header {
        border-bottom: 1px solid var(--border-light);
        background: #fffdf5;
    }

    .modal-footer {
        border-top: none;
        padding-top: 0.75rem;
    }

    .modal-title {
        font-size: 1rem;
        font-weight: 900;
    }

    .modal-header small {
        font-size: 0.78rem;
    }

    .minimal-service-form {
        padding: 0.25rem 0;
    }

    .minimal-section-title {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-primary);
        margin-bottom: 1.35rem;
        padding-bottom: 0.85rem;
        border-bottom: 1px solid #dfe3e8;
    }

    .minimal-form-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        column-gap: 1.2rem;
        row-gap: 1.4rem;
    }

    .minimal-form-field {
        min-width: 0;
        grid-column: span 2;
    }

    .minimal-form-field.half {
        grid-column: span 3;
    }

    .minimal-form-field.full {
        grid-column: 1 / -1;
    }

    .minimal-label {
        display: block;
        font-size: 0.76rem;
        font-weight: 800;
        color: var(--dashboard-text-muted);
        margin-bottom: 0.35rem;
    }

    .minimal-control {
        width: 100%;
        border: none;
        border-bottom: 1px solid #cfd6df;
        border-radius: 0;
        padding: 0.45rem 0 0.55rem 0;
        font-size: 0.95rem;
        color: var(--dashboard-text-main);
        background: transparent;
        box-shadow: none;
    }

    .minimal-control:focus {
        border-color: var(--dashboard-primary);
        outline: none;
        box-shadow: none;
        background: transparent;
    }

    textarea.minimal-control {
        min-height: 95px;
        resize: vertical;
        line-height: 1.5;
    }

    input[type="file"].minimal-control {
        cursor: pointer;
    }

    .minimal-helper,
    .features-help {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        margin-top: 0.35rem;
    }

    .minimal-check-field {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding-top: 0.1rem;
    }

    .minimal-check-field .form-check-input {
        width: 18px;
        height: 18px;
        margin: 0;
        border: 1px solid #d1d5db;
        cursor: pointer;
    }

    .minimal-check-field .form-check-input:checked {
        background-color: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
    }

    .minimal-check-field label {
        margin: 0;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
        font-weight: 800;
        cursor: pointer;
    }

    .minimal-cancel-btn {
        border: 1px solid #e5e7eb;
        background: transparent;
        color: var(--dashboard-text-main);
        border-radius: 999px;
        padding: 0.55rem 1rem;
        font-size: 0.9rem;
        font-weight: 800;
        transition: 0.2s ease;
    }

    .minimal-cancel-btn:hover {
        background: #f8fafc;
        color: var(--black);
    }

    .minimal-save-btn {
        border: 1px solid var(--dashboard-primary);
        background: var(--dashboard-primary);
        color: var(--black);
        border-radius: 999px;
        padding: 0.55rem 1rem;
        font-size: 0.9rem;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .minimal-save-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    @media (max-width: 767.98px) {
        .services-header {
            flex-direction: column;
            align-items: stretch;
        }

        .service-add-btn {
            width: 100%;
            justify-content: center;
        }

        .services-header h2 {
            font-size: 1.75rem;
        }

        .services-pagination-wrap {
            justify-content: center;
        }

        .minimal-form-grid {
            grid-template-columns: 1fr;
        }

        .minimal-form-field,
        .minimal-form-field.half,
        .minimal-form-field.full,
        .minimal-check-field {
            grid-column: 1 / -1;
        }

        .modal-footer {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .minimal-cancel-btn,
        .minimal-save-btn {
            width: 100%;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="services-page">

            <div class="services-header">
                <div>
                    <h2>Services Offered</h2>
                    <p class="services-count-text">
                        Manage repair services, public details, images, and base pricing
                    </p>
                </div>

                <button
                    class="btn service-add-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#addServiceModal"
                >
                    <i class="bi bi-plus-lg"></i>
                    Add Service
                </button>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show services-alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show services-alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="services-table-wrap">
                <table class="services-table" id="servicesTable">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Service Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Base Price</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="services-empty-state">
                                        <i class="bi bi-tools"></i>
                                        <div class="fw-bold mb-1">No services found</div>
                                        <div>Add a service to start managing repair service records.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($services as $s): ?>
                            <tr class="service-row">
                                <td>
                                    <img
                                        src="<?= serviceImagePath($s['image'] ?? '') ?>"
                                        alt="<?= htmlspecialchars($s['service_name']) ?>"
                                        class="service-thumb"
                                    >
                                </td>

                                <td>
                                    <div class="service-name">
                                        <?= htmlspecialchars($s['service_name']) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="service-category-badge">
                                        <?= htmlspecialchars($s['category']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="service-description-cell">
                                        <?= htmlspecialchars($s['description']) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="service-price">
                                        ₱<?= number_format((float) $s['base_price'], 2) ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <div class="service-action-group">
                                        <button
                                            type="button"
                                            class="icon-action-btn edit-btn"
                                            title="Edit Service"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editServiceModal<?= (int) $s['service_id'] ?>"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <form
                                            action="../controllers/ServiceController.php"
                                            method="POST"
                                            class="service-action-form"
                                            onsubmit="return confirm('Deactivate this service?');"
                                        >
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="service_id" value="<?= (int) $s['service_id'] ?>">

                                            <button
                                                type="submit"
                                                class="icon-action-btn delete-btn"
                                                title="Deactivate Service"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="editServiceModal<?= (int) $s['service_id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <form
                                            action="../controllers/ServiceController.php"
                                            method="POST"
                                            enctype="multipart/form-data"
                                        >
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="service_id" value="<?= (int) $s['service_id'] ?>">
                                            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($s['image'] ?? '') ?>">

                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Edit Service</h5>
                                                    <small class="text-muted">
                                                        Update repair service details and public information.
                                                    </small>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <div class="minimal-service-form">
                                                    <div class="minimal-section-title">Basic Service Details</div>

                                                    <div class="minimal-form-grid">
                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Service Name</label>
                                                            <input
                                                                type="text"
                                                                name="service_name"
                                                                class="minimal-control"
                                                                value="<?= htmlspecialchars($s['service_name']) ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Category</label>
                                                            <input
                                                                type="text"
                                                                name="category"
                                                                class="minimal-control"
                                                                value="<?= htmlspecialchars($s['category']) ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Base Price</label>
                                                            <input
                                                                type="number"
                                                                name="base_price"
                                                                class="minimal-control"
                                                                step="0.01"
                                                                min="0"
                                                                value="<?= htmlspecialchars($s['base_price']) ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Warranty Days</label>
                                                            <input
                                                                type="number"
                                                                name="warranty_days"
                                                                class="minimal-control"
                                                                min="0"
                                                                value="<?= htmlspecialchars($s['warranty_days']) ?>"
                                                            >
                                                        </div>

                                                        <div class="minimal-check-field">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                name="requires_down_payment"
                                                                id="editDownPayment<?= (int) $s['service_id'] ?>"
                                                                <?= ((int) $s['requires_down_payment'] === 1) ? 'checked' : '' ?>
                                                            >
                                                            <label for="editDownPayment<?= (int) $s['service_id'] ?>">
                                                                Requires down payment
                                                            </label>
                                                        </div>

                                                        <div class="minimal-form-field full">
                                                            <label class="minimal-label">Short Description</label>
                                                            <textarea
                                                                name="description"
                                                                class="minimal-control"
                                                                required
                                                            ><?= htmlspecialchars($s['description']) ?></textarea>
                                                        </div>

                                                        <div class="minimal-form-field full">
                                                            <label class="minimal-label">Full Description</label>
                                                            <textarea
                                                                name="full_description"
                                                                class="minimal-control"
                                                            ><?= htmlspecialchars($s['full_description'] ?? '') ?></textarea>
                                                        </div>

                                                        <div class="minimal-form-field full">
                                                            <label class="minimal-label">Features</label>
                                                            <textarea
                                                                name="features"
                                                                class="minimal-control"
                                                            ><?= htmlspecialchars($s['features'] ?? '') ?></textarea>
                                                            <div class="minimal-helper">
                                                                Enter one feature per line.
                                                            </div>
                                                        </div>

                                                        <div class="minimal-form-field full">
                                                            <label class="minimal-label">Service Image</label>
                                                            <input
                                                                type="file"
                                                                name="image"
                                                                class="minimal-control"
                                                                accept=".jpg,.jpeg,.png,.webp"
                                                            >
                                                            <div class="minimal-helper">
                                                                Leave empty to keep current image.
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal-footer">
                                                <button type="button" class="minimal-cancel-btn" data-bs-dismiss="modal">
                                                    Cancel
                                                </button>
                                                <button type="submit" class="minimal-save-btn">
                                                    Save Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="services-pagination-wrap" id="servicesPagination"></div>

        </div>
    </main>
</div>

<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form
                action="../controllers/ServiceController.php"
                method="POST"
                enctype="multipart/form-data"
            >
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Add Service</h5>
                        <small class="text-muted">
                            Create a new service record for repair jobs and customer service listings.
                        </small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="minimal-service-form">
                        <div class="minimal-section-title">Basic Service Details</div>

                        <div class="minimal-form-grid">
                            <div class="minimal-form-field half">
                                <label class="minimal-label">Service Name</label>
                                <input
                                    type="text"
                                    name="service_name"
                                    class="minimal-control"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field half">
                                <label class="minimal-label">Category</label>
                                <input
                                    type="text"
                                    name="category"
                                    class="minimal-control"
                                    placeholder="Example: Rewinding Services"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field half">
                                <label class="minimal-label">Base Price</label>
                                <input
                                    type="number"
                                    name="base_price"
                                    class="minimal-control"
                                    step="0.01"
                                    min="0"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field half">
                                <label class="minimal-label">Warranty Days</label>
                                <input
                                    type="number"
                                    name="warranty_days"
                                    class="minimal-control"
                                    min="0"
                                    value="0"
                                >
                            </div>

                            <div class="minimal-check-field">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="requires_down_payment"
                                    id="addDownPayment"
                                >
                                <label for="addDownPayment">
                                    Requires down payment
                                </label>
                            </div>

                            <div class="minimal-form-field full">
                                <label class="minimal-label">Short Description</label>
                                <textarea
                                    name="description"
                                    class="minimal-control"
                                    required
                                ></textarea>
                            </div>

                            <div class="minimal-form-field full">
                                <label class="minimal-label">Full Description</label>
                                <textarea
                                    name="full_description"
                                    class="minimal-control"
                                ></textarea>
                            </div>

                            <div class="minimal-form-field full">
                                <label class="minimal-label">Features</label>
                                <textarea
                                    name="features"
                                    class="minimal-control"
                                ></textarea>
                                <div class="minimal-helper">
                                    Enter one feature per line.
                                </div>
                            </div>

                            <div class="minimal-form-field full">
                                <label class="minimal-label">Service Image</label>
                                <input
                                    type="file"
                                    name="image"
                                    class="minimal-control"
                                    accept=".jpg,.jpeg,.png,.webp"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="minimal-cancel-btn" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="minimal-save-btn">
                        Add Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ITEMS_PER_PAGE = 5;

    const serviceRows = Array.from(document.querySelectorAll('.service-row'));
    const servicesPagination = document.getElementById('servicesPagination');

    let currentPage = 1;

    function renderPagination(totalPages) {
        if (!servicesPagination) {
            return;
        }

        servicesPagination.innerHTML = '';

        if (totalPages <= 1) {
            servicesPagination.style.display = 'none';
            return;
        }

        servicesPagination.style.display = 'flex';

        const prevButton = document.createElement('button');
        prevButton.type = 'button';
        prevButton.className = 'service-page-btn';
        prevButton.innerHTML = '&laquo;';
        prevButton.disabled = currentPage === 1;
        prevButton.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                applyServicePagination();
            }
        });
        servicesPagination.appendChild(prevButton);

        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement('button');
            pageButton.type = 'button';
            pageButton.className = 'service-page-btn' + (page === currentPage ? ' active' : '');
            pageButton.textContent = page;
            pageButton.addEventListener('click', function () {
                currentPage = page;
                applyServicePagination();
            });
            servicesPagination.appendChild(pageButton);
        }

        const nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.className = 'service-page-btn';
        nextButton.innerHTML = '&raquo;';
        nextButton.disabled = currentPage === totalPages;
        nextButton.addEventListener('click', function () {
            if (currentPage < totalPages) {
                currentPage++;
                applyServicePagination();
            }
        });
        servicesPagination.appendChild(nextButton);
    }

    function applyServicePagination() {
        const totalPages = Math.ceil(serviceRows.length / ITEMS_PER_PAGE) || 1;

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        const end = start + ITEMS_PER_PAGE;

        serviceRows.forEach(function (row) {
            row.style.display = 'none';
        });

        serviceRows.slice(start, end).forEach(function (row) {
            row.style.display = '';
        });

        renderPagination(totalPages);
    }

    applyServicePagination();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>