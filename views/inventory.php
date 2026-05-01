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

$stmt = $db->prepare("
    SELECT *
    FROM part
    WHERE is_active = 1
    ORDER BY category ASC, part_name ASC
");
$stmt->execute();

$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function partImagePath($image) {
    if (!$image) {
        return '../assets/images/parts/default.png';
    }

    return '../assets/images/parts/' . htmlspecialchars($image);
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
        margin-bottom: 1.5rem;
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
        min-width: 1180px;
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
        min-width: 150px;
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

    .part-price {
        color: #047857;
        font-size: 0.95rem;
        font-weight: 900;
        white-space: nowrap;
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

    .icon-action-btn.delete-btn:hover {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #be123c;
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
                        Manage parts, stock, pricing, images, and public details
                    </p>
                </div>

                <button
                    class="btn part-add-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#addPartModal"
                >
                    <i class="bi bi-plus-lg"></i>
                    Add Part
                </button>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show inventory-alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show inventory-alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="inventory-table-wrap">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Part Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Stock</th>
                            <th>Unit Price</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($parts)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="inventory-empty-state">
                                        <i class="bi bi-box-seam"></i>
                                        <div class="fw-bold mb-1">No parts found</div>
                                        <div>Add a part to start managing inventory records.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($parts as $p): ?>
                            <tr>
                                <td>
                                    <img
                                        src="<?= partImagePath($p['image'] ?? '') ?>"
                                        alt="<?= htmlspecialchars($p['part_name']) ?>"
                                        class="part-thumb"
                                    >
                                </td>

                                <td>
                                    <div class="part-name">
                                        <?= htmlspecialchars($p['part_name']) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="part-category-badge">
                                        <?= htmlspecialchars($p['category']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="part-description-cell">
                                        <?= htmlspecialchars($p['description'] ?? '') ?>
                                    </div>
                                </td>

                                <td>
                                    <?php if ((int) $p['quantity_on_hand'] <= (int) $p['low_stock_threshold']): ?>
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
                                </td>

                                <td class="text-end">
                                    <div class="part-action-group">
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
                                            onsubmit="return confirm('Deactivate this part?');"
                                        >
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="part_id" value="<?= (int) $p['part_id'] ?>">

                                            <button
                                                type="submit"
                                                class="icon-action-btn delete-btn"
                                                title="Deactivate Part"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

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
                                            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($p['image'] ?? '') ?>">

                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Edit Part</h5>
                                                    <small class="text-muted">
                                                        Update inventory details for this part.
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
                                                                value="<?= htmlspecialchars($p['part_name']) ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Category</label>
                                                            <input
                                                                type="text"
                                                                name="category"
                                                                class="minimal-control"
                                                                value="<?= htmlspecialchars($p['category']) ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field">
                                                            <label class="minimal-label">Quantity</label>
                                                            <input
                                                                type="number"
                                                                name="quantity_on_hand"
                                                                class="minimal-control"
                                                                min="0"
                                                                value="<?= htmlspecialchars($p['quantity_on_hand']) ?>"
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
                                                                value="<?= htmlspecialchars($p['unit_price']) ?>"
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
                                                                value="<?= htmlspecialchars($p['cost_price'] ?? '') ?>"
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Low Stock Threshold</label>
                                                            <input
                                                                type="number"
                                                                name="low_stock_threshold"
                                                                class="minimal-control"
                                                                min="0"
                                                                value="<?= htmlspecialchars($p['low_stock_threshold']) ?>"
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Supplier Reference</label>
                                                            <input
                                                                type="text"
                                                                name="supplier_reference"
                                                                class="minimal-control"
                                                                value="<?= htmlspecialchars($p['supplier_reference'] ?? '') ?>"
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field full">
                                                            <label class="minimal-label">Short Description</label>
                                                            <textarea
                                                                name="description"
                                                                class="minimal-control"
                                                                required
                                                            ><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
                                                        </div>

                                                        <div class="minimal-form-field full">
                                                            <label class="minimal-label">Full Description</label>
                                                            <textarea
                                                                name="full_description"
                                                                class="minimal-control"
                                                            ><?= htmlspecialchars($p['full_description'] ?? '') ?></textarea>
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
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<div class="modal fade" id="addPartModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form
                action="../controllers/PartController.php"
                method="POST"
                enctype="multipart/form-data"
            >
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Add Part</h5>
                        <small class="text-muted">
                            Create a new part record for inventory and customer web orders.
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>