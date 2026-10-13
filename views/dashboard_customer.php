<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('dashboard.customer');

require_once '../config/Database.php';
require_once '../models/ServiceRequest.php';

$db = (new Database())->getConnection();
$serviceRequestModel = new ServiceRequest($db);

$stmtC = $db->prepare("
    SELECT customer_id
    FROM customer
    WHERE user_id = ?
    LIMIT 1
");
$stmtC->execute([$_SESSION['user_id']]);
$customer_id = $stmtC->fetchColumn();

if (!$customer_id) {
    require_once __DIR__ . '/../includes/error_page.php';
    renderErrorPage(404, 'Customer portal', 'Profile not found',
        'Your account is not linked to a customer profile yet. Please contact the shop so they can fix it.',
        [['label' => 'Contact the shop', 'href' => 'public_pages/contact.php', 'primary' => true], ['label' => '← Back to home', 'href' => '']]);
}

$stmtVehicles = $db->prepare("
    SELECT *
    FROM vehicle
    WHERE customer_id = ?
      AND status = 'Active'
    ORDER BY created_at DESC, vehicle_id DESC
");
$stmtVehicles->execute([$customer_id]);
$myVehicles = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);
$vehicleCount = count($myVehicles);

$stmtActiveServices = $serviceRequestModel->readActiveServices();
$activeServices = $stmtActiveServices->fetchAll(PDO::FETCH_ASSOC);

$stmtServiceRequests = $serviceRequestModel->readByCustomer($customer_id);
$myServiceRequests = $stmtServiceRequests->fetchAll(PDO::FETCH_ASSOC);

$stmtJO = $db->prepare("
    SELECT 
        jo.job_order_number,
        v.plate_number,
        v.make,
        v.model,
        jo.status,
        i.payment_status,
        i.balance_due,
        jo.date_created
    FROM job_order jo
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id
    WHERE jo.customer_id = ?
    ORDER BY jo.date_created DESC
");
$stmtJO->execute([$customer_id]);
$jobOrders = $stmtJO->fetchAll(PDO::FETCH_ASSOC);

$stmtOrders = $db->prepare("
    SELECT 
        po.order_id,
        po.order_date,
        po.preferred_pickup_date,
        po.preferred_pickup_time,
        po.pickup_notes,
        po.payment_method,
        po.gcash_reference,
        po.gcash_payment_amount,
        po.gcash_verification_status,
        po.gcash_verified_at,
        po.payment_notes,
        po.status,
        po.total_amount,
        i.payment_status,
        i.balance_due,
        GROUP_CONCAT(
            CONCAT(p.part_name, ' x', poi.quantity)
            ORDER BY p.part_name ASC
            SEPARATOR '<br>'
        ) AS items
    FROM part_order po
    JOIN part_order_item poi ON po.order_id = poi.order_id
    JOIN part p ON poi.part_id = p.part_id
    LEFT JOIN invoice i ON po.order_id = i.part_order_id
    WHERE po.customer_id = ?
    GROUP BY 
        po.order_id,
        po.order_date,
        po.preferred_pickup_date,
        po.preferred_pickup_time,
        po.pickup_notes,
        po.payment_method,
        po.gcash_reference,
        po.gcash_payment_amount,
        po.gcash_verification_status,
        po.gcash_verified_at,
        po.payment_notes,
        po.status,
        po.total_amount,
        i.payment_status,
        i.balance_due
    ORDER BY 
        po.order_date DESC,
        po.order_id DESC
");
$stmtOrders->execute([$customer_id]);
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

$activeRepairCount = 0;
$pendingPaymentCount = 0;
$pendingBalanceTotal = 0;
$readyPickupCount = 0;
$readyPickupOrders = [];
$pendingServiceRequestCount = 0;

foreach ($jobOrders as $repair) {
    if (($repair['status'] ?? '') !== 'Completed' && ($repair['status'] ?? '') !== 'Cancelled') {
        $activeRepairCount++;
    }

    $paymentStatus = $repair['payment_status'] ?? 'Not Paid';

    if ($paymentStatus !== 'Paid') {
        $pendingPaymentCount++;
        $pendingBalanceTotal += floatval($repair['balance_due'] ?? 0);
    }
}

foreach ($orders as $order) {
    if (($order['status'] ?? '') === 'Ready for Pickup') {
        $readyPickupCount++;
        $readyPickupOrders[] = $order;
    }
}

foreach ($myServiceRequests as $request) {
    if (($request['status'] ?? '') === 'Pending') {
        $pendingServiceRequestCount++;
    }
}

$hour = intval(date('H'));

if ($hour < 12) {
    $greeting = 'Good Morning';
} elseif ($hour < 18) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}

function isCustomerGcashOrder($paymentMethod) {
    $paymentMethod = trim((string) $paymentMethod);

    return in_array($paymentMethod, [
        'GCash Down Payment',
        'GCash Full Payment',
        'GCash Reservation'
    ]);
}

function getCustomerRepairBadge($status) {
    switch ($status) {
        case 'Pending':
            return 'st pend';
        case 'In Progress':
            return 'st prog';
        case 'Ready for Pickup':
            return 'st ready solid';
        case 'Completed':
            return 'st done';
        case 'Cancelled':
            return 'st bad';
        default:
            return 'st done';
    }
}

function getCustomerOrderBadge($status) {
    switch ($status) {
        case 'Pending':
            return 'st pend';
        case 'Approved':
            return 'st prog';
        case 'Ready for Pickup':
            return 'st ready solid';
        case 'Completed':
            return 'st done';
        case 'Cancelled':
            return 'st bad';
        default:
            return 'st done';
    }
}

function getCustomerGcashBadge($status) {
    switch ($status) {
        case 'Verified':
            return 'st ok';
        case 'Rejected':
            return 'st bad';
        case 'Pending Verification':
        case 'Awaiting Payment':
            return 'st pend';
        case 'Not Required':
            return 'st done';
        default:
            return 'st done';
    }
}

function getCustomerPaymentBadge($paymentStatus) {
    switch ($paymentStatus) {
        case 'Paid':
            return 'st done';
        case 'Partial':
            return 'st pend';
        case 'Not Paid':
            return 'st bad';
        case 'No Invoice':
            return 'st done';
        default:
            return 'st done';
    }
}

function getServiceRequestBadge($status) {
    switch ($status) {
        case 'Pending':
            return 'st pend';
        case 'Converted':
            return 'st prog';
        case 'Rejected':
            return 'st bad';
        case 'Cancelled':
            return 'st bad';
        default:
            return 'st done';
    }
}

function getServiceRequestDisplayLabel($status) {
    switch ($status) {
        case 'Pending':
            return 'Pending';
        case 'Converted':
            return 'Job Order Created';
        case 'Rejected':
            return 'Rejected';
        case 'Cancelled':
            return 'Cancelled';
        default:
            return 'N/A';
    }
}

function getCustomerPaymentInstruction($paymentMethod, $paymentStatus, $balanceDue, $gcashStatus) {
    $paymentMethod = trim((string) $paymentMethod);
    $paymentStatus = trim((string) $paymentStatus);
    $balanceDue = floatval($balanceDue);

    if ($paymentStatus === 'Paid') {
        return [
            'label' => 'Paid',
            'badge' => 'st done'
        ];
    }

    if ($paymentStatus === 'Partial') {
        if ($balanceDue > 0) {
            return [
                'label' => 'Balance at Shop',
                'badge' => 'st pend'
            ];
        }

        return [
            'label' => 'Partially Paid',
            'badge' => 'st pend'
        ];
    }

    if (isCustomerGcashOrder($paymentMethod)) {
        if ($gcashStatus === 'Verified') {
            if ($paymentMethod === 'GCash Full Payment') {
                return [
                    'label' => 'GCash Verified',
                    'badge' => 'st ok'
                ];
            }

            return [
                'label' => 'DP Verified',
                'badge' => 'st ok'
            ];
        }

        if ($gcashStatus === 'Awaiting Payment') {
            return [
                'label' => 'Awaiting GCash Payment',
                'badge' => 'st pend'
            ];
        }

        if ($gcashStatus === 'Rejected') {
            return [
                'label' => 'GCash Rejected',
                'badge' => 'st bad'
            ];
        }

        return [
            'label' => 'Pending GCash Check',
            'badge' => 'st pend'
        ];
    }

    return [
        'label' => 'Pay at Shop',
        'badge' => 'st done'
    ];
}

// Part names in an order, one per line (the query joins them with <br>).
function customerOrderItemsHtml($items) {
    if (empty($items)) {
        return 'No items';
    }

    return implode('<br>', array_map(function ($line) {
        return htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
    }, explode('<br>', $items)));
}

/*
    Feature card: the repair to show at the top of the page (display only, built
    from the rows already loaded above). Ready for Pickup first, then the newest
    active job order. Its service name, request date and mechanic come from the
    matching service request, and the warranty from the active services list.
*/
$featureJob = null;

foreach ($jobOrders as $job) {
    if (($job['status'] ?? '') === 'Ready for Pickup') {
        $featureJob = $job;
        break;
    }
}

if ($featureJob === null) {
    foreach ($jobOrders as $job) {
        if (!in_array($job['status'] ?? '', ['Completed', 'Cancelled'], true)) {
            $featureJob = $job;
            break;
        }
    }
}

$featureRequest = null;
$featureWarrantyDays = 0;

if ($featureJob !== null) {
    foreach ($myServiceRequests as $request) {
        if (!empty($request['job_order_number']) && $request['job_order_number'] === $featureJob['job_order_number']) {
            $featureRequest = $request;
            break;
        }
    }

    if ($featureRequest !== null) {
        foreach ($activeServices as $service) {
            if (intval($service['service_id']) === intval($featureRequest['service_id'] ?? 0)) {
                $featureWarrantyDays = intval($service['warranty_days'] ?? 0);
                break;
            }
        }
    }
}

$carIsReady = $featureJob !== null && ($featureJob['status'] ?? '') === 'Ready for Pickup';
$customerFirstName = $_SESSION['first_name'] ?? 'Customer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Garage - Norily's Customer Portal</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<?php require_once __DIR__ . '/../includes/customer_ui.php'; cpHeadAssets(); ?>
</head>

<body class="cp-body">
<?php include '../includes/sidebar.php'; ?>

<main class="cp-main" id="cpMain">

    <header class="cp-garage-head">
        <div>
            <div class="cp-garage-meta">
                <p class="cp-label">My garage</p>
                <p class="cp-label cp-mono"><?php echo date('M d, Y'); ?></p>
            </div>
            <h1 class="cp-title">
                <?php if ($carIsReady): ?>
                    Hi, <?php echo cpEscape($customerFirstName); ?>. Your car is ready.
                <?php else: ?>
                    <?php echo $greeting; ?>, <?php echo cpEscape($customerFirstName); ?>.
                <?php endif; ?>
            </h1>
            <p class="cp-lead">Welcome back. Here's your repair and order summary.</p>
        </div>
        <div class="cp-pagehead-actions">
            <a href="customer_services.php" class="cp-btn cp-btn-dark">Request a service</a>
            <a href="customer_parts.php" class="cp-btn cp-btn-line">Browse parts</a>
        </div>
    </header>

    <?php cpFlash(); ?>

    <!-- Feature card: the repair that needs attention now -->
    <section class="cp-feature" aria-label="Current repair">
        <?php if ($featureJob !== null): ?>
            <?php
                $featureStatus = $featureJob['status'] ?? 'Pending';
                $featureVehicle = trim(($featureJob['make'] ?? '') . ' ' . ($featureJob['model'] ?? ''));
                $featurePayment = $featureJob['payment_status'] ?? 'Not Paid';
                $featureService = $featureRequest['service_name'] ?? 'Vehicle repair';

                $stepIndex = [
                    'Pending' => 1,
                    'In Progress' => 2,
                    'Ready for Pickup' => 3,
                    'Completed' => 4,
                ][$featureStatus] ?? 1;

                $timeline = [
                    [
                        'title' => 'Request received',
                        'note' => $featureRequest ? ($featureRequest['request_number'] ?? '') : 'Walk-in or shop-created job',
                        'date' => !empty($featureRequest['created_at']) ? date('M d', strtotime($featureRequest['created_at'])) : '',
                    ],
                    [
                        'title' => 'Job order created',
                        'note' => $featureJob['job_order_number'] ?? '',
                        'date' => !empty($featureJob['date_created']) ? date('M d', strtotime($featureJob['date_created'])) : '',
                    ],
                    [
                        'title' => 'Repair in progress',
                        'note' => !empty($featureRequest['assigned_mechanic_name']) ? 'Mechanic: ' . $featureRequest['assigned_mechanic_name'] : 'Handled by the shop mechanics',
                        'date' => '',
                    ],
                    [
                        'title' => 'Ready for pickup',
                        'note' => 'Settle any balance at the shop',
                        'date' => '',
                    ],
                    [
                        'title' => 'Completed',
                        'note' => 'Vehicle released',
                        'date' => '',
                    ],
                ];
            ?>
            <div class="cp-feature-main">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <p class="cp-label mb-0"><?php echo $carIsReady ? 'Ready for pickup' : 'Current repair'; ?></p>
                    <span class="<?php echo getCustomerRepairBadge($featureStatus); ?>"><?php echo cpEscape($featureStatus); ?></span>
                </div>

                <h2 class="cp-feature-title"><?php echo cpEscape($featureService); ?></h2>

                <div class="cp-feature-meta">
                    <span><span class="cp-mono"><?php echo cpEscape($featureJob['plate_number'] ?? 'N/A'); ?></span> · <?php echo cpEscape($featureVehicle !== '' ? $featureVehicle : 'Vehicle'); ?></span>
                    <span>JO <span class="cp-mono"><?php echo cpEscape($featureJob['job_order_number'] ?? 'N/A'); ?></span></span>
                    <?php if ($featureWarrantyDays > 0): ?>
                        <span><span class="cp-mono"><?php echo $featureWarrantyDays; ?></span>-day warranty</span>
                    <?php endif; ?>
                </div>

                <div class="cp-feature-money">
                    <div>
                        <p class="cp-label">Payment</p>
                        <strong><?php echo cpEscape($featurePayment); ?></strong>
                    </div>
                    <div>
                        <p class="cp-label">Balance due</p>
                        <strong><?php echo cpMoney($featureJob['balance_due'] ?? 0); ?></strong>
                    </div>
                </div>

                <div class="cp-feature-actions">
                    <a href="#repairStatusSection" class="cp-btn cp-btn-light">View my repairs</a>
                    <a href="customer_service_requests.php" class="cp-btn cp-btn-line">My service requests</a>
                </div>
            </div>

            <div class="cp-feature-side">
                <p class="cp-label">Progress</p>
                <ol class="cp-timeline">
                    <?php foreach ($timeline as $i => $step): ?>
                        <?php
                            if ($featureStatus === 'Completed' || $i < $stepIndex) {
                                $stepState = 'is-done';
                            } elseif ($i === $stepIndex) {
                                $stepState = 'is-now';
                            } else {
                                $stepState = 'is-todo';
                            }
                        ?>
                        <li class="<?php echo $stepState; ?>">
                            <span class="cp-tl-dot" aria-hidden="true"></span>
                            <span class="cp-tl-text">
                                <strong><?php echo cpEscape($step['title']); ?></strong>
                                <?php if ($step['note'] !== ''): ?>
                                    <span><?php echo cpEscape($step['note']); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="cp-tl-date"><?php echo cpEscape($step['date']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php else: ?>
            <div class="cp-feature-main">
                <p class="cp-label">Current repair</p>
                <h2 class="cp-feature-title">No vehicle in the shop right now.</h2>
                <p class="cp-feature-text">Send a service request and the shop will review it, give you an estimate, and track the repair here.</p>
                <div class="cp-feature-actions mt-auto">
                    <a href="customer_services.php" class="cp-btn cp-btn-light">Request a service</a>
                    <a href="customer_parts.php" class="cp-btn cp-btn-line">Browse parts</a>
                </div>
            </div>
            <div class="cp-feature-side">
                <p class="cp-label">How a repair works</p>
                <ol class="cp-timeline">
                    <li class="is-todo"><span class="cp-tl-dot" aria-hidden="true"></span><span class="cp-tl-text"><strong>Request received</strong><span>You pick a service, vehicle and date</span></span><span class="cp-tl-date"></span></li>
                    <li class="is-todo"><span class="cp-tl-dot" aria-hidden="true"></span><span class="cp-tl-text"><strong>Job order created</strong><span>The shop reviews and approves it</span></span><span class="cp-tl-date"></span></li>
                    <li class="is-todo"><span class="cp-tl-dot" aria-hidden="true"></span><span class="cp-tl-text"><strong>Repair in progress</strong><span>A mechanic works on your vehicle</span></span><span class="cp-tl-date"></span></li>
                    <li class="is-todo"><span class="cp-tl-dot" aria-hidden="true"></span><span class="cp-tl-text"><strong>Ready for pickup</strong><span>Settle any balance at the shop</span></span><span class="cp-tl-date"></span></li>
                </ol>
            </div>
        <?php endif; ?>
    </section>

    <section class="cp-stats" aria-label="Summary">
        <div class="cp-stat">
            <p class="cp-label">Active repairs</p>
            <span class="cp-stat-value"><?php echo $activeRepairCount; ?></span>
            <span class="cp-stat-note">Repair jobs still in progress</span>
        </div>
        <div class="cp-stat">
            <p class="cp-label">Service requests</p>
            <span class="cp-stat-value"><?php echo $pendingServiceRequestCount; ?></span>
            <span class="cp-stat-note">Pending online requests</span>
        </div>
        <div class="cp-stat">
            <p class="cp-label">Balance due</p>
            <span class="cp-stat-value"><?php echo cpMoney($pendingBalanceTotal); ?></span>
            <span class="cp-stat-note">Total unpaid repair balance</span>
        </div>
        <div class="cp-stat">
            <p class="cp-label">Ready for pickup</p>
            <span class="cp-stat-value"><?php echo $readyPickupCount; ?></span>
            <span class="cp-stat-note">Parts orders ready at the shop</span>
        </div>
    </section>

    <!-- 01 Quick actions -->
    <section class="cp-sec" aria-labelledby="secActions">
        <div class="cp-sec-head">
            <h2 id="secActions"><span class="cp-sec-num">01</span>What do you need?</h2>
        </div>
        <div class="cp-actions" data-reveal-group>
            <a href="customer_services.php" class="cp-action">
                <span class="cp-action-num">01</span><span class="cp-action-arrow" aria-hidden="true">↗</span>
                <span><strong>Request a service</strong><span class="cp-action-sub">Book a repair or check-up</span></span>
            </a>
            <a href="customer_parts.php" class="cp-action">
                <span class="cp-action-num">02</span><span class="cp-action-arrow" aria-hidden="true">↗</span>
                <span><strong>Browse parts</strong><span class="cp-action-sub">Reserve parts for pickup</span></span>
            </a>
            <a href="customer_services.php" class="cp-action">
                <span class="cp-action-num">03</span><span class="cp-action-arrow" aria-hidden="true">↗</span>
                <span><strong>Services offered</strong><span class="cp-action-sub">Prices and warranties</span></span>
            </a>
            <a href="customer_cart.php" class="cp-action">
                <span class="cp-action-num">04</span><span class="cp-action-arrow" aria-hidden="true">↗</span>
                <span><strong>My cart</strong><span class="cp-action-sub"><?php echo intval($cartCount ?? 0); ?> item<?php echo intval($cartCount ?? 0) === 1 ? '' : 's'; ?> waiting</span></span>
            </a>
            <a href="#repairStatusSection" class="cp-action">
                <span class="cp-action-num">05</span><span class="cp-action-arrow" aria-hidden="true">↓</span>
                <span><strong>My repairs</strong><span class="cp-action-sub">Job orders and balances</span></span>
            </a>
            <a href="#orderStatusSection" class="cp-action">
                <span class="cp-action-num">06</span><span class="cp-action-arrow" aria-hidden="true">↓</span>
                <span><strong>My orders</strong><span class="cp-action-sub">Parts orders and payments</span></span>
            </a>
        </div>
    </section>

    <!-- 02 Vehicles -->
    <section class="cp-sec" id="myVehiclesSection" aria-labelledby="secVehicles">
        <div class="cp-sec-head">
            <h2 id="secVehicles"><span class="cp-sec-num">02</span>My vehicles</h2>
            <div class="cp-sec-link">
                <button type="button" class="cp-link" data-bs-toggle="modal" data-bs-target="#addVehicleModal">+ Add vehicle</button>
            </div>
        </div>

        <?php if (empty($myVehicles)): ?>
            <div class="cp-empty">
                <strong>No vehicles registered yet</strong>
                Add your vehicle details so the shop can connect future repairs to your profile.
                <div><button type="button" class="cp-btn cp-btn-dark" data-bs-toggle="modal" data-bs-target="#addVehicleModal">Add vehicle</button></div>
            </div>
        <?php else: ?>
            <div class="cp-vehicles">
                <?php foreach ($myVehicles as $vehicle): ?>
                    <?php
                        $vehicleStatus = $vehicle['status'] ?? 'Active';
                        $vehicleName = trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
                        $vehicleNotes = !empty($vehicle['notes']) ? $vehicle['notes'] : 'Registered vehicle';
                    ?>
                    <article class="cp-vehicle">
                        <div class="cp-vehicle-top">
                            <span class="cp-plate"><?php echo cpEscape($vehicle['plate_number'] ?? 'N/A'); ?></span>
                            <?php echo cpBadge($vehicleStatus); ?>
                        </div>
                        <h3 class="cp-vehicle-name"><?php echo cpEscape($vehicleName !== '' ? $vehicleName : 'Vehicle'); ?></h3>
                        <div class="cp-vehicle-meta">
                            <span>Year <span class="cp-mono"><?php echo cpEscape($vehicle['year'] ?? 'N/A'); ?></span></span>
                            <span><?php echo cpEscape($vehicle['color'] ?? 'N/A'); ?></span>
                            <span class="cp-mono cp-faint">VID-<?php echo intval($vehicle['vehicle_id']); ?></span>
                        </div>
                        <p class="cp-vehicle-notes"><?php echo cpEscape($vehicleNotes); ?></p>
                        <div class="cp-vehicle-foot">
                            <span class="cp-faint small">Used for service requests</span>
                            <button
                                type="button"
                                class="cp-link"
                                data-bs-toggle="modal"
                                data-bs-target="#editVehicleModal"
                                data-vehicle-id="<?php echo intval($vehicle['vehicle_id']); ?>"
                                data-plate-number="<?php echo htmlspecialchars($vehicle['plate_number'] ?? '', ENT_QUOTES); ?>"
                                data-make="<?php echo htmlspecialchars($vehicle['make'] ?? '', ENT_QUOTES); ?>"
                                data-model="<?php echo htmlspecialchars($vehicle['model'] ?? '', ENT_QUOTES); ?>"
                                data-year="<?php echo htmlspecialchars($vehicle['year'] ?? '', ENT_QUOTES); ?>"
                                data-color="<?php echo htmlspecialchars($vehicle['color'] ?? '', ENT_QUOTES); ?>"
                                data-notes="<?php echo htmlspecialchars($vehicle['notes'] ?? '', ENT_QUOTES); ?>"
                            >Edit</button>
                        </div>
                    </article>
                <?php endforeach; ?>
                <button type="button" class="cp-vehicle cp-vehicle-add" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                    <span aria-hidden="true">+</span>
                    Add vehicle
                </button>
            </div>
        <?php endif; ?>
    </section>

    <!-- 03 Service requests -->
    <section class="cp-sec" id="serviceRequestsSection" aria-labelledby="secRequests">
        <div class="cp-sec-head">
            <h2 id="secRequests"><span class="cp-sec-num">03</span>Service requests</h2>
            <div class="cp-sec-link">
                <a href="customer_service_requests.php" class="cp-link">View all ↗</a>
            </div>
        </div>

        <?php if (empty($myServiceRequests)): ?>
            <div class="cp-empty">
                <strong>No service requests yet</strong>
                Submit a service request for one of your registered vehicles.
                <div><a href="customer_services.php" class="cp-btn cp-btn-dark">Request a service</a></div>
            </div>
        <?php else: ?>
            <div class="cp-rows" data-cp-paginate="5" data-cp-noun="request">
                <?php foreach ($myServiceRequests as $request): ?>
                    <?php
                        $requestDate = !empty($request['created_at'])
                            ? date('M d, Y', strtotime($request['created_at']))
                            : 'N/A';

                        $preferredDate = !empty($request['preferred_appointment_date'])
                            ? date('M d, Y', strtotime($request['preferred_appointment_date']))
                            : 'N/A';

                        $preferredTime = !empty($request['preferred_appointment_time'])
                            ? date('h:i A', strtotime($request['preferred_appointment_time']))
                            : '';

                        $requestVehicle = trim(($request['make'] ?? '') . ' ' . ($request['model'] ?? ''));
                    ?>
                    <div class="cp-row">
                        <div class="cp-row-main">
                            <span class="cp-row-title"><?php echo cpEscape($request['service_name'] ?? 'N/A'); ?></span>
                            <div class="cp-row-sub">
                                <span class="cp-mono"><?php echo cpEscape($request['request_number'] ?? 'N/A'); ?></span>
                                <span>Sent <span class="cp-mono"><?php echo cpEscape($requestDate); ?></span></span>
                                <span><?php echo cpEscape($requestVehicle !== '' ? $requestVehicle : 'Vehicle'); ?> · <span class="cp-mono"><?php echo cpEscape($request['plate_number'] ?? 'N/A'); ?></span></span>
                            </div>
                        </div>
                        <div class="cp-row-side">
                            <span>Preferred <span class="cp-mono"><?php echo cpEscape($preferredDate . ($preferredTime !== '' ? ' ' . $preferredTime : '')); ?></span></span>
                            <?php if (($request['status'] ?? '') === 'Converted' && !empty($request['job_order_number'])): ?>
                                <span><span class="cp-mono"><?php echo cpEscape($request['job_order_number']); ?></span> created</span>
                            <?php endif; ?>
                        </div>
                        <div class="cp-row-end">
                            <span class="<?php echo getServiceRequestBadge($request['status'] ?? ''); ?>">
                                <?php echo cpEscape(getServiceRequestDisplayLabel($request['status'] ?? '')); ?>
                            </span>

                            <?php if (($request['status'] ?? '') === 'Pending'): ?>
                                <form action="../controllers/ServiceRequestController.php" method="POST" class="m-0"
                                      data-cp-confirm="Cancel this pending service request?"
                                      data-cp-confirm-title="Cancel request?"
                                      data-cp-confirm-yes="Yes, cancel it"
                                      data-cp-confirm-no="Keep request">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="cancel_customer_request">
                                    <input type="hidden" name="service_request_id" value="<?php echo intval($request['service_request_id']); ?>">
                                    <button type="submit" class="cp-link cp-link-danger">Cancel</button>
                                </form>
                            <?php else: ?>
                                <span class="cp-faint small">No action</span>
                            <?php endif; ?>
                        </div>
                        <p class="cp-row-note"><?php echo cpEscape($request['concern_description'] ?? 'No concern details'); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- 04 Repairs -->
    <section class="cp-sec" id="repairStatusSection" aria-labelledby="secRepairs">
        <div class="cp-sec-head">
            <h2 id="secRepairs"><span class="cp-sec-num">04</span>My vehicle repairs</h2>
        </div>

        <?php if (empty($jobOrders)): ?>
            <div class="cp-empty">
                <strong>No repair records yet</strong>
                Repair records will appear here once the shop creates a job order for your vehicle.
            </div>
        <?php else: ?>
            <div class="cp-rows" data-cp-paginate="5" data-cp-noun="repair">
                <?php foreach ($jobOrders as $job): ?>
                    <?php $paymentStatus = $job['payment_status'] ?? 'Not Paid'; ?>
                    <div class="cp-row">
                        <div class="cp-row-main">
                            <span class="cp-row-title">
                                <span class="cp-mono"><?php echo cpEscape($job['plate_number'] ?? 'N/A'); ?></span>
                                <span class="cp-faint">· <?php echo cpEscape(($job['make'] ?? '') . ' ' . ($job['model'] ?? '')); ?></span>
                            </span>
                            <div class="cp-row-sub">
                                <span>JO <span class="cp-mono"><?php echo cpEscape($job['job_order_number'] ?? 'N/A'); ?></span></span>
                                <span>Date <span class="cp-mono"><?php echo !empty($job['date_created']) ? date('M d, Y', strtotime($job['date_created'])) : 'N/A'; ?></span></span>
                            </div>
                        </div>
                        <div class="cp-row-side">
                            <span>Balance <span class="cp-mono"><?php echo cpMoney($job['balance_due'] ?? 0); ?></span></span>
                        </div>
                        <div class="cp-row-end">
                            <span class="<?php echo getCustomerRepairBadge($job['status'] ?? ''); ?>"><?php echo cpEscape($job['status'] ?? 'N/A'); ?></span>
                            <span class="<?php echo getCustomerPaymentBadge($paymentStatus); ?>"><?php echo cpEscape($paymentStatus); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- 05 Pickup reminders -->
    <section class="cp-sec" aria-labelledby="secPickup">
        <div class="cp-sec-head">
            <h2 id="secPickup"><span class="cp-sec-num">05</span>Pickup reminders</h2>
            <div class="cp-sec-link">
                <a href="#orderStatusSection" class="cp-link">View orders ↓</a>
            </div>
        </div>

        <?php if (empty($readyPickupOrders)): ?>
            <div class="cp-empty">
                <strong>No pickup reminders</strong>
                No parts orders are ready for pickup right now.
            </div>
        <?php else: ?>
            <div class="cp-rows">
                <?php foreach ($readyPickupOrders as $pickup): ?>
                    <div class="cp-row">
                        <div class="cp-row-main">
                            <span class="cp-row-title">Order <span class="cp-mono">#<?php echo intval($pickup['order_id']); ?></span></span>
                            <div class="cp-order-items cp-muted"><?php echo customerOrderItemsHtml($pickup['items'] ?? ''); ?></div>
                        </div>
                        <div class="cp-row-side">
                            <span class="cp-mono"><?php echo cpMoney($pickup['total_amount'] ?? 0); ?></span>
                        </div>
                        <div class="cp-row-end">
                            <span class="st ready solid">Ready for Pickup</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="cp-reminder">
            <strong>Customer reminder.</strong>
            For parts reservations, you can pay a GCash down payment or full payment online through PayMongo.
            Online payments are confirmed automatically. If you only paid a down payment,
            please settle the remaining balance at the shop before claiming your item.
        </div>
    </section>

    <!-- 06 Ordered parts -->
    <section class="cp-sec" id="orderStatusSection" aria-labelledby="secOrders">
        <div class="cp-sec-head">
            <h2 id="secOrders"><span class="cp-sec-num">06</span>My ordered parts</h2>
            <div class="cp-sec-link">
                <a href="customer_parts.php" class="cp-link">Browse parts ↗</a>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="cp-empty">
                <strong>No online orders yet</strong>
                Browse available parts and submit a pickup order when needed.
                <div><a href="customer_parts.php" class="cp-btn cp-btn-dark">Browse parts</a></div>
            </div>
        <?php else: ?>
            <div class="cp-table-wrap">
                <table class="cp-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Items</th>
                            <th>Pickup</th>
                            <th>Payment</th>
                            <th class="cp-num">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody data-cp-paginate="5" data-cp-noun="order">
                        <?php foreach ($orders as $order): ?>
                            <?php
                                $paymentMethod = trim($order['payment_method'] ?? 'Cash on Pickup');
                                $gcashReference = trim($order['gcash_reference'] ?? '');
                                $gcashAmount = floatval($order['gcash_payment_amount'] ?? 0);

                                $gcashStatus = trim($order['gcash_verification_status'] ?? '');

                                if ($gcashStatus === '' && isCustomerGcashOrder($paymentMethod)) {
                                    $gcashStatus = 'Pending Verification';
                                }

                                if ($gcashStatus === '' && !isCustomerGcashOrder($paymentMethod)) {
                                    $gcashStatus = 'Not Required';
                                }

                                $paymentStatus = trim($order['payment_status'] ?? 'No Invoice');
                                $balanceDue = floatval($order['balance_due'] ?? 0);
                                $totalAmount = floatval($order['total_amount'] ?? 0);
                                $recordedPaid = max($totalAmount - $balanceDue, 0);

                                if ($paymentStatus === 'No Invoice') {
                                    $recordedPaid = 0;
                                }

                                $shopPaidAmount = 0;

                                if (isCustomerGcashOrder($paymentMethod) && $gcashAmount > 0) {
                                    $shopPaidAmount = max($recordedPaid - $gcashAmount, 0);
                                }

                                $pickupDate = $order['preferred_pickup_date'] ?? '';
                                $pickupTime = $order['preferred_pickup_time'] ?? '';
                                $pickupNotes = $order['pickup_notes'] ?? '';

                                $pickupDateDisplay = !empty($pickupDate)
                                    ? date('M d, Y', strtotime($pickupDate))
                                    : 'No pickup schedule';

                                $pickupTimeDisplay = !empty($pickupTime)
                                    ? date('h:i A', strtotime($pickupTime))
                                    : '';

                                $orderDateDisplay = !empty($order['order_date'])
                                    ? date('M d, Y', strtotime($order['order_date']))
                                    : 'N/A';
                            ?>
                            <tr>
                                <td data-label="Order">
                                    <div>
                                        <span class="cp-cell-title cp-mono">#<?php echo intval($order['order_id']); ?></span>
                                        <span class="cp-cell-sub cp-mono"><?php echo cpEscape($orderDateDisplay); ?></span>
                                    </div>
                                </td>

                                <td data-label="Items">
                                    <div class="cp-order-items"><?php echo customerOrderItemsHtml($order['items'] ?? ''); ?></div>
                                </td>

                                <td data-label="Pickup">
                                    <div>
                                        <span class="cp-cell-title cp-mono"><?php echo cpEscape($pickupDateDisplay); ?></span>
                                        <?php if (!empty($pickupTimeDisplay)): ?>
                                            <span class="cp-cell-sub cp-mono"><?php echo cpEscape($pickupTimeDisplay); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($pickupNotes)): ?>
                                            <span class="cp-cell-sub"><?php echo cpEscape($pickupNotes); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td data-label="Payment" class="cp-td-stack">
                                    <div>
                                        <span class="cp-cell-title"><?php echo cpEscape($paymentMethod); ?></span>

                                        <?php if (isCustomerGcashOrder($paymentMethod)): ?>
                                            <div class="mt-2">
                                                <span class="<?php echo getCustomerGcashBadge($gcashStatus); ?>">GCash: <?php echo cpEscape($gcashStatus); ?></span>
                                            </div>

                                            <?php if ($gcashStatus === 'Awaiting Payment' && ($order['status'] ?? '') === 'Pending'): ?>
                                                <div class="cp-pay-card">
                                                    <p>Your reservation is waiting for its GCash payment.</p>
                                                    <form method="POST" action="../controllers/PaymentController.php" class="m-0">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="pay">
                                                        <input type="hidden" name="order_id" value="<?php echo intval($order['order_id']); ?>">
                                                        <button type="submit" class="cp-btn cp-btn-dark cp-btn-sm cp-btn-block">
                                                            Pay <?php echo cpMoney($gcashAmount); ?> with GCash
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <div class="cp-order-money">
                                            <div>
                                                <span>Total paid</span>
                                                <strong><?php echo cpMoney($recordedPaid); ?></strong>
                                            </div>
                                            <div>
                                                <span>Balance</span>
                                                <strong><?php echo cpMoney($balanceDue); ?></strong>
                                            </div>
                                            <div>
                                                <span>Invoice</span>
                                                <strong><?php echo cpEscape($paymentStatus); ?></strong>
                                            </div>
                                        </div>

                                        <ul class="cp-order-lines">
                                            <?php if (isCustomerGcashOrder($paymentMethod)): ?>
                                                <?php if (!empty($gcashReference)): ?>
                                                    <li>GCash ref: <span class="cp-mono"><?php echo cpEscape($gcashReference); ?></span></li>
                                                <?php endif; ?>

                                                <?php if ($gcashAmount > 0 && $gcashStatus === 'Verified'): ?>
                                                    <li>GCash paid: <span class="cp-mono"><?php echo cpMoney($gcashAmount); ?></span></li>
                                                <?php endif; ?>

                                                <?php if ($shopPaidAmount > 0): ?>
                                                    <li>Paid at shop: <span class="cp-mono"><?php echo cpMoney($shopPaidAmount); ?></span></li>
                                                <?php endif; ?>

                                                <?php if (!empty($order['gcash_verified_at'])): ?>
                                                    <li>Checked: <span class="cp-mono"><?php echo date('M d, Y h:i A', strtotime($order['gcash_verified_at'])); ?></span></li>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (!empty($order['payment_notes'])): ?>
                                                <li><?php echo cpEscape($order['payment_notes']); ?></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>

                                <td data-label="Total" class="cp-num">
                                    <strong class="cp-mono fw-normal"><?php echo cpMoney($totalAmount); ?></strong>
                                </td>

                                <td data-label="Status">
                                    <div>
                                        <span class="<?php echo getCustomerOrderBadge($order['status'] ?? ''); ?>"><?php echo cpEscape($order['status'] ?? 'N/A'); ?></span>
                                        <p class="cp-order-help">
                                            <?php
                                                switch ($order['status'] ?? '') {
                                                    case 'Pending':
                                                        if ($gcashStatus === 'Awaiting Payment') {
                                                            echo 'Please complete your GCash payment.';
                                                        } elseif (isCustomerGcashOrder($paymentMethod) && $gcashStatus !== 'Verified') {
                                                            echo 'Waiting for GCash verification.';
                                                        } else {
                                                            echo 'Waiting for shop confirmation.';
                                                        }
                                                        break;
                                                    case 'Approved':
                                                        echo 'Order approved and being prepared.';
                                                        break;
                                                    case 'Ready for Pickup':
                                                        if ($paymentStatus === 'Paid') {
                                                            echo 'Paid and ready for shop pickup.';
                                                        } elseif ($balanceDue > 0) {
                                                            echo 'Ready for pickup. Please settle the remaining balance at the shop.';
                                                        } else {
                                                            echo 'Ready for shop pickup.';
                                                        }
                                                        break;
                                                    case 'Completed':
                                                        echo 'Order completed. Thank you!';
                                                        break;
                                                    case 'Cancelled':
                                                        echo 'This order was cancelled.';
                                                        break;
                                                    default:
                                                        echo 'Status unavailable.';
                                                }
                                            ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <footer class="cp-legend" aria-label="Status colours">
        <p class="cp-label me-2">Status key</p>
        <span class="st pend">Pending</span>
        <span class="st prog">In progress</span>
        <span class="st ready solid">Ready for pickup</span>
        <span class="st ok">Active / verified</span>
        <span class="st done">Completed / paid</span>
        <span class="st bad">Cancelled / unpaid</span>
    </footer>
</main>

<!-- REQUEST SERVICE MODAL -->
<div class="modal fade cp-modal" id="requestServiceModal" tabindex="-1" aria-labelledby="requestServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form action="../controllers/ServiceRequestController.php" method="POST" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="submit_customer_request">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">Service request</p>
                    <h2 class="cp-modal-title" id="requestServiceModalLabel">Request service appointment</h2>
                </div>
                <button type="button" class="cp-modal-x" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>

            <div class="cp-modal-body cp-modal-scroll">
                <?php if (empty($myVehicles)): ?>
                    <div class="cp-flash cp-flash-warn mb-0">Please add a registered vehicle first before requesting a service appointment.</div>
                <?php elseif (empty($activeServices)): ?>
                    <div class="cp-flash cp-flash-warn mb-0">No active services are available right now. Please contact the shop.</div>
                <?php else: ?>
                    <div class="cp-flash cp-flash-info">Your request will be reviewed by the shop. The final estimated cost will be provided after approval.</div>

                    <div class="cp-form-grid">
                        <div class="cp-field">
                            <label for="req_vehicle_id">Vehicle *</label>
                            <select name="vehicle_id" id="req_vehicle_id" class="cp-input" required>
                                <option value="">Select vehicle</option>
                                <?php foreach ($myVehicles as $vehicle): ?>
                                    <option value="<?php echo intval($vehicle['vehicle_id']); ?>">
                                        <?php echo htmlspecialchars($vehicle['plate_number'] . ' - ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="cp-field">
                            <label for="req_service_id">Service *</label>
                            <select name="service_id" id="req_service_id" class="cp-input" required>
                                <option value="">Select service</option>
                                <?php foreach ($activeServices as $service): ?>
                                    <option value="<?php echo intval($service['service_id']); ?>">
                                        <?php echo htmlspecialchars($service['service_name']); ?>
                                        <?php if (!empty($service['base_price'])): ?>
                                            - ₱<?php echo number_format(floatval($service['base_price']), 2); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="cp-field">
                            <label for="req_date">Preferred date *</label>
                            <input type="date" name="preferred_appointment_date" id="req_date" class="cp-input" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="cp-field">
                            <label for="req_time">Preferred time</label>
                            <input type="time" name="preferred_appointment_time" id="req_time" class="cp-input">
                        </div>

                        <div class="cp-field cp-span">
                            <label for="req_concern">Concern / symptoms / additional notes *</label>
                            <textarea name="concern_description" id="req_concern" class="cp-input" rows="4" placeholder="Describe the vehicle concern or symptoms. Example: leaking oil, hard starting, unusual noise, weak brakes." required></textarea>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-bs-dismiss="modal">Cancel</button>
                <?php if (!empty($myVehicles) && !empty($activeServices)): ?>
                    <button type="submit" class="cp-btn cp-btn-dark">Submit request</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ADD VEHICLE MODAL -->
<div class="modal fade cp-modal" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form action="../controllers/VehicleController.php" method="POST" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_customer_vehicle">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">My garage</p>
                    <h2 class="cp-modal-title" id="addVehicleModalLabel">Add vehicle</h2>
                </div>
                <button type="button" class="cp-modal-x" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>

            <div class="cp-modal-body cp-modal-scroll">
                <p class="cp-hint mb-3">Register your vehicle once so it can be reused for future service records. Plate numbers must be unique. If your vehicle is already registered by the shop, contact the staff for assistance.</p>

                <div class="cp-form-grid">
                    <div class="cp-field">
                        <label for="add_plate_number">Plate number *</label>
                        <input type="text" name="plate_number" id="add_plate_number" class="cp-input cp-mono text-uppercase" placeholder="Example: ABC 1234" required>
                    </div>

                    <div class="cp-field">
                        <label for="add_make">Make / brand *</label>
                        <input type="text" name="make" id="add_make" class="cp-input" placeholder="Example: Toyota" required>
                    </div>

                    <div class="cp-field">
                        <label for="add_model">Model *</label>
                        <input type="text" name="model" id="add_model" class="cp-input" placeholder="Example: Vios" required>
                    </div>

                    <div class="cp-form-grid">
                        <div class="cp-field">
                            <label for="add_year">Year *</label>
                            <input type="text" name="year" id="add_year" class="cp-input cp-mono" maxlength="4" pattern="[0-9]{4}" inputmode="numeric" placeholder="2020" required>
                        </div>

                        <div class="cp-field">
                            <label for="add_color">Color *</label>
                            <input type="text" name="color" id="add_color" class="cp-input" placeholder="Black" required>
                        </div>
                    </div>

                    <div class="cp-field cp-span">
                        <label for="add_notes">Notes</label>
                        <textarea name="notes" id="add_notes" class="cp-input" rows="3" placeholder="Optional notes, engine concerns, or vehicle remarks"></textarea>
                    </div>
                </div>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Save vehicle</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT VEHICLE MODAL -->
<div class="modal fade cp-modal" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form action="../controllers/VehicleController.php" method="POST" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_customer_vehicle">
            <input type="hidden" name="vehicle_id" id="edit_vehicle_id">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">My garage</p>
                    <h2 class="cp-modal-title" id="editVehicleModalLabel">Edit vehicle</h2>
                </div>
                <button type="button" class="cp-modal-x" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>

            <div class="cp-modal-body cp-modal-scroll">
                <p class="cp-hint mb-3">Update your saved vehicle information. You can only edit vehicles linked to your own customer account.</p>

                <div class="cp-form-grid">
                    <div class="cp-field">
                        <label for="edit_plate_number">Plate number *</label>
                        <input type="text" name="plate_number" id="edit_plate_number" class="cp-input cp-mono text-uppercase" required>
                    </div>

                    <div class="cp-field">
                        <label for="edit_make">Make / brand *</label>
                        <input type="text" name="make" id="edit_make" class="cp-input" required>
                    </div>

                    <div class="cp-field">
                        <label for="edit_model">Model *</label>
                        <input type="text" name="model" id="edit_model" class="cp-input" required>
                    </div>

                    <div class="cp-form-grid">
                        <div class="cp-field">
                            <label for="edit_year">Year *</label>
                            <input type="text" name="year" id="edit_year" class="cp-input cp-mono" maxlength="4" pattern="[0-9]{4}" inputmode="numeric" required>
                        </div>

                        <div class="cp-field">
                            <label for="edit_color">Color *</label>
                            <input type="text" name="color" id="edit_color" class="cp-input" required>
                        </div>
                    </div>

                    <div class="cp-field cp-span">
                        <label for="edit_notes">Notes</label>
                        <textarea name="notes" id="edit_notes" class="cp-input" rows="3" placeholder="Optional notes, engine concerns, or vehicle remarks"></textarea>
                    </div>
                </div>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editVehicleModal = document.getElementById('editVehicleModal');
    const plateInputs = document.querySelectorAll('input[name="plate_number"]');

    plateInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
    });

    if (editVehicleModal) {
        editVehicleModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            if (!button) {
                return;
            }

            document.getElementById('edit_vehicle_id').value = button.getAttribute('data-vehicle-id') || '';
            document.getElementById('edit_plate_number').value = button.getAttribute('data-plate-number') || '';
            document.getElementById('edit_make').value = button.getAttribute('data-make') || '';
            document.getElementById('edit_model').value = button.getAttribute('data-model') || '';
            document.getElementById('edit_year').value = button.getAttribute('data-year') || '';
            document.getElementById('edit_color').value = button.getAttribute('data-color') || '';
            document.getElementById('edit_notes').value = button.getAttribute('data-notes') || '';
        });
    }
});
</script>

</body>
</html>