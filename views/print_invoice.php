<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('billing.manage');

require_once '../config/Database.php';
require_once '../includes/receipt_ui.php';

$db = (new Database())->getConnection();

$invoice_id = intval($_GET['invoice_id'] ?? 0);
// ?embed=1: shown inside the staff popup (no toolbar; the popup has the buttons)
$isEmbed = !empty($_GET['embed']);

$stmt = $db->prepare("
    SELECT
        i.*,
        c.first_name,
        c.last_name,
        c.contact_number,
        jo.job_order_number,
        jo.description AS job_description,
        po.order_id AS part_order_number
    FROM invoice i
    JOIN customer c ON i.customer_id = c.customer_id
    LEFT JOIN job_order jo ON i.job_order_id = jo.job_order_id
    LEFT JOIN part_order po ON i.part_order_id = po.order_id
    WHERE i.invoice_id = ?
    LIMIT 1
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    require_once __DIR__ . '/../includes/error_page.php';
    renderErrorPage(404, 'Invoice', 'Invoice not found',
        'This invoice does not exist or the link is incomplete. Open it again from Invoice History.',
        [['label' => 'Go to Invoice History', 'href' => 'views/invoice_history.php', 'primary' => true], ['label' => '← Go back', 'href' => 'javascript:history.back()']]);
}

