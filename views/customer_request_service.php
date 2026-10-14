<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requirePermission('service_requests.submit');

require_once '../config/Database.php';
require_once '../models/ServiceRequest.php';

$db = (new Database())->getConnection();
$serviceRequestModel = new ServiceRequest($db);

$selectedServiceId = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT);
$selectedService = null;
$dbError = '';

try {
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

    $stmtActiveServices = $serviceRequestModel->readActiveServices();
    $activeServices = $stmtActiveServices->fetchAll(PDO::FETCH_ASSOC);

    if ($selectedServiceId) {
        $stmtSelected = $db->prepare("
            SELECT
                service_id,
                service_name,
                category,
                base_price,
                requires_down_payment,
                warranty_days,
                description,
                image
            FROM service
            WHERE service_id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmtSelected->execute([$selectedServiceId]);
        $selectedService = $stmtSelected->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $dbError = 'Unable to load request form at the moment.';
    $myVehicles = [];
    $activeServices = [];
}

function serviceImagePath($image)
{
    $fallback = 'default.png';

    if (!$image) {
        return '../assets/images/services/' . $fallback;
    }

    return '../assets/images/services/' . htmlspecialchars($image);
}

function shortText($value, $fallback = 'Service details will be confirmed by the shop after assessment.', $limit = 150)
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        $value = $fallback;
    }

    if (mb_strlen($value) > $limit) {
        $value = mb_substr($value, 0, $limit) . '...';
    }

    return htmlspecialchars($value);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Request a Service - Norily's Customer Portal</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<?php require_once __DIR__ . '/../includes/customer_ui.php'; cpHeadAssets(); ?>
</head>

<body class="cp-body">
<?php include '../includes/sidebar.php'; ?>

<main class="cp-main" id="cpMain">

    <?php
        cpPageHead(
            'Request a service',
            'Submit an online service appointment request for shop review. The shop confirms the schedule and cost before any work starts.',
            '<a href="customer_services.php" class="cp-btn cp-btn-line">← Back to services</a>'
        );
    ?>

    <?php cpFlash(); ?>

    <?php if ($dbError): ?>
        <div class="cp-empty">
            <strong>Request form unavailable</strong>
            <?php echo htmlspecialchars($dbError); ?>
        </div>
    <?php else: ?>
        <div class="cp-two-col">

            <section aria-labelledby="formTitle">
                <h2 class="visually-hidden" id="formTitle">Appointment details</h2>

                <?php if (empty($myVehicles)): ?>
                    <div class="cp-flash cp-flash-warn">
                        <span>
                            Please add a registered vehicle first before requesting a service appointment.
                            You can add your vehicle from your <a href="dashboard_customer.php#myVehiclesSection">customer dashboard</a>.
                        </span>
                    </div>
                <?php elseif (empty($activeServices)): ?>
                    <div class="cp-flash cp-flash-warn">
                        <span>No active services are available right now. Please contact the shop.</span>
                    </div>
                <?php else: ?>
                    <form action="../controllers/ServiceRequestController.php" method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="submit_customer_request">

                        <div class="cp-form-rows">
                            <div class="cp-form-row">
                                <span class="cp-form-row-num">01</span>
                                <div class="cp-form-row-label">
                                    <label for="rqService">Service *</label>
                                    <small>What do you need done?</small>
                                </div>
                                <select name="service_id" id="rqService" class="cp-input" required>
                                    <option value="">Select service</option>
                                    <?php foreach ($activeServices as $service): ?>
                                        <option
                                            value="<?php echo intval($service['service_id']); ?>"
                                            <?php echo ($selectedServiceId && intval($service['service_id']) === intval($selectedServiceId)) ? 'selected' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars($service['service_name']); ?>
                                            <?php if (!empty($service['base_price'])): ?>
                                                - ₱<?php echo number_format(floatval($service['base_price']), 2); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="cp-form-row">
                                <span class="cp-form-row-num">02</span>
                                <div class="cp-form-row-label">
                                    <label for="rqVehicle">Vehicle *</label>
                                    <small>From your registered vehicles</small>
                                </div>
                                <select name="vehicle_id" id="rqVehicle" class="cp-input" required>
                                    <option value="">Select vehicle</option>
                                    <?php foreach ($myVehicles as $vehicle): ?>
                                        <option value="<?php echo intval($vehicle['vehicle_id']); ?>">
                                            <?php echo htmlspecialchars($vehicle['plate_number'] . ' - ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="cp-form-row">
                                <span class="cp-form-row-num">03</span>
                                <div class="cp-form-row-label">
                                    <strong>Preferred schedule</strong>
                                    <small>Date is required; time is optional</small>
                                </div>
                                <div class="cp-form-grid">
                                    <div class="cp-field">
                                        <label for="rqDate">Date *</label>
                                        <input type="date" name="preferred_appointment_date" id="rqDate" class="cp-input cp-mono" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="cp-field">
                                        <label for="rqTime">Time</label>
                                        <input type="time" name="preferred_appointment_time" id="rqTime" class="cp-input cp-mono">
                                    </div>
                                </div>
                            </div>

                            <div class="cp-form-row">
                                <span class="cp-form-row-num">04</span>
                                <div class="cp-form-row-label">
                                    <label for="rqConcern">Concern *</label>
                                    <small>Symptoms or additional notes</small>
                                </div>
                                <textarea name="concern_description" id="rqConcern" class="cp-input" rows="5" placeholder="Describe the vehicle concern or symptoms. Example: leaking oil, hard starting, unusual noise, weak brakes." required></textarea>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                            <a href="customer_services.php" class="cp-btn cp-btn-line">Cancel</a>
                            <button type="submit" class="cp-btn cp-btn-dark">Submit request</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <aside class="cp-sticky">
                <div class="cp-next-box">
                    <?php if ($selectedService): ?>
                        <?php
                            $basePrice = floatval($selectedService['base_price'] ?? 0);
                            $warrantyDays = intval($selectedService['warranty_days'] ?? 0);
                            $requiresDownPayment = intval($selectedService['requires_down_payment'] ?? 0);
                            $selectedImage = !empty($selectedService['image']) ? '../assets/images/services/' . $selectedService['image'] : '';
                        ?>
                        <p class="cp-label">Selected service</p>
                        <div class="cp-selected">
                            <div class="cp-selected-media"><?php echo cpThumb($selectedImage, $selectedService['service_name'] ?? 'Service'); ?></div>
                            <div>
                                <span class="cp-cell-title"><?php echo displayText($selectedService['service_name'] ?? ''); ?></span>
                                <span class="cp-cell-sub"><?php echo displayText($selectedService['category'] ?? ''); ?></span>
                                <span class="cp-cell-sub cp-mono">
                                    <?php if ($basePrice > 0): ?>
                                        Starts at ₱<?php echo number_format($basePrice, 2); ?>
                                    <?php else: ?>
                                        Cost after assessment
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <p class="cp-hint mb-3"><?php echo shortText($selectedService['description'] ?? ''); ?></p>
                        <ul class="cp-bullets mb-4">
                            <?php if ($warrantyDays > 0): ?>
                                <li>Warranty: <?php echo $warrantyDays; ?> day<?php echo $warrantyDays !== 1 ? 's' : ''; ?>.</li>
                            <?php else: ?>
                                <li>Warranty depends on shop assessment.</li>
                            <?php endif; ?>

                            <?php if ($requiresDownPayment): ?>
                                <li>Down payment may be required after confirmation.</li>
                            <?php else: ?>
                                <li>Payment details will be confirmed after assessment.</li>
                            <?php endif; ?>

                            <li>Final cost may change depending on actual inspection and required parts.</li>
                        </ul>
                    <?php else: ?>
                        <p class="cp-label">Service appointment</p>
                        <p class="cp-muted mt-2 mb-4">Choose the service you want to request, select your registered vehicle, and provide your preferred appointment schedule.</p>
                    <?php endif; ?>

                    <p class="cp-label mb-3">What happens next</p>
                    <ol class="cp-steps-list mb-0">
                        <li>Your request will be reviewed by shop staff.</li>
                        <li>Final cost will be confirmed after assessment.</li>
                        <li>An approved request may be converted into an official job order.</li>
                        <li>Track its status under <a href="customer_service_requests.php">My service requests</a>.</li>
                    </ol>
                </div>
            </aside>

        </div>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>