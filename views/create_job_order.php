<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Head Mechanic' || $_SESSION['role'] === 'Customer') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$stmtV = $db->prepare("
    SELECT 
        v.*, 
        c.first_name, 
        c.last_name 
    FROM vehicle v 
    JOIN customer c ON v.customer_id = c.customer_id 
    ORDER BY v.plate_number ASC
");
$stmtV->execute();
$vehicles = $stmtV->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Job Order - Norily's Repair Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .create-job-page {
            width: 100%;
            max-width: 980px;
        }

        .create-job-header {
            margin-bottom: 1.8rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--dashboard-text-muted);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 1.1rem;
            transition: 0.2s ease;
        }

        .back-link:hover {
            color: var(--black);
            transform: translateX(-3px);
        }

        .create-job-title {
            font-size: 2.45rem;
            font-weight: 900;
            color: var(--dashboard-text-main);
            margin-bottom: 0.3rem;
            line-height: 1.1;
        }

        .create-job-subtitle {
            color: var(--dashboard-text-muted);
            font-size: 1rem;
            margin-bottom: 0;
        }

        .job-form-area {
            padding: 0;
            max-width: 900px;
        }

        .form-section-title {
            font-size: 0.95rem;
            font-weight: 900;
            color: var(--dashboard-primary);
            margin-bottom: 1.4rem;
            padding-bottom: 0.9rem;
            border-bottom: 1px solid #dfe3e8;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 1.7rem;
            row-gap: 1.35rem;
        }

        .form-field {
            min-width: 0;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .job-label {
            display: block;
            font-size: 0.76rem;
            font-weight: 800;
            color: var(--dashboard-text-muted);
            margin-bottom: 0.35rem;
        }

        .job-control {
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

        .job-control:focus {
            border-color: var(--dashboard-primary);
            outline: none;
            box-shadow: none;
            background: transparent;
        }

        select.job-control {
            cursor: pointer;
        }

        textarea.job-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }

        .form-helper {
            color: var(--dashboard-text-muted);
            font-size: 0.78rem;
            margin-top: 0.35rem;
        }

        .empty-vehicle-note {
            grid-column: 1 / -1;
            border: 1px solid #fde68a;
            background: #fffbeb;
            color: #92400e;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            font-size: 0.88rem;
            font-weight: 700;
        }

        .minimal-form-footer {
            margin-top: 2rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .cancel-btn {
            border: 1px solid #e5e7eb;
            background: transparent;
            color: var(--dashboard-text-main);
            border-radius: 999px;
            padding: 0.65rem 1.15rem;
            font-size: 0.9rem;
            font-weight: 800;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .cancel-btn:hover {
            background: #f8fafc;
            color: var(--black);
        }

        .save-btn {
            border: 1px solid var(--dashboard-primary);
            background: var(--dashboard-primary);
            color: var(--black);
            border-radius: 999px;
            padding: 0.65rem 1.35rem;
            font-size: 0.9rem;
            font-weight: 900;
            transition: 0.2s ease;
            box-shadow: 0 8px 18px rgba(245, 197, 24, 0.22);
        }

        .save-btn:hover {
            background: var(--black);
            border-color: var(--black);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: none;
        }

        .save-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 767.98px) {
            .create-job-title {
                font-size: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .minimal-form-footer {
                align-items: stretch;
                flex-direction: column-reverse;
            }

            .cancel-btn,
            .save-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="create-job-page">

            <div class="create-job-header">
                <a href="job_orders.php" class="back-link">
                    <i class="bi bi-arrow-left"></i>
                    Back to Job Orders
                </a>

                <h1 class="create-job-title">Create New Job Order</h1>
                <p class="create-job-subtitle">
                    Register a new vehicle repair task while keeping customer and vehicle records connected.
                </p>
            </div>

            <form action="../controllers/JobOrderController.php" method="POST" class="job-form-area">
                <input type="hidden" name="action" value="create">

                <div class="form-section-title">Basic Job Details</div>

                <div class="form-grid">

                    <?php if (count($vehicles) === 0): ?>
                        <div class="empty-vehicle-note">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No registered vehicles found. Please add a customer vehicle first before creating a job order.
                        </div>
                    <?php endif; ?>

                    <div class="form-field full-width">
                        <label class="job-label">Vehicle & Customer</label>
                        <select name="vehicle_id" class="job-control" required <?php echo count($vehicles) === 0 ? 'disabled' : ''; ?>>
                            <option value="" disabled selected>Select vehicle by plate number</option>

                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?php echo intval($v['vehicle_id']); ?>">
                                    <?php
                                        echo htmlspecialchars(
                                            $v['plate_number'] . ' - ' .
                                            $v['make'] . ' ' .
                                            $v['model'] . ' (' .
                                            $v['first_name'] . ' ' .
                                            $v['last_name'] . ')'
                                        );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-helper">
                            Choose the vehicle that will be assigned to this job order.
                        </div>
                    </div>

                    <div class="form-field full-width">
                        <label class="job-label">Repair Description</label>
                        <textarea
                            name="description"
                            class="job-control"
                            placeholder="Describe the issue, symptoms, diagnosis, or requested repair..."
                            required
                        ></textarea>
                        <div class="form-helper">
                            This will help mechanics and billing staff understand the repair scope.
                        </div>
                    </div>

                    <div class="form-field">
                        <label class="job-label">Estimated Cost (₱)</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="estimated_cost"
                            class="job-control"
                            placeholder="0.00"
                            required
                        >
                        <div class="form-helper">
                            Initial amount for the repair job.
                        </div>
                    </div>

                </div>

                <div class="minimal-form-footer">
                    <a href="job_orders.php" class="cancel-btn">
                        Cancel
                    </a>

                    <button type="submit" class="save-btn" <?php echo count($vehicles) === 0 ? 'disabled' : ''; ?>>
                        Save Job Order
                    </button>
                </div>
            </form>

        </div>
    </main>
</div>

</body>
</html>