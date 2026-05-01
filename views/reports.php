<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
$db = (new Database())->getConnection();

$filter = $_GET['filter'] ?? 'week';
$reportType = $_GET['report_type'] ?? 'all';

$allowedFilters = ['today', 'week', 'month', 'custom'];
$allowedReportTypes = ['all', 'sales', 'inventory', 'jobs'];

if (!in_array($filter, $allowedFilters)) {
    $filter = 'week';
}

if (!in_array($reportType, $allowedReportTypes)) {
    $reportType = 'all';
}

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$customDateError = '';
$dateFilter = "";
$dateParams = [];
$reportTitle = "This Week's Report";

/* =========================
   DATE FILTER
========================= */
if ($filter === 'today') {
    $dateFilter = "DATE(p.payment_date) = CURDATE()";
    $jobCreatedFilter = "DATE(date_created) = CURDATE()";
    $jobCompletedFilter = "DATE(date_completed) = CURDATE()";
    $reportTitle = "Today's Report";
} elseif ($filter === 'month') {
    $dateFilter = "YEAR(p.payment_date) = YEAR(CURDATE()) AND MONTH(p.payment_date) = MONTH(CURDATE())";
    $jobCreatedFilter = "YEAR(date_created) = YEAR(CURDATE()) AND MONTH(date_created) = MONTH(CURDATE())";
    $jobCompletedFilter = "YEAR(date_completed) = YEAR(CURDATE()) AND MONTH(date_completed) = MONTH(CURDATE())";
    $reportTitle = "This Month's Report";
} elseif ($filter === 'custom') {
    if ($startDate === '' || $endDate === '') {
        $customDateError = "Please select both start date and end date for a custom report.";

        $filter = 'week';
        $dateFilter = "YEARWEEK(p.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
        $jobCreatedFilter = "YEARWEEK(date_created, 1) = YEARWEEK(CURDATE(), 1)";
        $jobCompletedFilter = "YEARWEEK(date_completed, 1) = YEARWEEK(CURDATE(), 1)";
        $reportTitle = "This Week's Report";
    } elseif (strtotime($startDate) > strtotime($endDate)) {
        $customDateError = "Invalid date range. Start date must not be later than end date.";

        $filter = 'week';
        $dateFilter = "YEARWEEK(p.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
        $jobCreatedFilter = "YEARWEEK(date_created, 1) = YEARWEEK(CURDATE(), 1)";
        $jobCompletedFilter = "YEARWEEK(date_completed, 1) = YEARWEEK(CURDATE(), 1)";
        $reportTitle = "This Week's Report";
    } else {
        $dateFilter = "DATE(p.payment_date) BETWEEN ? AND ?";
        $jobCreatedFilter = "DATE(date_created) BETWEEN " . $db->quote($startDate) . " AND " . $db->quote($endDate);
        $jobCompletedFilter = "DATE(date_completed) BETWEEN " . $db->quote($startDate) . " AND " . $db->quote($endDate);
        $dateParams = [$startDate, $endDate];
        $reportTitle = "Custom Report (" . date('M d, Y', strtotime($startDate)) . " - " . date('M d, Y', strtotime($endDate)) . ")";
    }
} else {
    $filter = 'week';
    $dateFilter = "YEARWEEK(p.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
    $jobCreatedFilter = "YEARWEEK(date_created, 1) = YEARWEEK(CURDATE(), 1)";
    $jobCompletedFilter = "YEARWEEK(date_completed, 1) = YEARWEEK(CURDATE(), 1)";
    $reportTitle = "This Week's Report";
}

/* =========================
   REVENUE
========================= */
$query = "
    SELECT i.invoice_type, SUM(p.amount) AS total
    FROM payment p
    JOIN invoice i ON p.invoice_id = i.invoice_id
    WHERE $dateFilter
    GROUP BY i.invoice_type
";

