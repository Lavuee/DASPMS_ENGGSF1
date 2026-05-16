<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer' || $_SESSION['role'] === 'Head Mechanic') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
$db = (new Database())->getConnection();

// Fetch active suppliers
$stmt = $db->query("SELECT * FROM supplier WHERE is_active = 1 ORDER BY supplier_name ASC");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Suppliers - Norily's Repair Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; }
        .page-header h2 { font-size: 2rem; font-weight: 900; color: var(--dashboard-text-main); margin-bottom: 0.25rem; }
        .count-text { color: var(--dashboard-text-muted); font-size: 0.95rem; font-weight: 500; }
        
        .action-btn { background: var(--dashboard-primary); border: none; color: var(--black); font-weight: 800; padding: 0.6rem 1.2rem; border-radius: 999px; display: inline-flex; align-items: center; gap: 0.5rem; transition: 0.2s ease; }
        .action-btn:hover { background: var(--black); color: var(--white); }

        .minimal-table { width: 100%; border-collapse: collapse; }
        .minimal-table th { color: var(--dashboard-text-muted); font-size: 0.82rem; font-weight: 900; text-transform: uppercase; padding: 1rem; border-bottom: 1px solid #dcdfe4; }
        .minimal-table td { padding: 1.2rem 1rem; border-bottom: 1px solid #e8ebef; color: var(--dashboard-text-main); font-size: 0.95rem; vertical-align: middle; }
        .minimal-table tr:hover td { background: rgba(245, 197, 24, 0.035); }

        .icon-btn { width: 36px; height: 36px; border-radius: 10px; border: 1px solid #e5e7eb; background: #fff; color: var(--dashboard-text-muted); display: inline-flex; align-items: center; justify-content: center; transition: 0.2s ease; }
        .icon-btn:hover { background: var(--dashboard-primary); border-color: var(--dashboard-primary); color: var(--black); }
        .icon-btn.archive-btn:hover { background: #fff1f2; border-color: #fecdd3; color: #be123c; }

        /* Minimal Modal Styling */
        .modal-content { border-radius: 20px; border: none; }
        .minimal-control { width: 100%; border: none; border-bottom: 1px solid #cfd6df; padding: 0.5rem 0; font-size: 0.95rem; background: transparent; }
        .minimal-control:focus { border-color: var(--dashboard-primary); outline: none; }
        .minimal-label { font-size: 0.76rem; font-weight: 800; color: var(--dashboard-text-muted); text-transform: uppercase; }
    </style>
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h2>Supplier Directory</h2>
                <p class="count-text">Manage your parts distributors and automated restock contacts.</p>
            </div>
            <button class="action-btn" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                <i class="bi bi-plus-lg"></i> Add Supplier
            </button>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success border-0 rounded-4"><i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <div style="background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; overflow: hidden;">
            <table class="minimal-table">
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Email (Restock Alerts)</th>
                        <th>Phone</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($s['supplier_name']) ?></td>
                            <td><?= htmlspecialchars($s['contact_person'] ?: 'N/A') ?></td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($s['email']) ?>" style="color: #1d4ed8; text-decoration: none; font-weight: 600;">
                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($s['email']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($s['phone'] ?: 'N/A') ?></td>
                            <td class="text-end">
                                <button class="icon-btn" data-bs-toggle="modal" data-bs-target="#editSupplierModal<?= $s['supplier_id'] ?>"><i class="bi bi-pencil-square"></i></button>
                                <form action="../controllers/SupplierController.php" method="POST" style="display:inline;" onsubmit="return confirm('Archive this supplier?');">
                                    <input type="hidden" name="action" value="archive">
                                    <input type="hidden" name="supplier_id" value="<?= $s['supplier_id'] ?>">
                                    <button class="icon-btn archive-btn"><i class="bi bi-archive"></i></button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal fade" id="editSupplierModal<?= $s['supplier_id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content p-2">
                                    <form action="../controllers/SupplierController.php" method="POST">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="supplier_id" value="<?= $s['supplier_id'] ?>">
                                        <div class="modal-header border-0">
                                            <h5 class="fw-bold mb-0">Edit Supplier</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-4">
                                                <label class="minimal-label">Company Name *</label>
                                                <input type="text" name="supplier_name" class="minimal-control" value="<?= htmlspecialchars($s['supplier_name']) ?>" required>
                                            </div>
                                            <div class="mb-4">
                                                <label class="minimal-label">Email Address (For Automated Restock) *</label>
                                                <input type="email" name="email" class="minimal-control" value="<?= htmlspecialchars($s['email']) ?>" required>
                                            </div>
                                            <div class="row g-3 mb-4">
                                                <div class="col-6">
                                                    <label class="minimal-label">Contact Person</label>
                                                    <input type="text" name="contact_person" class="minimal-control" value="<?= htmlspecialchars($s['contact_person']) ?>">
                                                </div>
                                                <div class="col-6">
                                                    <label class="minimal-label">Phone</label>
                                                    <input type="text" name="phone" class="minimal-control" value="<?= htmlspecialchars($s['phone']) ?>">
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="minimal-label">Address</label>
                                                <input type="text" name="address" class="minimal-control" value="<?= htmlspecialchars($s['address']) ?>">
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0">
                                            <button type="submit" class="action-btn w-100 justify-content-center">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
            <form action="../controllers/SupplierController.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-0">
                    <h5 class="fw-bold mb-0">New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="minimal-label">Company Name *</label>
                        <input type="text" name="supplier_name" class="minimal-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="minimal-label">Email Address (For Automated Restock) *</label>
                        <input type="email" name="email" class="minimal-control" required>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="minimal-label">Contact Person</label>
                            <input type="text" name="contact_person" class="minimal-control">
                        </div>
                        <div class="col-6">
                            <label class="minimal-label">Phone</label>
                            <input type="text" name="phone" class="minimal-control">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="minimal-label">Address</label>
                        <input type="text" name="address" class="minimal-control">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="action-btn w-100 justify-content-center">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>