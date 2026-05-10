<?php
session_start();

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['role'] === 'Customer' ||
    $_SESSION['role'] === 'Head Mechanic'
) {
    header("Location: ../views/login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$viewStatus = $_GET['status'] ?? 'active';
$viewStatus = $viewStatus === 'archived' ? 'archived' : 'active';
$isActiveView = $viewStatus === 'active';
$isActiveValue = $isActiveView ? 1 : 0;

$itemsPerPage = 5;
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;

if ($currentPage < 1) {
    $currentPage = 1;
}

$countActiveStmt = $db->prepare("SELECT COUNT(*) FROM part WHERE is_active = 1");
$countActiveStmt->execute();
$activeCount = intval($countActiveStmt->fetchColumn());

$countArchivedStmt = $db->prepare("SELECT COUNT(*) FROM part WHERE is_active = 0");
$countArchivedStmt->execute();
$archivedCount = intval($countArchivedStmt->fetchColumn());

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM part
    WHERE is_active = :is_active
");
$countStmt->bindValue(':is_active', $isActiveValue, PDO::PARAM_INT);
$countStmt->execute();
$totalParts = intval($countStmt->fetchColumn());

$totalPages = max(1, ceil($totalParts / $itemsPerPage));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $itemsPerPage;

$stmt = $db->prepare("
    SELECT *
    FROM part
    WHERE is_active = :is_active
    ORDER BY category ASC, part_name ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':is_active', $isActiveValue, PDO::PARAM_INT);
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function partImagePath($image) {
    if (!$image) {
        return '../assets/images/parts/default.png';
    }

    return '../assets/images/parts/' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
}

function cleanValue($value, $fallback = 'N/A') {
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        return htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function inventoryPageUrl($page, $status = 'active') {
    $page = max(1, intval($page));
    $status = $status === 'archived' ? 'archived' : 'active';

    return 'inventory.php?status=' . $status . '&page=' . $page;
}

function inventoryStatusUrl($status) {
    $status = $status === 'archived' ? 'archived' : 'active';

    return 'inventory.php?status=' . $status;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .inventory-page {
        width: 100%;
        max-width: 100%;
    }

    .inventory-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.15rem;
    }

    .inventory-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .inventory-count-text {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .part-add-btn {
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

    .part-add-btn i {
        font-size: 0.95rem;
    }

    .part-add-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .inventory-tabs {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        flex-wrap: wrap;
        margin-bottom: 1.35rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.08);
    }

    .inventory-tab-link {
        border: 1px solid #e5e7eb;
        background: rgba(255, 255, 255, 0.55);
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        padding: 0.5rem 0.9rem;
        font-size: 0.86rem;
        font-weight: 900;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.42rem;
        transition: 0.2s ease;
    }

    .inventory-tab-link:hover {
        color: var(--black);
        background: rgba(245, 197, 24, 0.14);
        border-color: rgba(245, 197, 24, 0.45);
    }

    .inventory-tab-link.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .inventory-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 1.5rem;
    }

    .inventory-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .inventory-table {
        width: 100%;
        min-width: 1380px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .inventory-table thead th {
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

    .inventory-table tbody td {
        padding: 1rem 0.85rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.95rem;
    }

    .inventory-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .inventory-table th:last-child,
    .inventory-table td:last-child {
        min-width: 160px;
    }

    .part-thumb {
        width: 64px;
        height: 48px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #fff;
    }

    .part-name {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        white-space: nowrap;
    }

    .part-brand {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 700;
        margin-top: 0.15rem;
        white-space: nowrap;
    }

    .part-category-badge {
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

    .part-description-cell {
        max-width: 310px;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        line-height: 1.45;
    }

    .part-spec-cell {
        max-width: 360px;
        font-size: 0.88rem;
        line-height: 1.45;
        color: var(--dashboard-text-main);
    }

    .part-spec-line {
        display: block;
        margin-bottom: 0.25rem;
    }

    .part-spec-label {
        color: var(--dashboard-text-muted);
        font-size: 0.74rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.25px;
    }

    .stock-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .stock-low {
        background: #fef3c7;
        color: #b45309;
    }

    .stock-available {
        background: rgba(16, 185, 129, 0.12);
        color: #047857;
    }

    .stock-archived {
        background: #f3f4f6;
        color: #4b5563;
    }

    .part-price {
        color: #047857;
        font-size: 0.95rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .part-unit {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 700;
        margin-top: 0.2rem;
    }

    .supplier-cell {
        max-width: 230px;
        color: var(--dashboard-text-main);
        font-size: 0.86rem;
        line-height: 1.4;
    }

    .supplier-ref {
        font-weight: 800;
        color: var(--dashboard-text-main);
    }

    .supplier-email-link {
        color: #1d4ed8;
        font-size: 0.78rem;
        font-weight: 800;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        margin-top: 0.25rem;
    }

    .supplier-email-link:hover {
        text-decoration: underline;
    }

    .part-action-group {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .part-action-form {
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

    .icon-action-btn.archive-btn:hover {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #be123c;
    }

    .icon-action-btn.restore-btn:hover {
        background: #ecfdf5;
        border-color: #bbf7d0;
        color: #15803d;
    }

    .icon-action-btn.permanent-delete-btn:hover {
        background: #fee2e2;
        border-color: #fecaca;
        color: #b91c1c;
    }

    .inventory-empty-state {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
    }

    .inventory-empty-state i {
        display: block;
        color: var(--dashboard-primary);
        font-size: 2.25rem;
        margin-bottom: 0.75rem;
    }

    .inventory-empty-state .fw-bold {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900 !important;
    }

    .inventory-pagination-wrap {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.35rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .pagination-controls {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
    }

    .page-link-custom {
        min-width: 38px;
        min-height: 38px;
        border-radius: 999px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-muted);
        text-decoration: none;
        font-size: 0.82rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.45rem 0.75rem;
        transition: 0.2s ease;
    }

    .page-link-custom:hover {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .page-link-custom.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .page-link-custom.disabled {
        pointer-events: none;
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

    .minimal-part-form {
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
    .help-text {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        margin-top: 0.35rem;
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
        .inventory-header {
            flex-direction: column;
            align-items: stretch;
        }

        .part-add-btn {
            width: 100%;
            justify-content: center;
        }

        .inventory-header h2 {
            font-size: 1.75rem;
        }

        .inventory-tabs {
            align-items: stretch;
            flex-direction: column;
        }

        .inventory-tab-link {
            justify-content: center;
        }

        .inventory-pagination-wrap {
            justify-content: center;
        }

        .pagination-controls {
            justify-content: center;
        }

        .minimal-form-grid {
            grid-template-columns: 1fr;
        }

        .minimal-form-field,
        .minimal-form-field.half,
        .minimal-form-field.full {
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
        <div class="inventory-page">

            <div class="inventory-header">
                <div>
                    <h2>Parts Inventory</h2>
                    <p class="inventory-count-text">
                        Manage parts, stock, pricing, images, specifications, compatibility, and supplier details
                    </p>
                </div>

                <?php if ($isActiveView): ?>
                    <button
                        class="btn part-add-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#addPartModal"
                    >
                        <i class="bi bi-plus-lg"></i>
                        Add Part
                    </button>
                <?php endif; ?>
            </div>

            <div class="inventory-tabs">
                <a
                    href="<?= inventoryStatusUrl('active') ?>"
                    class="inventory-tab-link <?= $isActiveView ? 'active' : '' ?>"
                >
                    <i class="bi bi-box-seam"></i>
                    Active Parts (<?= $activeCount ?>)
                </a>

                <a
                    href="<?= inventoryStatusUrl('archived') ?>"
                    class="inventory-tab-link <?= !$isActiveView ? 'active' : '' ?>"
                >
                    <i class="bi bi-archive"></i>
                    Archived Parts (<?= $archivedCount ?>)
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show inventory-alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show inventory-alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="inventory-table-wrap">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Part Details</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Specifications</th>
                            <th>Stock</th>
                            <th>Unit Price</th>
                            <th>Supplier</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($parts)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="inventory-empty-state">
                                        <i class="bi <?= $isActiveView ? 'bi-box-seam' : 'bi-archive' ?>"></i>
                                        <div class="fw-bold mb-1">
                                            <?= $isActiveView ? 'No active parts found' : 'No archived parts found' ?>
                                        </div>
                                        <div>
                                            <?= $isActiveView ? 'Add a part to start managing inventory records.' : 'Archived parts will appear here.' ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($parts as $p): ?>
                            <tr>
                                <td>
                                    <img
                                        src="<?= partImagePath($p['image'] ?? '') ?>"
                                        alt="<?= cleanValue($p['part_name'] ?? 'Part image') ?>"
                                        class="part-thumb"
                                    >
                                </td>

                                <td>
                                    <div class="part-name">
                                        <?= cleanValue($p['part_name'] ?? 'N/A') ?>
                                    </div>

                                    <div class="part-brand">
                                        Brand: <?= cleanValue($p['brand'] ?? '', 'Unspecified') ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="part-category-badge">
                                        <?= cleanValue($p['category'] ?? 'N/A') ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="part-description-cell">
                                        <?= cleanValue($p['description'] ?? '') ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="part-spec-cell">
                                        <span class="part-spec-line">
                                            <span class="part-spec-label">Spec:</span>
                                            <?= cleanValue($p['specification'] ?? '', 'No specification') ?>
                                        </span>

                                        <span class="part-spec-line">
                                            <span class="part-spec-label">Compatibility:</span>
                                            <?= cleanValue($p['compatibility'] ?? '', 'Not specified') ?>
                                        </span>

                                        <span class="part-spec-line">
                                            <span class="part-spec-label">Unit:</span>
                                            <?= cleanValue($p['unit'] ?? '', 'piece') ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <?php if (!$isActiveView): ?>
                                        <span class="stock-badge stock-archived">
                                            Archived
                                        </span>
                                    <?php elseif ((int) $p['quantity_on_hand'] <= (int) $p['low_stock_threshold']): ?>
                                        <span class="stock-badge stock-low">
                                            <?= (int) $p['quantity_on_hand'] ?> Low
                                        </span>
                                    <?php else: ?>
                                        <span class="stock-badge stock-available">
                                            <?= (int) $p['quantity_on_hand'] ?> Available
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="part-price">
                                        ₱<?= number_format((float) $p['unit_price'], 2) ?>
                                    </span>

                                    <div class="part-unit">
                                        per <?= cleanValue($p['unit'] ?? '', 'piece') ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="supplier-cell">
                                        <div class="supplier-ref">
                                            <?= cleanValue($p['supplier_reference'] ?? '', 'No supplier ref') ?>
                                        </div>

                                        <?php if (!empty($p['supplier_email'])): ?>
                                            <a
                                                href="mailto:<?= htmlspecialchars($p['supplier_email'], ENT_QUOTES, 'UTF-8') ?>?subject=Parts Inquiry - <?= rawurlencode($p['part_name'] ?? 'Part') ?>"
                                                class="supplier-email-link"
                                                title="Send email to supplier"
                                            >
                                                <i class="bi bi-envelope"></i>
                                                <?= htmlspecialchars($p['supplier_email'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php else: ?>
                                            <div class="part-brand">No supplier email</div>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <div class="part-action-group">
                                        <?php if ($isActiveView): ?>
                                            <button
                                                type="button"
                                                class="icon-action-btn edit-btn"
                                                title="Edit Part"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editPartModal<?= (int) $p['part_id'] ?>"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <form
                                                action="../controllers/PartController.php"
                                                method="POST"
                                                class="part-action-form"
                                                onsubmit="return confirm('Archive this part? It will be removed from active inventory but kept in archived records.');"
                                            >
                                                <input type="hidden" name="action" value="archive">
                                                <input type="hidden" name="part_id" value="<?= (int) $p['part_id'] ?>">
                                                <input type="hidden" name="redirect_status" value="active">

                                                <button
                                                    type="submit"
                                                    class="icon-action-btn archive-btn"
                                                    title="Archive Part"
                                                >
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form
                                                action="../controllers/PartController.php"
                                                method="POST"
                                                class="part-action-form"
                                                onsubmit="return confirm('Restore this part to active inventory?');"
                                            >
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="part_id" value="<?= (int) $p['part_id'] ?>">
                                                <input type="hidden" name="redirect_status" value="archived">

                                                <button
                                                    type="submit"
                                                    class="icon-action-btn restore-btn"
                                                    title="Restore Part"
                                                >
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </form>

                                            <form
                                                action="../controllers/PartController.php"
                                                method="POST"
                                                class="part-action-form"
                                                onsubmit="return confirm('Permanently delete this archived part? This action cannot be undone. It will only work if the part has no transaction history.');"
                                            >
                                                <input type="hidden" name="action" value="permanent_delete">
                                                <input type="hidden" name="part_id" value="<?= (int) $p['part_id'] ?>">
                                                <input type="hidden" name="redirect_status" value="archived">

                                                <button
                                                    type="submit"
                                                    class="icon-action-btn permanent-delete-btn"
                                                    title="Delete Permanently"
                                                >
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <?php if ($isActiveView): ?>
                                <div class="modal fade" id="editPartModal<?= (int) $p['part_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <form
                                                action="../controllers/PartController.php"
                                                method="POST"
                                                enctype="multipart/form-data"
                                            >
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="part_id" value="<?= (int) $p['part_id'] ?>">
                                                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($p['image'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="redirect_status" value="active">

                                                <div class="modal-header">
                                                    <div>
                                                        <h5 class="modal-title">Edit Part</h5>
                                                        <small class="text-muted">
                                                            Update inventory details, specifications, compatibility, and supplier information.
                                                        </small>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="minimal-part-form">
                                                        <div class="minimal-section-title">Basic Part Details</div>

                                                        <div class="minimal-form-grid">
                                                            <div class="minimal-form-field half">
                                                                <label class="minimal-label">Part Name</label>
                                                                <input
                                                                    type="text"
                                                                    name="part_name"
                                                                    class="minimal-control"
                                                                    value="<?= cleanValue($p['part_name'] ?? '') ?>"
                                                                    required
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field half">
                                                                <label class="minimal-label">Category</label>
                                                                <input
                                                                    type="text"
                                                                    name="category"
                                                                    class="minimal-control"
                                                                    value="<?= cleanValue($p['category'] ?? '') ?>"
                                                                    required
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field half">
                                                                <label class="minimal-label">Brand</label>
                                                                <input
                                                                    type="text"
                                                                    name="brand"
                                                                    class="minimal-control"
                                                                    value="<?= cleanValue($p['brand'] ?? '') ?>"
                                                                    placeholder="Example: Bosch, NGK, Motolite"
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field half">
                                                                <label class="minimal-label">Unit</label>
                                                                <input
                                                                    type="text"
                                                                    name="unit"
                                                                    class="minimal-control"
                                                                    value="<?= cleanValue($p['unit'] ?? 'piece') ?>"
                                                                    placeholder="Example: piece, set, meter, box"
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field">
                                                                <label class="minimal-label">Quantity</label>
                                                                <input
                                                                    type="number"
                                                                    name="quantity_on_hand"
                                                                    class="minimal-control"
                                                                    min="0"
                                                                    value="<?= htmlspecialchars($p['quantity_on_hand'] ?? 0, ENT_QUOTES, 'UTF-8') ?>"
                                                                    required
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field">
                                                                <label class="minimal-label">Unit Price</label>
                                                                <input
                                                                    type="number"
                                                                    name="unit_price"
                                                                    class="minimal-control"
                                                                    step="0.01"
                                                                    min="0"
                                                                    value="<?= htmlspecialchars($p['unit_price'] ?? 0, ENT_QUOTES, 'UTF-8') ?>"
                                                                    required
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field">
                                                                <label class="minimal-label">Cost Price</label>
                                                                <input
                                                                    type="number"
                                                                    name="cost_price"
                                                                    class="minimal-control"
                                                                    step="0.01"
                                                                    min="0"
                                                                    value="<?= htmlspecialchars($p['cost_price'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field half">
                                                                <label class="minimal-label">Low Stock Threshold</label>
                                                                <input
                                                                    type="number"
                                                                    name="low_stock_threshold"
                                                                    class="minimal-control"
                                                                    min="0"
                                                                    value="<?= htmlspecialchars($p['low_stock_threshold'] ?? 5, ENT_QUOTES, 'UTF-8') ?>"
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field half">
                                                                <label class="minimal-label">Supplier Reference</label>
                                                                <input
                                                                    type="text"
                                                                    name="supplier_reference"
                                                                    class="minimal-control"
                                                                    value="<?= cleanValue($p['supplier_reference'] ?? '') ?>"
                                                                    placeholder="Supplier name or contact person"
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field half">
                                                                <label class="minimal-label">Supplier Email</label>
                                                                <input
                                                                    type="email"
                                                                    name="supplier_email"
                                                                    class="minimal-control"
                                                                    value="<?= htmlspecialchars($p['supplier_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                    placeholder="supplier@email.com"
                                                                >
                                                            </div>

                                                            <div class="minimal-form-field full">
                                                                <label class="minimal-label">Short Description</label>
                                                                <textarea
                                                                    name="description"
                                                                    class="minimal-control"
                                                                    required
                                                                ><?= cleanValue($p['description'] ?? '') ?></textarea>
                                                            </div>

                                                            <div class="minimal-form-field full">
                                                                <label class="minimal-label">Specification</label>
                                                                <textarea
                                                                    name="specification"
                                                                    class="minimal-control"
                                                                    placeholder="Example: 12V, 40A, iridium, copper wire 1.2mm, etc."
                                                                ><?= cleanValue($p['specification'] ?? '') ?></textarea>
                                                            </div>

                                                            <div class="minimal-form-field full">
                                                                <label class="minimal-label">Compatibility</label>
                                                                <textarea
                                                                    name="compatibility"
                                                                    class="minimal-control"
                                                                    placeholder="Example: Toyota Vios 2014-2019, Honda City, Universal, etc."
                                                                ><?= cleanValue($p['compatibility'] ?? '') ?></textarea>
                                                            </div>

                                                            <div class="minimal-form-field full">
                                                                <label class="minimal-label">Full Description</label>
                                                                <textarea
                                                                    name="full_description"
                                                                    class="minimal-control"
                                                                ><?= cleanValue($p['full_description'] ?? '') ?></textarea>
                                                            </div>

                                                            <div class="minimal-form-field full">
                                                                <label class="minimal-label">Part Image</label>
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
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="inventory-pagination-wrap">
                    <div class="pagination-controls">
                        <a
                            href="<?= inventoryPageUrl($currentPage - 1, $viewStatus) ?>"
                            class="page-link-custom <?= $currentPage <= 1 ? 'disabled' : '' ?>"
                            title="Previous page"
                        >
                            <i class="bi bi-chevron-left"></i>
                        </a>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a
                                href="<?= inventoryPageUrl($i, $viewStatus) ?>"
                                class="page-link-custom <?= $i === $currentPage ? 'active' : '' ?>"
                            >
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <a
                            href="<?= inventoryPageUrl($currentPage + 1, $viewStatus) ?>"
                            class="page-link-custom <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"
                            title="Next page"
                        >
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<?php if ($isActiveView): ?>
    <div class="modal fade" id="addPartModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form
                    action="../controllers/PartController.php"
                    method="POST"
                    enctype="multipart/form-data"
                >
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="redirect_status" value="active">

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">Add Part</h5>
                            <small class="text-muted">
                                Create a new part record with specifications for inventory and customer web orders.
                            </small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="minimal-part-form">
                            <div class="minimal-section-title">Basic Part Details</div>

                            <div class="minimal-form-grid">
                                <div class="minimal-form-field half">
                                    <label class="minimal-label">Part Name</label>
                                    <input
                                        type="text"
                                        name="part_name"
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
                                        placeholder="Example: Electrical Parts & Supplies"
                                        required
                                    >
                                </div>

                                <div class="minimal-form-field half">
                                    <label class="minimal-label">Brand</label>
                                    <input
                                        type="text"
                                        name="brand"
                                        class="minimal-control"
                                        placeholder="Example: Bosch, NGK, Motolite"
                                    >
                                </div>

                                <div class="minimal-form-field half">
                                    <label class="minimal-label">Unit</label>
                                    <input
                                        type="text"
                                        name="unit"
                                        class="minimal-control"
                                        value="piece"
                                        placeholder="Example: piece, set, meter, box"
                                    >
                                </div>

                                <div class="minimal-form-field">
                                    <label class="minimal-label">Quantity</label>
                                    <input
                                        type="number"
                                        name="quantity_on_hand"
                                        class="minimal-control"
                                        min="0"
                                        required
                                    >
                                </div>

                                <div class="minimal-form-field">
                                    <label class="minimal-label">Unit Price</label>
                                    <input
                                        type="number"
                                        name="unit_price"
                                        class="minimal-control"
                                        step="0.01"
                                        min="0"
                                        required
                                    >
                                </div>

                                <div class="minimal-form-field">
                                    <label class="minimal-label">Cost Price</label>
                                    <input
                                        type="number"
                                        name="cost_price"
                                        class="minimal-control"
                                        step="0.01"
                                        min="0"
                                    >
                                </div>

                                <div class="minimal-form-field half">
                                    <label class="minimal-label">Low Stock Threshold</label>
                                    <input
                                        type="number"
                                        name="low_stock_threshold"
                                        class="minimal-control"
                                        min="0"
                                        value="5"
                                    >
                                </div>

                                <div class="minimal-form-field half">
                                    <label class="minimal-label">Supplier Reference</label>
                                    <input
                                        type="text"
                                        name="supplier_reference"
                                        class="minimal-control"
                                        placeholder="Supplier name or contact person"
                                    >
                                </div>

                                <div class="minimal-form-field half">
                                    <label class="minimal-label">Supplier Email</label>
                                    <input
                                        type="email"
                                        name="supplier_email"
                                        class="minimal-control"
                                        placeholder="supplier@email.com"
                                    >
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
                                    <label class="minimal-label">Specification</label>
                                    <textarea
                                        name="specification"
                                        class="minimal-control"
                                        placeholder="Example: 12V, 40A, iridium, copper wire 1.2mm, etc."
                                    ></textarea>
                                </div>

                                <div class="minimal-form-field full">
                                    <label class="minimal-label">Compatibility</label>
                                    <textarea
                                        name="compatibility"
                                        class="minimal-control"
                                        placeholder="Example: Toyota Vios 2014-2019, Honda City, Universal, etc."
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
                                    <label class="minimal-label">Part Image</label>
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
                            Add Part
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>