$stmt = $db->prepare($query);
$stmt->execute($dateParams);

$serviceRevenue = 0;
$partsRevenue = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['invoice_type'] === 'Service') {
        $serviceRevenue = floatval($row['total']);
    } else {
        $partsRevenue = floatval($row['total']);
    }
}

$grandTotal = $serviceRevenue + $partsRevenue;

/* =========================
   PAYMENT METHODS
========================= */
$queryMethod = "
    SELECT payment_method, SUM(amount) AS total
    FROM payment p
    WHERE $dateFilter
    GROUP BY payment_method
";

$stmtMethod = $db->prepare($queryMethod);
$stmtMethod->execute($dateParams);

$methods = [];

while ($row = $stmtMethod->fetch(PDO::FETCH_ASSOC)) {
    $methods[$row['payment_method']] = floatval($row['total']);
}

/* =========================
   JOB STATUS SUMMARY
========================= */
$pendingJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Pending'
      AND $jobCreatedFilter
")->fetchColumn();

$completedJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Completed'
      AND $jobCompletedFilter
")->fetchColumn();

$cancelledJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Cancelled'
      AND $jobCreatedFilter
")->fetchColumn();

$jobsCompleted = $completedJobs;

/* =========================
   LOW STOCK
========================= */
$lowStock = $db->query("
    SELECT part_name, quantity_on_hand, low_stock_threshold
    FROM part
    WHERE is_active = 1
      AND quantity_on_hand <= low_stock_threshold
    ORDER BY quantity_on_hand ASC
")->fetchAll(PDO::FETCH_ASSOC);

function reportUrl($filterValue, $reportTypeValue, $startDate = '', $endDate = '') {
    $params = [
        'filter' => $filterValue,
        'report_type' => $reportTypeValue
    ];

    if ($filterValue === 'custom' && $startDate !== '' && $endDate !== '') {
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
    }

    return '?' . http_build_query($params);
}

$showSales = ($reportType === 'all' || $reportType === 'sales');
$showInventory = ($reportType === 'all' || $reportType === 'inventory');
$showJobs = ($reportType === 'all' || $reportType === 'jobs');

$pdfFilename = 'DASPMS-' . preg_replace("/[^A-Za-z0-9_-]/", "-", $reportTitle) . '.pdf';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports - DASPMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    .main-content {
        overflow-x: hidden;
    }

    .reports-page {
        width: 100%;
        max-width: 100%;
    }

    .reports-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.4rem;
    }

    .reports-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.22rem;
        line-height: 1.1;
    }

    .reports-subtitle {
        color: var(--dashboard-text-muted);
        font-size: 0.96rem;
        margin-bottom: 0;
    }

    .report-actions {
        display: flex;
        gap: 0.65rem;
        flex-wrap: wrap;
    }

    .report-main-btn,
    .report-outline-btn,
    .report-apply-btn {
        min-height: 44px;
        border-radius: 999px;
        font-size: 0.9rem;
        font-weight: 800;
        padding: 0.58rem 1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .report-main-btn {
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
    }

    .report-main-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .report-outline-btn {
        background: transparent;
        border: 1px solid rgba(15, 23, 42, 0.10);
        color: var(--dashboard-text-main);
    }

    .report-outline-btn:hover {
        background: rgba(255, 255, 255, 0.65);
        border-color: rgba(15, 23, 42, 0.14);
        color: var(--black);
    }

    .report-print-header {
        display: none;
    }

    .report-tabs {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .report-tab {
        text-decoration: none;
        background: transparent;
        border: 1px solid transparent;
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        padding: 0.52rem 0.95rem;
        font-size: 0.88rem;
        font-weight: 800;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .report-tab:hover {
        background: rgba(255, 255, 255, 0.50);
        border-color: rgba(15, 23, 42, 0.06);
        color: var(--dashboard-text-main);
    }

    .report-tab.active {
        background: rgba(255, 255, 255, 0.72);
        border-color: rgba(15, 23, 42, 0.08);
        color: var(--dashboard-text-main);
    }

    .report-filter-bar {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) minmax(170px, 210px) minmax(170px, 210px) auto;
        gap: 0.85rem;
        align-items: end;
        margin-bottom: 1.45rem;
        padding: 0.2rem 0 1.1rem 0;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    }

    .report-filter-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.75rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .report-filter-control {
        height: 44px;
        border-radius: 12px;
        border: 1px solid rgba(15, 23, 42, 0.10);
        font-size: 0.92rem;
        color: var(--dashboard-text-main);
        background: rgba(255, 255, 255, 0.60);
        box-shadow: none;
    }

    .report-filter-control:focus {
        border-color: rgba(245, 197, 24, 0.60);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
        background: rgba(255, 255, 255, 0.90);
    }

    .report-apply-btn {
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
    }

    .report-apply-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .report-alert {
        border-radius: 14px;
        font-size: 0.92rem;
        margin-bottom: 1.2rem;
    }

    .report-print-section {
        margin-bottom: 1.25rem;
    }

    .report-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.9rem;
        margin-bottom: 1.2rem;
    }

    .job-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.9rem;
        margin-bottom: 1.2rem;
    }

    .report-stat-card {
        min-height: 108px;
        background: rgba(255, 255, 255, 0.42);
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 16px;
        padding: 1.05rem 1.1rem;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        box-shadow: none;
        position: relative;
    }

    .report-stat-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 12px;
        bottom: 12px;
        width: 3px;
        border-radius: 999px;
        background: rgba(245, 197, 24, 0.85);
    }

    .report-stat-label {
        color: var(--dashboard-text-muted);
        font-size: 0.88rem;
        font-weight: 600;
        margin-bottom: 0.35rem;
    }

    .report-stat-value {
        color: var(--dashboard-text-main);
        font-size: 1.55rem;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 0;
    }

    .report-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 13px;
        background: rgba(245, 197, 24, 0.16);
        color: var(--black);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.12rem;
        flex-shrink: 0;
    }

    .report-panel-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
        gap: 1rem;
        margin-bottom: 1.2rem;
    }

    .report-panel {
        background: rgba(255, 255, 255, 0.34);
        border: 1px solid rgba(15, 23, 42, 0.05);
        border-radius: 16px;
        padding: 1.1rem 1.15rem;
        box-shadow: none;
    }

    .report-panel h5 {
        color: var(--dashboard-text-main);
        font-size: 1rem;
        font-weight: 900;
        margin-bottom: 1rem;
    }

    .chart-box {
        height: 300px;
        position: relative;
    }

    .chart-box-small {
        height: 260px;
        position: relative;
    }

    .report-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .report-table {
        width: 100%;
        min-width: 620px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .report-table thead th {
        color: var(--dashboard-text-muted);
        font-size: 0.8rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 0.82rem 0.75rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        background: transparent;
        white-space: nowrap;
    }

    .report-table tbody td {
        padding: 0.92rem 0.75rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        vertical-align: middle;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.94rem;
    }

    .report-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.03);
    }

    .stock-alert-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .stock-alert-out {
        background: rgba(239, 68, 68, 0.10);
        color: #b91c1c;
    }

    .stock-alert-low {
        background: rgba(245, 158, 11, 0.12);
        color: #92400e;
    }

    .report-empty-note {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        margin-bottom: 0;
    }

    .report-pdf-content {
        background: transparent;
    }

    /* =========================
       PDF EXPORT LAYOUT
       Used only on hidden cloned report content
    ========================= */
    .pdf-export-mode {
        background: #ffffff !important;
        color: #111827 !important;
        width: 760px !important;
        padding: 0 !important;
        font-family: Arial, sans-serif !important;
    }

    .pdf-export-mode .no-pdf,
    .pdf-export-mode .report-tabs,
    .pdf-export-mode .report-filter-bar,
    .pdf-export-mode .report-alert,
    .pdf-export-mode .report-actions {
        display: none !important;
    }

    .pdf-export-mode .reports-page {
        width: 100% !important;
        max-width: 100% !important;
        background: #ffffff !important;
    }

    .pdf-export-mode .report-print-header {
        display: block !important;
        margin-bottom: 14px !important;
        border-bottom: 2px solid #111827 !important;
        padding-bottom: 10px !important;
    }

    .pdf-export-mode .report-print-header h2 {
        font-size: 20px !important;
        font-weight: 900 !important;
        margin-bottom: 4px !important;
        color: #111827 !important;
    }

    .pdf-export-mode .report-print-header p {
        font-size: 11px !important;
        color: #4b5563 !important;
        margin-bottom: 2px !important;
    }

    .pdf-export-mode .reports-header {
        display: block !important;
        margin-bottom: 14px !important;
    }

    .pdf-export-mode .reports-header h2 {
        font-size: 24px !important;
        font-weight: 900 !important;
        margin-bottom: 4px !important;
        color: #111827 !important;
    }

    .pdf-export-mode .reports-subtitle {
        font-size: 12px !important;
        color: #4b5563 !important;
    }

    .pdf-export-mode .report-print-section {
        margin-bottom: 16px !important;
        break-inside: auto !important;
        page-break-inside: auto !important;
    }

    .pdf-export-mode .report-stat-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
        margin-bottom: 14px !important;
        break-inside: auto !important;
        page-break-inside: auto !important;
    }

    .pdf-export-mode .job-stat-grid {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 10px !important;
        margin-bottom: 14px !important;
        break-inside: auto !important;
        page-break-inside: auto !important;
    }

    .pdf-export-mode .report-stat-card {
        min-height: 86px !important;
        padding: 12px 14px !important;
        border: 1px solid #d9dee7 !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        box-shadow: none !important;
        display: block !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .pdf-export-mode .report-stat-card::before,
    .pdf-export-mode .report-stat-icon {
        display: none !important;
    }

    .pdf-export-mode .report-stat-label {
        color: #6b7280 !important;
        font-size: 10px !important;
        font-weight: 900 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.4px !important;
        margin-bottom: 5px !important;
    }

    .pdf-export-mode .report-stat-value {
        color: #111827 !important;
        font-size: 20px !important;
        font-weight: 900 !important;
        line-height: 1.1 !important;
        margin-bottom: 0 !important;
    }

    .pdf-export-mode .report-panel-grid {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 14px !important;
        margin-bottom: 14px !important;
    }

    .pdf-export-mode .report-panel {
        padding: 14px !important;
        border: 1px solid #d9dee7 !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        box-shadow: none !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        margin-bottom: 0 !important;
    }

    .pdf-export-mode .report-panel h5 {
        font-size: 15px !important;
        font-weight: 900 !important;
        margin-bottom: 10px !important;
        color: #111827 !important;
    }

    .pdf-export-mode .chart-box {
        height: 285px !important;
        max-height: 285px !important;
        position: relative !important;
    }

    .pdf-export-mode .chart-box-small {
        height: 265px !important;
        max-height: 265px !important;
        position: relative !important;
    }

    .pdf-export-mode canvas {
        max-height: 280px !important;
    }

    .pdf-export-mode .report-table-wrap {
        overflow: visible !important;
    }

    .pdf-export-mode .report-table {
        width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        font-size: 11px !important;
    }

    .pdf-export-mode .report-table thead th {
        background: #f3f4f6 !important;
        color: #111827 !important;
        border: 1px solid #d1d5db !important;
        padding: 8px !important;
        font-size: 10px !important;
        font-weight: 900 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.2px !important;
    }

    .pdf-export-mode .report-table tbody td {
        border: 1px solid #e5e7eb !important;
        padding: 8px !important;
        font-size: 10.5px !important;
        color: #111827 !important;
        background: #ffffff !important;
    }

    .pdf-export-mode .stock-alert-badge {
        border-radius: 999px !important;
        padding: 4px 8px !important;
        font-size: 9px !important;
        font-weight: 900 !important;
    }

    .pdf-export-mode .stock-alert-out {
        background: #fee2e2 !important;
        color: #991b1b !important;
    }

    .pdf-export-mode .stock-alert-low {
        background: #fef3c7 !important;
        color: #92400e !important;
    }

    @media (max-width: 1199.98px) {
        .report-stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .report-panel-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 991.98px) {
        .report-filter-bar {
            grid-template-columns: 1fr;
        }

        .report-apply-btn {
            width: 100%;
        }

        .job-stat-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .reports-header {
            flex-direction: column;
            align-items: stretch;
        }

        .report-actions {
            width: 100%;
        }

        .report-main-btn,
        .report-outline-btn {
            width: 100%;
        }

        .reports-header h2 {
            font-size: 1.75rem;
        }

        .report-stat-grid {
            grid-template-columns: 1fr;
        }
    }

    /* =========================
       PRINT LAYOUT
    ========================= */
    @media print {
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html,
        body {
            background: #ffffff !important;
            color: #111827 !important;
            font-size: 12px !important;
        }

        .sidebar,
        .report-tabs,
        .report-actions,
        .report-filter-bar,
        .report-alert,
        .btn,
        script {
            display: none !important;
        }

        .app-wrapper {
            display: block !important;
            background: #ffffff !important;
        }

        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            width: 100% !important;
        }

        .report-pdf-content {
            background: #ffffff !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .reports-page {
            width: 100% !important;
            max-width: 100% !important;
        }

        .report-print-header {
            display: block !important;
            margin-bottom: 14px;
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
        }

        .report-print-header h2 {
            font-size: 18px !important;
            font-weight: 900 !important;
            margin-bottom: 4px !important;
        }

        .report-print-header p {
            font-size: 10px !important;
            margin-bottom: 2px !important;
            color: #4b5563 !important;
        }

        .reports-header {
            display: block !important;
            margin-bottom: 12px !important;
        }

        .reports-header h2 {
            font-size: 24px !important;
            font-weight: 900 !important;
            margin-bottom: 3px !important;
        }

        .reports-subtitle {
            font-size: 11px !important;
            color: #4b5563 !important;
        }

        .report-print-section {
            margin-bottom: 16px !important;
            page-break-inside: auto !important;
            break-inside: auto !important;
        }

        .report-stat-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
            margin-bottom: 12px !important;
            page-break-inside: auto !important;
            break-inside: auto !important;
        }

        .job-stat-grid {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 10px !important;
            margin-bottom: 12px !important;
            page-break-inside: auto !important;
            break-inside: auto !important;
        }

        .report-stat-card {
            min-height: 88px !important;
            padding: 11px 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            box-shadow: none !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            display: flex !important;
            justify-content: flex-start !important;
        }

        .report-stat-card::before,
        .report-stat-icon {
            display: none !important;
        }

        .report-stat-label {
            color: #6b7280 !important;
            font-size: 9px !important;
            font-weight: 800 !important;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px !important;
        }

        .report-stat-value {
            color: #111827 !important;
            font-size: 20px !important;
            font-weight: 900 !important;
            line-height: 1.1 !important;
            margin-bottom: 0 !important;
        }

        .report-panel-grid {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 12px !important;
            margin-bottom: 12px !important;
        }

        .report-panel {
            padding: 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            box-shadow: none !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            margin-bottom: 0 !important;
        }

        .report-panel h5 {
            font-size: 13px !important;
            font-weight: 900 !important;
            margin-bottom: 8px !important;
            color: #111827 !important;
        }

        .chart-box {
            height: 260px !important;
            max-height: 260px !important;
            position: relative !important;
        }

        .chart-box-small {
            height: 250px !important;
            max-height: 250px !important;
            position: relative !important;
        }

        canvas {
            max-height: 250px !important;
        }

        .report-table-wrap {
            overflow: visible !important;
        }

        .report-table {
            width: 100% !important;
            min-width: 0 !important;
            border-collapse: collapse !important;
            font-size: 10px !important;
        }

        .report-table thead th {
            background: #f3f4f6 !important;
            color: #111827 !important;
            border: 1px solid #d1d5db !important;
            padding: 7px !important;
            font-size: 9px !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.2px !important;
        }

        .report-table tbody td {
            border: 1px solid #e5e7eb !important;
            padding: 7px !important;
            font-size: 9.5px !important;
            color: #111827 !important;
            background: #ffffff !important;
        }

        .stock-alert-badge {
            border-radius: 999px !important;
            padding: 4px 8px !important;
            font-size: 9px !important;
            font-weight: 900 !important;
        }

        .stock-alert-out {
            background: #fee2e2 !important;
            color: #991b1b !important;
        }

        .stock-alert-low {
            background: #fef3c7 !important;
            color: #92400e !important;
        }

        .report-empty-note {
            font-size: 10px !important;
            color: #4b5563 !important;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
<div id="reportContent" class="report-pdf-content">
    <div class="reports-page">

        <div class="report-print-header">
            <h2 class="fw-bold mb-1">Norily's Vehicle Repair Shop</h2>
            <p class="mb-1">Digital Auto Service and Parts Management System</p>
            <p class="mb-0">
                <?php echo htmlspecialchars($reportTitle); ?> —
                Generated on <?php echo date('F d, Y h:i A'); ?>
            </p>
        </div>

        <div class="reports-header">
            <div>
                <h2>Reports</h2>
                <p class="reports-subtitle">
                    Revenue, payment, job status, and inventory summaries
                </p>
            </div>

            <div class="report-actions no-pdf">
                <button type="button" id="downloadPdfBtn" class="btn report-main-btn">
                    <i class="bi bi-download"></i>
                    Download PDF
                </button>

                <button type="button" onclick="window.print()" class="btn report-outline-btn">
                    <i class="bi bi-printer"></i>
                    Print PDF
                </button>
            </div>
        </div>

        <?php if ($customDateError !== ''): ?>
            <div class="alert alert-warning alert-dismissible fade show report-alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($customDateError); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="report-tabs">
            <a href="<?php echo reportUrl('today', $reportType); ?>" class="report-tab <?php echo ($filter == 'today') ? 'active' : ''; ?>">
                Today
            </a>

            <a href="<?php echo reportUrl('week', $reportType); ?>" class="report-tab <?php echo ($filter == 'week') ? 'active' : ''; ?>">
                Week
            </a>

            <a href="<?php echo reportUrl('month', $reportType); ?>" class="report-tab <?php echo ($filter == 'month') ? 'active' : ''; ?>">
                Month
            </a>

            <a href="<?php echo reportUrl('custom', $reportType, $startDate, $endDate); ?>" class="report-tab <?php echo ($filter == 'custom') ? 'active' : ''; ?>">
                Custom
            </a>
        </div>

        <form method="GET" class="report-filter-bar">
            <input type="hidden" name="filter" value="custom">

            <div>
                <label class="report-filter-label">Report Type</label>
                <select name="report_type" class="form-select report-filter-control">
                    <option value="all" <?php echo $reportType === 'all' ? 'selected' : ''; ?>>All Reports</option>
                    <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>>Sales / Revenue Report</option>
                    <option value="inventory" <?php echo $reportType === 'inventory' ? 'selected' : ''; ?>>Inventory Report</option>
                    <option value="jobs" <?php echo $reportType === 'jobs' ? 'selected' : ''; ?>>Job Status Report</option>
                </select>
            </div>

            <div>
                <label class="report-filter-label">Start Date</label>
                <input
                    type="date"
                    name="start_date"
                    class="form-control report-filter-control"
                    value="<?php echo htmlspecialchars($startDate); ?>"
                >
            </div>

            <div>
                <label class="report-filter-label">End Date</label>
                <input
                    type="date"
                    name="end_date"
                    class="form-control report-filter-control"
                    value="<?php echo htmlspecialchars($endDate); ?>"
                >
            </div>

            <button type="submit" class="btn report-apply-btn">
                Apply Report
            </button>
        </form>

        <?php if ($showSales): ?>
            <section class="report-print-section">
                <div class="report-stat-grid">
                    <div class="report-stat-card">
                        <div>
                            <div class="report-stat-label">Total Revenue</div>
                            <h3 class="report-stat-value">₱<?php echo number_format($grandTotal, 2); ?></h3>
                        </div>
                        <div class="report-stat-icon">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>

                    <div class="report-stat-card">
                        <div>
                            <div class="report-stat-label">Service Revenue</div>
                            <h3 class="report-stat-value">₱<?php echo number_format($serviceRevenue, 2); ?></h3>
                        </div>
                        <div class="report-stat-icon">
                            <i class="bi bi-wrench-adjustable"></i>
                        </div>
                    </div>

                    <div class="report-stat-card">
                        <div>
                            <div class="report-stat-label">Parts Revenue</div>
                            <h3 class="report-stat-value">₱<?php echo number_format($partsRevenue, 2); ?></h3>
                        </div>
                        <div class="report-stat-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>

                    <div class="report-stat-card">
                        <div>
                            <div class="report-stat-label">Jobs Completed</div>
                            <h3 class="report-stat-value"><?php echo intval($jobsCompleted); ?></h3>
                        </div>
                        <div class="report-stat-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                </div>

                <div class="report-panel-grid">
                    <div class="report-panel">
                        <h5>Revenue Breakdown</h5>
                        <div class="chart-box">
                            <canvas id="barChart"></canvas>
                        </div>
                    </div>

                    <div class="report-panel">
                        <h5>Payment Methods</h5>
                        <div class="chart-box-small">
                            <canvas id="pieChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($showJobs): ?>
            <section class="report-print-section">
                <div class="job-stat-grid">
                    <div class="report-stat-card">
                        <div>
                            <div class="report-stat-label">Pending Jobs</div>
                            <h3 class="report-stat-value"><?php echo intval($pendingJobs); ?></h3>
                        </div>
                        <div class="report-stat-icon">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>

                    <div class="report-stat-card">
                        <div>
                            <div class="report-stat-label">Completed Jobs</div>
                            <h3 class="report-stat-value"><?php echo intval($completedJobs); ?></h3>
                        </div>
                        <div class="report-stat-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>

                    <div class="report-stat-card">
                        <div>
                            <div class="report-stat-label">Cancelled Jobs</div>
                            <h3 class="report-stat-value"><?php echo intval($cancelledJobs); ?></h3>
                        </div>
                        <div class="report-stat-icon">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($showInventory): ?>
            <section class="report-print-section">
                <div class="report-panel">
                    <h5>Low Stock Items</h5>

                    <?php if (count($lowStock) > 0): ?>
                        <div class="report-table-wrap">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Part</th>
                                        <th>Current Stock</th>
                                        <th>Low Stock Threshold</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStock as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                            <td>
                                                <?php if (intval($item['quantity_on_hand']) <= 0): ?>
                                                    <span class="stock-alert-badge stock-alert-out">
                                                        Out of Stock
                                                    </span>
                                                <?php else: ?>
                                                    <span class="stock-alert-badge stock-alert-low">
                                                        <?php echo intval($item['quantity_on_hand']); ?> Low
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo intval($item['low_stock_threshold']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="report-empty-note">No low stock items.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

    </div>
</div>
</main>
</div>

<?php if ($showSales): ?>
<script>
const serviceRevenue = <?php echo json_encode($serviceRevenue); ?>;
const partsRevenue = <?php echo json_encode($partsRevenue); ?>;
const paymentLabels = <?php echo json_encode(array_keys($methods)); ?>;
const paymentValues = <?php echo json_encode(array_values($methods)); ?>;

const barCanvas = document.getElementById('barChart');
if (barCanvas) {
    new Chart(barCanvas, {
        type: 'bar',
        data: {
            labels: ['Service', 'Parts'],
            datasets: [{
                label: 'Revenue',
                data: [serviceRevenue, partsRevenue],
                backgroundColor: ['#1e40af', '#10b981'],
                borderRadius: 5
            }]
        },
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value;
                        }
                    }
                }
            }
        }
    });
}