$stmtPayments = $db->prepare("
    SELECT amount, payment_method, reference_number, payment_date, is_down_payment
    FROM payment
    WHERE invoice_id = ?
    ORDER BY payment_date ASC
");
$stmtPayments->execute([$invoice_id]);
$payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

$referenceNo = $invoice['invoice_type'] === 'Service'
    ? $invoice['job_order_number']
    : 'PO-' . $invoice['part_order_number'];

/* Item lines (read-only). The lines always add up to the invoice total:
   - repair job: the parts used on the job, plus one line for the repair work (total minus parts)
   - web order: the parts in the order */
$lines = [];

if ($invoice['invoice_type'] === 'Service' && !empty($invoice['job_order_id'])) {
    $stmtParts = $db->prepare("
        SELECT p.part_name, jop.quantity_used AS qty, jop.unit_price_at_use AS price, jop.subtotal
        FROM job_order_part jop
        JOIN part p ON p.part_id = jop.part_id
        WHERE jop.job_order_id = ?
        ORDER BY jop.job_part_id
    ");
    $stmtParts->execute([$invoice['job_order_id']]);
    $partLines = $stmtParts->fetchAll(PDO::FETCH_ASSOC);

    $stmtServices = $db->prepare("
        SELECT s.service_name
        FROM job_order_service jos
        JOIN service s ON s.service_id = jos.service_id
        WHERE jos.job_order_id = ?
        ORDER BY jos.job_service_id
    ");
    $stmtServices->execute([$invoice['job_order_id']]);
    $serviceNames = $stmtServices->fetchAll(PDO::FETCH_COLUMN);

    $partsTotal = array_sum(array_map('floatval', array_column($partLines, 'subtotal')));
    $workAmount = max(floatval($invoice['total_amount']) - $partsTotal, 0);

    $workTitle = !empty($serviceNames) ? implode(', ', $serviceNames) : 'Repair service';
    $lines[] = [
        'title' => $workTitle,
        'note' => trim((string) ($invoice['job_description'] ?? '')),
        'qty' => 1,
        'price' => $workAmount,
        'amount' => $workAmount,
    ];

    foreach ($partLines as $part) {
        $lines[] = [
            'title' => $part['part_name'],
            'note' => '',
            'qty' => intval($part['qty']),
            'price' => floatval($part['price']),
            'amount' => floatval($part['subtotal']),
        ];
    }
} elseif (!empty($invoice['part_order_id'])) {
    $stmtItems = $db->prepare("
        SELECT p.part_name, poi.quantity, poi.unit_price
        FROM part_order_item poi
        JOIN part p ON p.part_id = poi.part_id
        WHERE poi.order_id = ?
        ORDER BY poi.order_item_id
    ");
    $stmtItems->execute([$invoice['part_order_id']]);

    foreach ($stmtItems->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $lines[] = [
            'title' => $item['part_name'],
            'note' => '',
            'qty' => intval($item['quantity']),
            'price' => floatval($item['unit_price']),
            'amount' => intval($item['quantity']) * floatval($item['unit_price']),
        ];
    }
}

if (empty($lines)) {
    $lines[] = [
        'title' => $invoice['invoice_type'] === 'Service' ? 'Repair Service Billing' : 'Auto Parts Purchase',
        'note' => '',
        'qty' => 1,
        'price' => floatval($invoice['total_amount']),
        'amount' => floatval($invoice['total_amount']),
    ];
}

function docMoney($amount) {
    return '₱' . number_format(floatval($amount), 2);
}

$issued = !empty($invoice['date_issued']) ? strtotime($invoice['date_issued']) : time();

$statusTone = ['Paid' => 'gray', 'Partial' => 'amber', 'Not Paid' => 'red'][$invoice['payment_status']] ?? 'gray';
$stamp = ['Paid' => ['PAID', 'green'], 'Partial' => ['PARTIAL', 'amber'], 'Not Paid' => ['UNPAID', 'red']][$invoice['payment_status']] ?? ['UNPAID', 'red'];
$contact = trim((string) ($invoice['contact_number'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/receipt.css?v=<?= filemtime(__DIR__ . '/../assets/css/receipt.css') ?>">
</head>
<body class="rc-body<?= $isEmbed ? ' is-embed' : '' ?>">

<div class="rc-bar no-print">
    <div class="rc-bar-inner">
        <a href="invoice_history.php" class="rc-btn">← Back to Invoice History</a>
        <div class="rc-bar-actions">
            <button type="button" class="rc-btn rc-btn-solid" onclick="downloadInvoicePDF()">Download PDF</button>
            <button type="button" class="rc-btn" onclick="window.print()">Print</button>
        </div>
    </div>
</div>

<div class="rc-wrap">
    <div id="invoiceArea">
        <article class="rc-receipt">
            <?php rcHeader('Invoice', '<p class="rc-number">' . rcEscape($invoice['invoice_number']) . '</p>'); ?>

            <hr class="rc-sep">
            <dl class="rc-details rc-section">
                <dt>Invoice date</dt><dd><?= date('M d, Y', $issued) ?></dd>
                <dt>Type</dt><dd><?= rcEscape($invoice['invoice_type']) ?></dd>
                <dt>Reference</dt><dd class="rc-mono"><?= rcEscape($referenceNo) ?></dd>
                <?php if (!empty($invoice['due_date']) && $invoice['payment_status'] !== 'Paid'): ?>
                    <dt>Due date</dt><dd><?= date('M d, Y', strtotime($invoice['due_date'])) ?></dd>
                <?php endif; ?>
                <dt>Status</dt><dd><?= rcBadge($invoice['payment_status'], $statusTone) ?></dd>
            </dl>

            <hr class="rc-sep">
            <div class="rc-parties rc-section">
                <div>
                    <p class="rc-label">Billed to</p>
                    <span class="rc-party-name"><?= rcEscape($invoice['first_name'] . ' ' . $invoice['last_name']) ?></span>
                    <span class="rc-muted">Customer</span>
                    <span class="rc-muted rc-mono"><?= rcEscape($contact !== '' ? $contact : 'N/A') ?></span>
                </div>
                <div>
                    <p class="rc-label">Issued by</p>
                    <span class="rc-party-name">Norily's</span>
                    <span class="rc-muted">Vila Rosario</span>
                    <span class="rc-muted">La Union</span>
                </div>
            </div>

            <hr class="rc-sep">
            <div class="rc-section">
                <p class="rc-label">Items</p>
                <?php foreach ($lines as $line): ?>
                    <div class="rc-item">
                        <div class="rc-item-main">
                            <?= rcEscape($line['title']) ?>
                            <?php if ($line['note'] !== ''): ?><span class="rc-muted"><?= rcEscape($line['note']) ?></span><?php endif; ?>
                            <span class="rc-item-sub"><?= intval($line['qty']) ?> × <?= rcMoney($line['price']) ?></span>
                        </div>
                        <span class="rc-money"><?= rcMoney($line['amount']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <hr class="rc-sep">
            <div class="rc-totals">
                <div class="rc-row"><span>Subtotal</span><span class="rc-money"><?= rcMoney($invoice['total_amount']) ?></span></div>
                <div class="rc-row"><span>Amount paid</span><span class="rc-money"><?= rcMoney($invoice['amount_paid']) ?></span></div>
                <div class="rc-row rc-total"><span>Total</span><span class="rc-money"><?= rcMoney($invoice['total_amount']) ?></span></div>
                <span class="rc-stamp is-<?= $stamp[1] ?>" aria-hidden="true"><?= $stamp[0] ?></span>
            </div>
            <div class="rc-inkbox"><span>Balance due</span><strong><?= rcMoney($invoice['balance_due']) ?></strong></div>

            <hr class="rc-sep">
            <div class="rc-section">
                <p class="rc-label">Payment history</p>
                <?php if (count($payments) > 0): ?>
                    <table class="rc-table">
                        <thead>
                            <tr><th style="width: 34%">Date</th><th>Method</th><th class="rc-right" style="width: 30%">Amount</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="rc-mono"><?= date('d M Y', strtotime($payment['payment_date'])) ?><small><?= date('g:i A', strtotime($payment['payment_date'])) ?></small></td>
                                    <td><?= rcEscape($payment['payment_method']) ?><?php if (!empty($payment['is_down_payment'])): ?><small>Down payment</small><?php endif; ?><?php if (!empty($payment['reference_number'])): ?><small>Ref <?= rcEscape($payment['reference_number']) ?></small><?php endif; ?></td>
                                    <td class="rc-right rc-money"><?= rcMoney($payment['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="rc-empty">No payments recorded yet.</p>
                <?php endif; ?>
            </div>

            <hr class="rc-sep">
            <div class="rc-section">
                <p class="rc-label">Payment note</p>
                <p class="rc-note">For parts orders, payment and claiming are processed at the shop after order approval and pickup confirmation. For repair services, this invoice reflects the recorded billing and payment status in DASPMS.</p>
                <p class="rc-note"><strong>Terms.</strong> This digital invoice is generated by DASPMS and may be used as the shop's digital reference for the handwritten official receipt.</p>
            </div>

            <div class="rc-sign"><strong>Authorized Staff</strong><span>Prepared / Verified by</span></div>

            <footer class="rc-foot">
                <p class="rc-thanks">Salamat po!</p>
                <p class="rc-foot-code"><?= rcEscape($invoice['invoice_number']) ?> · Printed <?= date('M d, Y') ?></p>
            </footer>
        </article>
        <?= rcTear() ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
// One receipt-shaped page: the page is as tall as the receipt, so nothing is cut onto a second page.
async function downloadInvoicePDF() {
    await document.fonts.ready;
    const el = document.getElementById('invoiceArea');
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
        filename: <?php echo json_encode($invoice['invoice_number'] . '.pdf'); ?>,
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
