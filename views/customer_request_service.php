<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

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
        die("Customer profile not found.");
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

function displayText($value, $fallback = 'N/A')
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        return htmlspecialchars($fallback);
    }

    return htmlspecialchars($value);
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
<title>Request Service - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .request-service-page {
        width: 100%;
        max-width: 100%;
    }

    .request-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.35rem;
    }

    .request-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .request-header p {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .back-btn {
        min-height: 42px;
        border-radius: 12px;
        padding: 0.6rem 0.95rem;
        font-size: 0.86rem;
        font-weight: 900;
        white-space: nowrap;
        border: 1px solid rgba(17, 24, 39, 0.18);
        background: transparent;
        color: var(--dashboard-text-main);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none;
        transition: 0.2s ease;
    }

    .back-btn:hover {
        background: rgba(255, 255, 255, 0.65);
        color: var(--black);
        border-color: rgba(17, 24, 39, 0.25);
    }

    .request-layout {
        display: grid;
        grid-template-columns: minmax(0, 0.85fr) minmax(420px, 1.15fr);
        gap: 1.5rem;
        align-items: start;
    }

    .request-summary-panel,
    .request-form-panel {
        border: 1px solid rgba(17, 24, 39, 0.10);
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.20);
        padding: 1.2rem;
    }

    .selected-service-card {
        display: flex;
        gap: 1rem;
        align-items: center;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
        margin-bottom: 1rem;
    }

    .selected-service-image {
        width: 112px;
        height: 92px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .selected-service-image img {
        max-width: 100%;
        max-height: 88px;
        object-fit: contain;
        filter: drop-shadow(0 12px 14px rgba(17, 24, 39, 0.10));
    }

    .selected-service-name {
        color: var(--dashboard-text-main);
        font-size: 1.05rem;
        font-weight: 900;
        margin-bottom: 0.22rem;
        line-height: 1.25;
    }

    .selected-service-category {
        color: var(--dashboard-primary);
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        margin-bottom: 0.38rem;
    }

    .selected-service-price {
        color: #047857;
        font-size: 0.88rem;
        font-weight: 900;
    }

    .request-note {
        color: var(--dashboard-text-muted);
        font-size: 0.86rem;
        line-height: 1.55;
        margin-bottom: 0;
    }

    .request-note-list {
        margin: 1rem 0 0 0;
        padding-left: 1.05rem;
        color: var(--dashboard-text-muted);
    }

    .request-note-list li {
        color: var(--dashboard-text-muted);
        font-size: 0.8rem;
        line-height: 1.5;
        margin-bottom: 0.38rem;
    }

    .request-note-list li::marker {
        color: var(--dashboard-text-main);
    }

    .form-section-title {
        color: var(--dashboard-text-main);
        font-size: 1.05rem;
        font-weight: 900;
        margin-bottom: 0.85rem;
    }

    .form-label-custom {
        font-size: 0.76rem;
        font-weight: 900;
        color: var(--dashboard-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .form-control-custom {
        min-height: 44px;
        border: 1px solid rgba(17, 24, 39, 0.12);
        border-radius: 14px;
        padding: 0.65rem 0.85rem;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
        box-shadow: none !important;
        background: rgba(255, 255, 255, 0.56);
    }

    .form-control-custom:focus {
        border-color: rgba(245, 197, 24, 0.75);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.14) !important;
    }

    .form-alert {
        border-radius: 14px;
        font-size: 0.9rem;
    }

    .form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
        padding-top: 1rem;
        margin-top: 1rem;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
    }

    .submit-request-btn {
        min-height: 44px;
        border-radius: 999px;
        padding: 0.65rem 1.1rem;
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-size: 0.9rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: 0.2s ease;
    }

    .submit-request-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .cancel-btn {
        min-height: 44px;
        border-radius: 999px;
        padding: 0.65rem 1.1rem;
        border: 1px solid rgba(17, 24, 39, 0.16);
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.9rem;
        font-weight: 850;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .cancel-btn:hover {
        background: rgba(255, 255, 255, 0.65);
        color: var(--black);
    }

    .empty-state-card {
        background: rgba(255, 255, 255, 0.3);
        border: 1px solid rgba(17, 24, 39, 0.06);
        border-radius: 18px;
        padding: 2.5rem 1.5rem;
        text-align: center;
        color: var(--dashboard-text-muted);
    }

    .empty-state-card i {
        display: block;
        font-size: 1.8rem;
        color: var(--dashboard-primary);
        margin-bottom: 0.8rem;
    }

    @media (max-width: 991.98px) {
        .request-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .request-header {
            flex-direction: column;
            align-items: stretch;
        }

        .back-btn {
            width: 100%;
            justify-content: center;
        }

        .request-header h2 {
            font-size: 1.65rem;
        }

        .selected-service-card {
            align-items: flex-start;
        }

        .form-actions {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .submit-request-btn,
        .cancel-btn {
            width: 100%;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="request-service-page">

        <div class="request-header">
            <div>
                <h2>Request Service</h2>
                <p>Submit an online service appointment request for shop review.</p>
            </div>

            <a href="customer_services.php" class="back-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Services
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show form-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show form-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($dbError): ?>
            <div class="empty-state-card">
                <i class="bi bi-exclamation-circle"></i>
                <div class="fw-bold mb-2">Request form unavailable</div>
                <div><?php echo htmlspecialchars($dbError); ?></div>
            </div>
        <?php else: ?>
            <div class="request-layout">

                <aside class="request-summary-panel">
                    <?php if ($selectedService): ?>
                        <?php
                            $basePrice = floatval($selectedService['base_price'] ?? 0);
                            $warrantyDays = intval($selectedService['warranty_days'] ?? 0);
                            $requiresDownPayment = intval($selectedService['requires_down_payment'] ?? 0);
                        ?>

                        <div class="selected-service-card">
                            <div class="selected-service-image">
                                <img
                                    src="<?php echo serviceImagePath($selectedService['image'] ?? ''); ?>"
                                    alt="<?php echo displayText($selectedService['service_name'] ?? 'Service image'); ?>"
                                >
                            </div>

                            <div>
                                <div class="selected-service-name">
                                    <?php echo displayText($selectedService['service_name'] ?? ''); ?>
                                </div>

                                <div class="selected-service-category">
                                    <?php echo displayText($selectedService['category'] ?? ''); ?>
                                </div>

                                <div class="selected-service-price">
                                    <?php if ($basePrice > 0): ?>
                                        Starts at ₱<?php echo number_format($basePrice, 2); ?>
                                    <?php else: ?>
                                        Cost after assessment
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <p class="request-note">
                            <?php echo shortText($selectedService['description'] ?? ''); ?>
                        </p>

                        <ul class="request-note-list">
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
                        <div class="selected-service-card">
                            <div class="selected-service-image">
                                <img src="../assets/images/services/default.png" alt="Service">
                            </div>

                            <div>
                                <div class="selected-service-name">Select a Service</div>
                                <div class="selected-service-category">Service Appointment</div>
                                <div class="selected-service-price">Choose from active services</div>
                            </div>
                        </div>

                        <p class="request-note">
                            Choose the service you want to request, select your registered vehicle, and provide your preferred appointment schedule.
                        </p>

                        <ul class="request-note-list">
                            <li>Your request will be reviewed by shop staff.</li>
                            <li>Final cost will be confirmed after assessment.</li>
                            <li>An approved request may be converted into an official job order.</li>
                        </ul>
                    <?php endif; ?>
                </aside>

                <section class="request-form-panel">
                    <h5 class="form-section-title">Appointment Details</h5>

                    <?php if (empty($myVehicles)): ?>
                        <div class="alert alert-warning form-alert mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Please add a registered vehicle first before requesting a service appointment.
                            You can add your vehicle from your customer dashboard.
                        </div>
                    <?php elseif (empty($activeServices)): ?>
                        <div class="alert alert-warning form-alert mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            No active services are available right now. Please contact the shop.
                        </div>
                    <?php else: ?>
                        <form action="../controllers/ServiceRequestController.php" method="POST">
                            <input type="hidden" name="action" value="submit_customer_request">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Vehicle <span class="text-danger">*</span></label>
                                    <select name="vehicle_id" class="form-select form-control-custom" required>
                                        <option value="">Select vehicle</option>
                                        <?php foreach ($myVehicles as $vehicle): ?>
                                            <option value="<?php echo intval($vehicle['vehicle_id']); ?>">
                                                <?php echo htmlspecialchars($vehicle['plate_number'] . ' - ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label-custom">Service <span class="text-danger">*</span></label>
                                    <select name="service_id" class="form-select form-control-custom" required>
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

                                <div class="col-md-6">
                                    <label class="form-label-custom">Preferred Date <span class="text-danger">*</span></label>
                                    <input
                                        type="date"
                                        name="preferred_appointment_date"
                                        class="form-control form-control-custom"
                                        min="<?php echo date('Y-m-d'); ?>"
                                        required
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label-custom">Preferred Time</label>
                                    <input
                                        type="time"
                                        name="preferred_appointment_time"
                                        class="form-control form-control-custom"
                                    >
                                </div>

                                <div class="col-12">
                                    <label class="form-label-custom">Concern / Symptoms / Additional Notes <span class="text-danger">*</span></label>
                                    <textarea
                                        name="concern_description"
                                        class="form-control form-control-custom"
                                        rows="5"
                                        placeholder="Describe the vehicle concern or symptoms. Example: leaking oil, hard starting, unusual noise, weak brakes."
                                        required
                                    ></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="customer_services.php" class="cancel-btn">
                                    Cancel
                                </a>

                                <button type="submit" class="submit-request-btn">
                                    <i class="bi bi-send"></i>
                                    Submit Request
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

            </div>
        <?php endif; ?>

    </div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>