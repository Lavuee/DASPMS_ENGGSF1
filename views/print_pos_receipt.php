<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
$db = (new Database())->getConnection();

$pos_id = intval($_GET['pos_id'] ?? 0);

$stmt = $db->prepare("
    SELECT 
        pt.*,
        c.first_name,
        c.last_name,
        c.contact_number,
        u.first_name AS staff_first_name,
        u.last_name AS staff_last_name
    FROM pos_transaction pt
    LEFT JOIN customer c ON pt.customer_id = c.customer_id
    LEFT JOIN user u ON pt.processed_by = u.user_id
    WHERE pt.pos_id = ?
    LIMIT 1
");
$stmt->execute([$pos_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die("POS receipt not found.");
}

$stmtItems = $db->prepare("
    SELECT 
        pi.quantity_sold,
        pi.unit_price_at_sale,
        pi.subtotal,
        p.part_name
    FROM pos_item pi
    JOIN part p ON pi.part_id = p.part_id
    WHERE pi.pos_id = ?
");
$stmtItems->execute([$pos_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$customerName = $sale['customer_id']
    ? $sale['first_name'] . ' ' . $sale['last_name']
    : 'Walk-in Customer';

$staffName = trim(($sale['staff_first_name'] ?? '') . ' ' . ($sale['staff_last_name'] ?? ''));
if ($staffName === '') {
    $staffName = 'Authorized Staff';
}

$receiptNumber = 'POS-' . str_pad($sale['pos_id'], 5, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?php echo htmlspecialchars($receiptNumber); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
body {
    background: #f4f6f9;
    font-family: Arial, sans-serif;
    color: #111827;
}

.receipt-page {
    max-width: 980px;
    margin: 0 auto;
    padding: 24px 16px 40px;
}

.receipt-action-bar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(244, 246, 249, 0.92);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(17, 24, 39, 0.08);
    margin: 0 -16px 24px;
    padding: 12px 16px;
}

.receipt-action-inner {
    max-width: 850px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.receipt-action-group {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
}

.receipt-action-btn {
    min-height: 38px;
    border-radius: 10px;
    padding: 0.45rem 0.85rem;
    font-weight: 700;
    font-size: 0.86rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.38rem;
    line-height: 1;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.back-pos-btn {
    background: rgba(255, 255, 255, 0.65);
    border: 1px solid rgba(17, 24, 39, 0.18);
    color: #111827;
}

.back-pos-btn:hover {
    background: #ffffff;
    border-color: #111827;
    color: #111827;
}

.download-receipt-btn {
    background: #f5c518;
    border: 1px solid #f5c518;
    color: #111827;
}

.download-receipt-btn:hover {
    background: #111827;
    border-color: #111827;
    color: #ffffff;
}

.print-receipt-btn {
    background: #ffffff;
    border: 1px solid #111827;
    color: #111827;
}

.print-receipt-btn:hover {
    background: #111827;
    border-color: #111827;
    color: #ffffff;
}

/* =========================
   RECEIPT DOCUMENT
========================= */

.receipt-wrapper {
    max-width: 850px;
    margin: 0 auto;
    background: #ffffff;
    padding: 44px 46px;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    position: relative;
    overflow: hidden;
}

.receipt-top-accent {
    position: absolute;
    top: 0;
    left: 46px;
    width: 58px;
    height: 92px;
    background: #111827;
    display: flex;
    align-items: center;
    justify-content: center;
}

.receipt-top-accent span {
    color: #f5c518;
    font-weight: 900;
    font-size: 1.35rem;
    letter-spacing: 1px;
}

.receipt-header {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2rem;
    align-items: start;
    margin-bottom: 2.3rem;
    padding-left: 82px;
}

.shop-title {
    font-size: 1.25rem;
    font-weight: 900;
    margin-bottom: 0.25rem;
    color: #111827;
}

.shop-subtitle,
.shop-address {
    font-size: 0.86rem;
    color: #4b5563;
    line-height: 1.45;
}

.receipt-title {
    font-size: 2rem;
    font-weight: 900;
    letter-spacing: 0.18rem;
    text-align: right;
    color: #374151;
    margin-bottom: 1rem;
    white-space: nowrap;
}

.receipt-meta-table {
    min-width: 250px;
}

.receipt-meta-row {
    display: grid;
    grid-template-columns: 100px 1fr;
    gap: 0.75rem;
    font-size: 0.86rem;
    margin-bottom: 0.4rem;
}

.receipt-meta-label {
    color: #6b7280;
    font-weight: 700;
}

.receipt-meta-value {
    color: #111827;
    font-weight: 700;
    text-align: right;
}

.receipt-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2.5rem;
    margin-bottom: 2.2rem;
}

.receipt-info-title {
    font-size: 0.78rem;
    font-weight: 900;
    color: #111827;
    text-transform: uppercase;
    letter-spacing: 0.05rem;
    margin-bottom: 0.6rem;
}

.customer-name {
    font-size: 1.2rem;
    font-weight: 900;
    color: #111827;
    margin-bottom: 0.2rem;
}

.info-line {
    color: #4b5563;
    font-size: 0.88rem;
    line-height: 1.55;
    margin-bottom: 0.25rem;
}

.payment-info {
    text-align: right;
}

.receipt-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.35rem;
}

.receipt-items-table thead th {
    background: #3f3f46;
    color: #ffffff;
    padding: 0.85rem 0.9rem;
    font-size: 0.78rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04rem;
}

.receipt-items-table tbody td {
    padding: 0.95rem 0.9rem;
    border-bottom: 1px solid #e5e7eb;
    color: #111827;
    font-size: 0.9rem;
}

.receipt-items-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

.receipt-items-table .item-no {
    width: 54px;
    text-align: center;
    color: #6b7280;
}

.receipt-items-table .item-part {
    font-weight: 700;
}

.receipt-items-table .item-qty {
    text-align: center;
    white-space: nowrap;
}

.receipt-items-table .item-price,
.receipt-items-table .item-subtotal {
    text-align: right;
    white-space: nowrap;
}

.receipt-bottom-grid {
    display: grid;
    grid-template-columns: 0.9fr 1.1fr;
    gap: 2.2rem;
    align-items: start;
    margin-top: 1.4rem;
}

.receipt-note-box {
    padding-top: 0.4rem;
}

.receipt-note-label {
    font-size: 0.78rem;
    color: #6b7280;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04rem;
    margin-bottom: 0.65rem;
}

.receipt-note-heading {
    font-size: 1.05rem;
    font-weight: 900;
    color: #111827;
    padding-bottom: 0.7rem;
    border-bottom: 2px solid #111827;
    display: inline-block;
    min-width: 170px;
    margin-bottom: 0.75rem;
}

.receipt-note-text {
    color: #6b7280;
    font-size: 0.8rem;
    line-height: 1.6;
    max-width: 280px;
    margin-bottom: 0;
}

.receipt-total-table {
    width: 100%;
    border-collapse: collapse;
}

.receipt-total-table td {
    padding: 0.62rem 0.75rem;
    border-bottom: 1px solid #e5e7eb;
    font-size: 0.9rem;
}

.receipt-total-table .label-cell {
    color: #374151;
    text-align: right;
    font-weight: 700;
}

.receipt-total-table .amount-cell {
    color: #111827;
    text-align: right;
    font-weight: 800;
    white-space: nowrap;
}

.receipt-total-table .grand-total-row td {
    background: #3f3f46;
    color: #ffffff;
    border-bottom: none;
    font-size: 1rem;
    font-weight: 900;
    padding: 0.85rem 0.75rem;
}

.receipt-footer-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 2rem;
    margin-top: 2.4rem;
    align-items: end;
}

.terms-box h6 {
    font-size: 0.86rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04rem;
    margin-bottom: 0.65rem;
}

.terms-box p {
    color: #6b7280;
    font-size: 0.8rem;
    line-height: 1.55;
    margin-bottom: 0;
}

.signature-box {
    text-align: center;
}

.signature-line {
    height: 56px;
    border-bottom: 1px solid #111827;
    margin-bottom: 0.55rem;
}

.signature-name {
    font-size: 0.85rem;
    font-weight: 900;
    color: #111827;
    margin-bottom: 0.15rem;
}

.signature-role {
    font-size: 0.78rem;
    color: #6b7280;
}

.receipt-contact-strip {
    margin: 2.2rem -46px -44px;
    padding: 1rem 46px;
    border-top: 1px solid #e5e7eb;
    background: #fafafa;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    color: #4b5563;
    font-size: 0.76rem;
    line-height: 1.35;
}

.contact-icon {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: #111827;
    color: #f5c518;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

@media (max-width: 767.98px) {
    .receipt-action-inner {
        flex-direction: column;
        align-items: stretch;
    }

    .receipt-action-group {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .receipt-action-btn {
        width: 100%;
    }

    .receipt-wrapper {
        padding: 30px 24px;
    }

    .receipt-top-accent {
        display: none;
    }

    .receipt-header {
        grid-template-columns: 1fr;
        padding-left: 0;
    }

    .receipt-title,
    .receipt-meta-value,
    .payment-info {
        text-align: left;
    }

    .receipt-info-grid,
    .receipt-bottom-grid,
    .receipt-footer-grid,
    .receipt-contact-strip {
        grid-template-columns: 1fr;
    }

    .receipt-contact-strip {
        margin: 2rem -24px -30px;
        padding: 1rem 24px;
    }
}

@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    body {
        background: #ffffff !important;
        color: #111827 !important;
    }

    .no-print {
        display: none !important;
    }

    .receipt-page {
        max-width: 100%;
        margin: 0;
        padding: 0;
    }

    .receipt-wrapper {
        box-shadow: none;
        margin: 0;
        max-width: 100%;
        border-radius: 0;
        padding: 28px 30px;
        overflow: visible;
    }

    .receipt-top-accent {
        top: 0;
        left: 30px;
    }

    .receipt-contact-strip {
        margin-left: -30px;
        margin-right: -30px;
        margin-bottom: -28px;
        padding-left: 30px;
        padding-right: 30px;
    }

    .receipt-items-table thead th,
    .receipt-total-table .grand-total-row td,
    .contact-icon,
    .receipt-top-accent {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
</head>

<body>

<div class="receipt-page">

    <!-- ACTION BAR -->
    <div class="receipt-action-bar no-print">
        <div class="receipt-action-inner">
            <a href="pos.php" class="btn receipt-action-btn back-pos-btn">
                <i class="bi bi-arrow-left"></i>
                Back to POS
            </a>

            <div class="receipt-action-group">
                <button onclick="downloadReceiptPDF()" class="btn receipt-action-btn download-receipt-btn">
                    <i class="bi bi-download"></i>
                    Download PDF
                </button>

                <button onclick="window.print()" class="btn receipt-action-btn print-receipt-btn">
                    <i class="bi bi-printer"></i>
                    Print Receipt
                </button>
            </div>
        </div>
    </div>

    <!-- RECEIPT CONTENT -->
    <div class="receipt-wrapper" id="receiptArea">

        <div class="receipt-top-accent">
            <span>NV</span>
        </div>

        <div class="receipt-header">
            <div>
                <div class="shop-title">Norily's Vehicle Repair Shop</div>
                <div class="shop-address">Vila Rosario, La Union</div>
                <div class="shop-subtitle">Digital Auto Service and Parts Management System</div>
            </div>

            <div>
                <div class="receipt-title">POS RECEIPT</div>

                <div class="receipt-meta-table">
                    <div class="receipt-meta-row">
                        <div class="receipt-meta-label">Receipt No</div>
                        <div class="receipt-meta-value">
                            <?php echo htmlspecialchars($receiptNumber); ?>
                        </div>
                    </div>

                    <div class="receipt-meta-row">
                        <div class="receipt-meta-label">Receipt Date</div>
                        <div class="receipt-meta-value">
                            <?php echo date('M d, Y', strtotime($sale['transaction_date'])); ?>
                        </div>
                    </div>

                    <div class="receipt-meta-row">
                        <div class="receipt-meta-label">Sale Type</div>
                        <div class="receipt-meta-value">POS Sale</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="receipt-info-grid">
            <div>
                <div class="receipt-info-title">Customer</div>

                <div class="customer-name">
                    <?php echo htmlspecialchars($customerName); ?>
                </div>

                <div class="info-line">
                    <?php echo $sale['customer_id'] ? 'Registered Customer' : 'Walk-in Transaction'; ?>
                </div>

                <div class="info-line mt-3">
                    <strong>Contact:</strong> <?php echo htmlspecialchars($sale['contact_number'] ?? 'N/A'); ?>
                </div>
            </div>

            <div class="payment-info">
                <div class="receipt-info-title">Payment Details</div>

                <div class="info-line">
                    <strong>Method:</strong> <?php echo htmlspecialchars($sale['payment_method']); ?>
                </div>

                <div class="info-line">
                    <strong>Reference:</strong> <?php echo htmlspecialchars($sale['reference_number'] ?: 'N/A'); ?>
                </div>

                <div class="info-line">
                    <strong>Status:</strong> <?php echo htmlspecialchars($sale['status']); ?>
                </div>
            </div>
        </div>

        <table class="receipt-items-table">
            <thead>
                <tr>
                    <th class="item-no">#</th>
                    <th>Part</th>
                    <th class="item-price">Unit Price</th>
                    <th class="item-qty">Qty</th>
                    <th class="item-subtotal">Subtotal</th>
                </tr>
            </thead>

            <tbody>
                <?php $itemCounter = 1; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="item-no">
                            <?php echo str_pad($itemCounter, 2, '0', STR_PAD_LEFT); ?>
                        </td>

                        <td class="item-part">
                            <?php echo htmlspecialchars($item['part_name']); ?>
                        </td>

                        <td class="item-price">
                            ₱<?php echo number_format(floatval($item['unit_price_at_sale']), 2); ?>
                        </td>

                        <td class="item-qty">
                            <?php echo intval($item['quantity_sold']); ?>
                        </td>

                        <td class="item-subtotal">
                            ₱<?php echo number_format(floatval($item['subtotal']), 2); ?>
                        </td>
                    </tr>
                    <?php $itemCounter++; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="receipt-bottom-grid">
            <div class="receipt-note-box">
                <div class="receipt-note-label">POS Note</div>
                <div class="receipt-note-heading">Over-the-Counter Sale</div>
                <p class="receipt-note-text">
                    This POS receipt is generated by DASPMS as proof of over-the-counter parts sale.
                    Please keep this receipt for shop transaction reference.
                </p>
            </div>

            <table class="receipt-total-table">
                <tr>
                    <td class="label-cell">Items Sold</td>
                    <td class="amount-cell">
                        <?php echo count($items); ?>
                    </td>
                </tr>

                <tr>
                    <td class="label-cell">Payment Method</td>
                    <td class="amount-cell">
                        <?php echo htmlspecialchars($sale['payment_method']); ?>
                    </td>
                </tr>

                <tr class="grand-total-row">
                    <td class="label-cell">TOTAL</td>
                    <td class="amount-cell">
                        ₱<?php echo number_format(floatval($sale['total_amount']), 2); ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="receipt-footer-grid">
            <div class="terms-box">
                <h6>Receipt Note</h6>
                <p>
                    This receipt confirms that the listed parts were sold through the DASPMS POS module.
                    Any concerns should be verified with the shop using the receipt reference number.
                </p>
            </div>

            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-name"><?php echo htmlspecialchars($staffName); ?></div>
                <div class="signature-role">Processed / Verified By</div>
            </div>
        </div>

        <div class="receipt-contact-strip">
            <div class="contact-item">
                <span class="contact-icon">
                    <i class="bi bi-person-fill"></i>
                </span>
                <span>
                    <?php echo htmlspecialchars($customerName); ?><br>
                    Customer
                </span>
            </div>

            <div class="contact-item">
                <span class="contact-icon">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <span>
                    Vila Rosario<br>
                    La Union
                </span>
            </div>

            <div class="contact-item">
                <span class="contact-icon">
                    <i class="bi bi-receipt"></i>
                </span>
                <span>
                    <?php echo htmlspecialchars($receiptNumber); ?><br>
                    POS Reference
                </span>
            </div>
        </div>

    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
function downloadReceiptPDF() {
    const element = document.getElementById('receiptArea');

    const opt = {
        margin: 10,
        filename: '<?php echo htmlspecialchars($receiptNumber); ?>.pdf',
        image: { type: 'jpeg', quality: 1 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>