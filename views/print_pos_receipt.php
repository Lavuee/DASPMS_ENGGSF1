<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('pos.use');

require_once '../config/Database.php';
require_once '../includes/receipt_ui.php';

$db = (new Database())->getConnection();

$pos_id = intval($_GET['pos_id'] ?? 0);

$stmt = $db->prepare("
    SELECT 
        pt.*,
        c.first_name,
        c.last_name,
        c.contact_number,
        c.address AS customer_address,
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
    require_once __DIR__ . '/../includes/error_page.php';
    renderErrorPage(404, 'Sales receipt', 'Receipt not found',
        'This POS receipt does not exist or the link is incomplete. Open it again from the POS recent sales.',
        [['label' => 'Go to POS Terminal', 'href' => 'views/pos.php', 'primary' => true], ['label' => '← Go back', 'href' => 'javascript:history.back()']]);
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

$customerType = trim($sale['customer_type'] ?? '');

if ($customerType === '') {
    $customerType = !empty($sale['customer_id']) ? 'Registered' : 'Walk-in';
}

if (!empty($sale['customer_id'])) {
    $customerName = trim(($sale['first_name'] ?? '') . ' ' . ($sale['last_name'] ?? ''));

    if ($customerName === '') {
        $customerName = 'Registered Customer';
    }

    $customerContact = trim($sale['contact_number'] ?? '');
    $customerAddress = trim($sale['customer_address'] ?? '');
    $customerLabel = 'Registered Customer';
} else {
    $customerName = trim($sale['walkin_customer_name'] ?? '');

    if ($customerName === '') {
        $customerName = 'Walk-in Customer';
    }

    $customerContact = trim($sale['walkin_contact_number'] ?? '');
    $customerAddress = trim($sale['walkin_address'] ?? '');
    $customerLabel = 'Walk-in Guest / One-time Buyer';
}

if ($customerContact === '') {
    $customerContact = 'N/A';
}

if ($customerAddress === '') {
    $customerAddress = 'N/A';
}

$staffName = trim(($sale['staff_first_name'] ?? '') . ' ' . ($sale['staff_last_name'] ?? ''));

if ($staffName === '') {
    $staffName = 'Authorized Staff';
}

$receiptNumber = 'POS-' . str_pad($sale['pos_id'], 5, '0', STR_PAD_LEFT);

$isEmbed = !empty($_GET['embed']);

$statusTone = ['Completed' => 'gray', 'Pending' => 'amber', 'Cancelled' => 'red'][$sale['status']] ?? 'gray';
$stamp = ['Completed' => ['PAID', 'green'], 'Pending' => ['PENDING', 'amber'], 'Cancelled' => ['CANCELLED', 'red']][$sale['status']] ?? ['PAID', 'green'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?php echo htmlspecialchars($receiptNumber); ?></title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/receipt.css?v=<?= filemtime(__DIR__ . '/../assets/css/receipt.css') ?>">
</head>
<body class="rc-body<?= $isEmbed ? ' is-embed' : '' ?>">

<div class="rc-bar no-print">
    <div class="rc-bar-inner">
        <a href="pos.php" class="rc-btn">← Back to POS</a>
        <div class="rc-bar-actions">
            <button type="button" class="rc-btn rc-btn-solid" onclick="downloadReceiptPDF()">Download PDF</button>
            <button type="button" class="rc-btn" onclick="window.print()">Print</button>
        </div>
    </div>
</div>

<div class="rc-wrap">
    <div id="receiptArea">
        <article class="rc-receipt">
            <?php rcHeader('Sales receipt', '<p class="rc-number">' . rcEscape($receiptNumber) . '</p>'); ?>

            <hr class="rc-sep">
            <dl class="rc-details rc-section">
                <dt>Date</dt><dd><?= date('M d, Y', strtotime($sale['transaction_date'])) ?></dd>
                <dt>Customer type</dt><dd><?= rcEscape($customerType) ?></dd>
                <dt>Payment method</dt><dd><?= rcEscape($sale['payment_method']) ?></dd>
                <dt>Reference</dt><dd class="rc-mono"><?= rcEscape($sale['reference_number'] ?: 'N/A') ?></dd>
                <dt>Status</dt><dd><?= rcBadge($sale['status'], $statusTone) ?></dd>
                <?php if (!empty($sale['void_reason'])): ?>
                    <dt>Void reason</dt><dd><?= rcEscape($sale['void_reason']) ?></dd>
                <?php endif; ?>
            </dl>

            <hr class="rc-sep">
            <div class="rc-parties rc-section">
                <div>
                    <p class="rc-label">Sold to</p>
                    <span class="rc-party-name"><?= rcEscape($customerName) ?></span>
                    <span class="rc-muted"><?= rcEscape($customerLabel) ?></span>
                    <span class="rc-muted rc-mono"><?= rcEscape($customerContact) ?></span>
                    <span class="rc-muted"><?= rcEscape($customerAddress) ?></span>
                </div>
                <div>
                    <p class="rc-label">Processed by</p>
                    <span class="rc-party-name"><?= rcEscape($staffName) ?></span>
                </div>
            </div>

            <hr class="rc-sep">
            <div class="rc-section">
                <p class="rc-label">Items · <?= count($items) ?></p>
                <?php foreach ($items as $item): ?>
                    <div class="rc-item">
                        <div class="rc-item-main">
                            <?= rcEscape($item['part_name']) ?>
                            <span class="rc-item-sub"><?= intval($item['quantity_sold']) ?> × <?= rcMoney($item['unit_price_at_sale']) ?></span>
                        </div>
                        <span class="rc-money"><?= rcMoney($item['subtotal']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <hr class="rc-sep">
            <div class="rc-totals">
                <div class="rc-row"><span>Items sold</span><span class="rc-money"><?= count($items) ?></span></div>
                <div class="rc-row rc-total"><span>Total</span><span class="rc-money"><?= rcMoney($sale['total_amount']) ?></span></div>
                <span class="rc-stamp is-<?= $stamp[1] ?>" aria-hidden="true"><?= $stamp[0] ?></span>
            </div>
            <div class="rc-inkbox"><span>Amount paid · <?= rcEscape($sale['payment_method']) ?></span><strong><?= rcMoney($sale['total_amount']) ?></strong></div>

            <hr class="rc-sep">
            <div class="rc-section">
                <p class="rc-label">POS note</p>
                <p class="rc-note">This POS receipt is generated by DASPMS as proof of over-the-counter parts sale. Walk-in guest details are saved only for this POS transaction and are not automatically added to the Customers list.</p>
                <p class="rc-note"><strong>Receipt note.</strong> This receipt confirms that the listed parts were sold through the DASPMS POS module. Any concerns should be verified with the shop using the receipt reference number.</p>
            </div>

            <div class="rc-sign"><strong><?= rcEscape($staffName) ?></strong><span>Processed / Verified by</span></div>

            <footer class="rc-foot">
                <p class="rc-thanks">Salamat po!</p>
                <p class="rc-foot-code"><?= rcEscape($receiptNumber) ?> · Printed <?= date('M d, Y') ?></p>
            </footer>
        </article>
        <?= rcTear() ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
// One receipt-shaped page: the page is as tall as the receipt, so nothing is cut onto a second page.
async function downloadReceiptPDF() {
    await document.fonts.ready;
    const el = document.getElementById('receiptArea');
    const pxToMm = px => px * 25.4 / 96;
    const w = pxToMm(el.offsetWidth) + 16;
    const h = pxToMm(el.scrollHeight) + 18; // +2mm slack: rounding never spills onto a 2nd page
    // Hide the page scroll bar while capturing (it shifts the image sideways), and use plain-width
    // text for the PDF (the PDF tool mis-measures the wide font and leaves gaps between letters).
    const root = document.documentElement;
    const oldOverflow = root.style.overflow;
    root.style.overflow = 'hidden';
    el.classList.add('rc-pdf');
    try {
    await html2pdf().set({
        margin: 8,
        filename: <?php echo json_encode($receiptNumber . '.pdf'); ?>,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, backgroundColor: '#E4E4E1', scrollX: 0, scrollY: 0 },
        jsPDF: { unit: 'mm', format: [w, h], orientation: 'portrait' },
        pagebreak: { mode: ['avoid-all'] }
    }).from(el).save();
    } finally {
        el.classList.remove('rc-pdf');
        root.style.overflow = oldOverflow;
    }
}
</script>
</body>
</html>