const pieCanvas = document.getElementById('pieChart');
if (pieCanvas) {
    new Chart(pieCanvas, {
        type: 'doughnut',
        data: {
            labels: paymentLabels.length ? paymentLabels : ['No Payments'],
            datasets: [{
                data: paymentValues.length ? paymentValues : [1],
                backgroundColor: ['#1e40af', '#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 3,
                borderColor: '#ffffff'
            }]
        },
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 14,
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
}
</script>
<?php endif; ?>

<script>
const downloadPdfBtn = document.getElementById('downloadPdfBtn');

if (downloadPdfBtn) {
    downloadPdfBtn.addEventListener('click', function () {
        const reportElement = document.getElementById('reportContent');

        if (!reportElement) {
            alert('Report content not found.');
            return;
        }

        const clonedReport = reportElement.cloneNode(true);
        clonedReport.classList.add('pdf-export-mode');

        const hiddenContainer = document.createElement('div');
        hiddenContainer.style.position = 'fixed';
        hiddenContainer.style.left = '-9999px';
        hiddenContainer.style.top = '0';
        hiddenContainer.style.width = '760px';
        hiddenContainer.style.background = '#ffffff';
        hiddenContainer.style.zIndex = '-1';
        hiddenContainer.appendChild(clonedReport);

        document.body.appendChild(hiddenContainer);

        const clonedCanvases = clonedReport.querySelectorAll('canvas');
        const originalCanvases = reportElement.querySelectorAll('canvas');

        clonedCanvases.forEach(function (clonedCanvas, index) {
            const originalCanvas = originalCanvases[index];

            if (originalCanvas) {
                const image = document.createElement('img');
                image.src = originalCanvas.toDataURL('image/png', 1.0);
                image.style.width = '100%';
                image.style.maxHeight = index === 0 ? '285px' : '265px';
                image.style.objectFit = 'contain';
                image.style.display = 'block';

                clonedCanvas.parentNode.replaceChild(image, clonedCanvas);
            }
        });

        const options = {
            margin: [0.45, 0.45, 0.45, 0.45],
            filename: <?php echo json_encode($pdfFilename); ?>,
            image: {
                type: 'jpeg',
                quality: 0.98
            },
            html2canvas: {
                scale: 2,
                useCORS: true,
                scrollY: 0,
                backgroundColor: '#ffffff'
            },
            jsPDF: {
                unit: 'in',
                format: 'a4',
                orientation: 'portrait'
            },
            pagebreak: {
                mode: ['css', 'legacy'],
                avoid: ['.report-stat-card', '.report-panel']
            }
        };

        html2pdf()
            .set(options)
            .from(clonedReport)
            .save()
            .then(function () {
                if (document.body.contains(hiddenContainer)) {
                    document.body.removeChild(hiddenContainer);
                }
            })
            .catch(function () {
                if (document.body.contains(hiddenContainer)) {
                    document.body.removeChild(hiddenContainer);
                }

                alert('Unable to generate PDF. Please try again.');
            });
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>