<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Head Mechanic') { 
    header("Location: login.php"); 
    exit; 
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$stmt = $db->prepare("
    SELECT *
    FROM part
    ORDER BY part_name ASC
");
$stmt->execute();

$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalParts = count($parts);
$lowStockCount = 0;

foreach ($parts as $p) {
    if ($p['quantity_on_hand'] <= $p['low_stock_threshold']) {
        $lowStockCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parts Availability - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .parts-view-page {
        width: 100%;
        max-width: 100%;
    }

    .pv-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .pv-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .pv-count-text {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .pv-readonly-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        background: rgba(245, 197, 24, 0.14);
        border: 1px solid rgba(245, 197, 24, 0.25);
        color: var(--black);
        border-radius: 999px;
        padding: 0.55rem 0.9rem;
        font-size: 0.85rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .pv-filter-area {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 220px 110px;
        gap: 0.85rem;
        align-items: end;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .pv-filter-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .pv-search-bar {
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: rgba(255, 255, 255, 0.56);
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        padding: 0.65rem 0.9rem;
        box-shadow: none;
    }

    .pv-search-bar:focus-within {
        border-color: rgba(245, 197, 24, 0.65);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
        background: rgba(255, 255, 255, 0.90);
    }

    .pv-search-bar i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .pv-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
    }

    .pv-filter-select {
        height: 44px;
        border-radius: 12px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.55rem 0.85rem;
        font-size: 0.92rem;
        color: var(--dashboard-text-main);
        background-color: rgba(255, 255, 255, 0.56);
        box-shadow: none;
    }

    .pv-filter-select:focus {
        border-color: rgba(245, 197, 24, 0.65);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
        background-color: rgba(255, 255, 255, 0.90);
    }

    .pv-clear-btn {
        height: 44px;
        border-radius: 999px;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
        font-weight: 800;
        border: 1px solid rgba(15, 23, 42, 0.10);
        background: rgba(255, 255, 255, 0.56);
        color: var(--dashboard-text-main);
        transition: 0.2s ease;
    }

    .pv-clear-btn:hover {
        background: rgba(255, 255, 255, 0.90);
        color: var(--black);
    }

    .pv-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .pv-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
        background: transparent;
    }

    .pv-table thead th {
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

    .pv-table tbody td {
        padding: 1rem 0.85rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        background: transparent;
    }

    .pv-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .pv-part-name {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-category-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: var(--dashboard-text-main);
        border-radius: 999px;
        padding: 0.38rem 0.68rem;
        font-size: 0.78rem;
        font-weight: 800;
        line-height: 1.35;
        max-width: 190px;
        white-space: normal;
    }

    .pv-description {
        color: var(--dashboard-text-muted);
        font-size: 0.9rem;
        line-height: 1.45;
        max-width: 440px;
    }

    .pv-stock {
        font-size: 0.95rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .pv-stock.low {
        color: #dc2626;
    }

    .pv-stock.ok {
        color: #047857;
    }

    .pv-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .pv-status.out {
        background: #fee2e2;
        color: #b91c1c;
    }

    .pv-status.low {
        background: #fef3c7;
        color: #92400e;
    }

    .pv-status.ok {
        background: #dcfce7;
        color: #047857;
    }

    .pv-empty-state {
        text-align: center;
        color: var(--dashboard-text-muted);
        padding: 3rem 1rem;
    }

    .pv-empty-state i {
        display: block;
        color: var(--dashboard-primary);
        font-size: 2rem;
        margin-bottom: 0.65rem;
    }

    @media (max-width: 991.98px) {
        .pv-filter-area {
            grid-template-columns: 1fr;
        }

        .pv-clear-btn {
            width: 100%;
        }
    }

    @media (max-width: 767.98px) {
        .pv-header {
            flex-direction: column;
            align-items: stretch;
        }

        .pv-readonly-pill {
            justify-content: center;
        }

        .pv-header h2 {
            font-size: 1.75rem;
        }

        .pv-search-bar,
        .pv-filter-select,
        .pv-clear-btn {
            height: 42px;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="parts-view-page">

            <div class="pv-header">
                <div>
                    <h2>Parts Availability</h2>
                    <p class="pv-count-text">
                        Workshop View (Read-Only) • <?php echo $totalParts; ?> parts tracked • <?php echo $lowStockCount; ?> low stock
                    </p>
                </div>

                <div class="pv-readonly-pill">
                    <i class="bi bi-eye"></i>
                    Read-Only View
                </div>
            </div>

            <div class="pv-filter-area">
                <div>
                    <label class="pv-filter-label" for="partSearch">Search</label>
                    <div class="pv-search-bar">
                        <i class="bi bi-search"></i>
                        <input
                            type="text"
                            id="partSearch"
                            placeholder="Search by part name, category, description, or status..."
                        >
                    </div>
                </div>

                <div>
                    <label class="pv-filter-label" for="stockFilter">Stock Status</label>
                    <select id="stockFilter" class="form-select pv-filter-select">
                        <option value="All">All Parts</option>
                        <option value="In Stock">In Stock</option>
                        <option value="Low Stock">Low Stock</option>
                        <option value="Out of Stock">Out of Stock</option>
                    </select>
                </div>

                <button type="button" id="clearFilters" class="pv-clear-btn">
                    Clear
                </button>
            </div>

            <div class="pv-table-wrap">
                <table class="pv-table" id="partsTable">
                    <thead>
                        <tr>
                            <th>Part Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Current Stock</th>
                            <th class="text-end">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($parts) > 0): ?>
                            <?php foreach ($parts as $p): ?>
                                <?php
                                    $isLowStock = $p['quantity_on_hand'] <= $p['low_stock_threshold'];

                                    if (intval($p['quantity_on_hand']) === 0) {
                                        $statusText = 'Out of Stock';
                                        $statusClass = 'out';
                                    } elseif ($isLowStock) {
                                        $statusText = 'Low Stock';
                                        $statusClass = 'low';
                                    } else {
                                        $statusText = 'In Stock';
                                        $statusClass = 'ok';
                                    }

                                    $searchText = strtolower(
                                        ($p['part_name'] ?? '') . ' ' .
                                        ($p['category'] ?? '') . ' ' .
                                        ($p['description'] ?? '') . ' ' .
                                        $statusText
                                    );
                                ?>

                                <tr
                                    class="part-row"
                                    data-status="<?php echo htmlspecialchars($statusText); ?>"
                                    data-search="<?php echo htmlspecialchars($searchText); ?>"
                                >
                                    <td>
                                        <div class="pv-part-name" title="<?php echo htmlspecialchars($p['part_name']); ?>">
                                            <?php echo htmlspecialchars($p['part_name']); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="pv-category-badge">
                                            <?php echo htmlspecialchars($p['category']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="pv-description">
                                            <?php echo htmlspecialchars($p['description']); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="pv-stock <?php echo $isLowStock ? 'low' : 'ok'; ?>">
                                            <?php echo htmlspecialchars($p['quantity_on_hand']); ?>
                                        </span>
                                    </td>

                                    <td class="text-end">
                                        <span class="pv-status <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($statusText); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr id="noPartResults" style="display:none;">
                                <td colspan="5">
                                    <div class="pv-empty-state">
                                        <i class="bi bi-search"></i>
                                        <div class="fw-bold mb-1">No matching parts found</div>
                                        <div>Try another keyword or stock status filter.</div>
                                    </div>
                                </td>
                            </tr>

                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="pv-empty-state">
                                        <i class="bi bi-box-seam"></i>
                                        <div class="fw-bold mb-1">No parts found</div>
                                        <div>No inventory records are available for workshop viewing.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<script>
const partSearch = document.getElementById('partSearch');
const stockFilter = document.getElementById('stockFilter');
const clearFilters = document.getElementById('clearFilters');
const rows = document.querySelectorAll('.part-row');
const noResults = document.getElementById('noPartResults');

function applyPartFilters() {
    const searchValue = partSearch.value.trim().toLowerCase();
    const stockValue = stockFilter.value;

    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.dataset.search || '';
        const rowStatus = row.dataset.status || '';

        const matchesSearch = rowSearch.includes(searchValue);
        const matchesStatus = stockValue === 'All' || rowStatus === stockValue;

        if (matchesSearch && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noResults) {
        noResults.style.display = visibleCount === 0 && rows.length > 0 ? '' : 'none';
    }
}

if (partSearch) {
    partSearch.addEventListener('input', applyPartFilters);
}

if (stockFilter) {
    stockFilter.addEventListener('change', applyPartFilters);
}

if (clearFilters) {
    clearFilters.addEventListener('click', function () {
        partSearch.value = '';
        stockFilter.value = 'All';
        applyPartFilters();
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>