<?php
class ServiceRequest {
    private $conn;
    private $table_name = "service_request";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function generateRequestNumber() {
        return 'SR-' . strtoupper(substr(uniqid(), -6));
    }

    public function getCustomerIdByUserId($user_id) {
        $query = "
            SELECT customer_id
            FROM customer
            WHERE user_id = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([intval($user_id)]);

        return $stmt->fetchColumn();
    }

    public function vehicleBelongsToCustomer($vehicle_id, $customer_id) {
        $query = "
            SELECT vehicle_id
            FROM vehicle
            WHERE vehicle_id = ?
              AND customer_id = ?
              AND status = 'Active'
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            intval($vehicle_id),
            intval($customer_id)
        ]);

        return $stmt->rowCount() > 0;
    }

    public function serviceIsActive($service_id) {
        $query = "
            SELECT service_id
            FROM service
            WHERE service_id = ?
              AND is_active = 1
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([intval($service_id)]);

        return $stmt->rowCount() > 0;
    }

    public function readCustomerVehicles($customer_id) {
        $query = "
            SELECT *
            FROM vehicle
            WHERE customer_id = ?
              AND status = 'Active'
            ORDER BY created_at DESC, vehicle_id DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([intval($customer_id)]);

        return $stmt;
    }

    public function readActiveServices() {
        $query = "
            SELECT *
            FROM service
            WHERE is_active = 1
            ORDER BY category ASC, service_name ASC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function readActiveMechanics() {
        $query = "
            SELECT user_id, first_name, last_name
            FROM user
            WHERE role = 'Head Mechanic'
              AND is_active = 1
            ORDER BY first_name ASC, last_name ASC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function createCustomerRequest($data) {
        if (!$this->vehicleBelongsToCustomer($data['vehicle_id'], $data['customer_id'])) {
            throw new Exception("Selected vehicle does not belong to your customer profile.");
        }

        if (!$this->serviceIsActive($data['service_id'])) {
            throw new Exception("Selected service is no longer available.");
        }

        $query = "
            INSERT INTO " . $this->table_name . " (
                request_number,
                customer_id,
                vehicle_id,
                service_id,
                concern_description,
                preferred_appointment_date,
                preferred_appointment_time,
                status,
                created_at
            ) VALUES (
                :request_number,
                :customer_id,
                :vehicle_id,
                :service_id,
                :concern_description,
                :preferred_appointment_date,
                :preferred_appointment_time,
                'Pending',
                NOW()
            )
        ";

        $stmt = $this->conn->prepare($query);

        $requestNumber = $this->generateRequestNumber();
        $customerId = intval($data['customer_id']);
        $vehicleId = intval($data['vehicle_id']);
        $serviceId = intval($data['service_id']);
        $concern = htmlspecialchars(strip_tags(trim($data['concern_description'])));
        $preferredDate = $data['preferred_appointment_date'];
        $preferredTime = !empty($data['preferred_appointment_time']) ? $data['preferred_appointment_time'] : null;

        $stmt->bindParam(':request_number', $requestNumber);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindParam(':vehicle_id', $vehicleId, PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
        $stmt->bindParam(':concern_description', $concern);
        $stmt->bindParam(':preferred_appointment_date', $preferredDate);
        $stmt->bindParam(':preferred_appointment_time', $preferredTime);

        if ($stmt->execute()) {
            return $requestNumber;
        }

        return false;
    }

    public function readByCustomer($customer_id) {
        $query = "
            SELECT
                sr.*,
                s.service_name,
                s.category AS service_category,
                s.base_price,
                v.plate_number,
                v.make,
                v.model,
                jo.job_order_number,
                CONCAT(m.first_name, ' ', m.last_name) AS assigned_mechanic_name
            FROM " . $this->table_name . " sr
            INNER JOIN service s ON sr.service_id = s.service_id
            INNER JOIN vehicle v ON sr.vehicle_id = v.vehicle_id
            LEFT JOIN job_order jo ON sr.converted_job_order_id = jo.job_order_id
            LEFT JOIN user m ON sr.assigned_mechanic_id = m.user_id
            WHERE sr.customer_id = ?
            ORDER BY sr.created_at DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([intval($customer_id)]);

        return $stmt;
    }

    public function readAll($status = 'Pending') {
        $params = [];

        $query = "
            SELECT
                sr.*,
                c.first_name,
                c.last_name,
                c.contact_number,
                c.email,
                s.service_name,
                s.category AS service_category,
                s.base_price,
                s.requires_down_payment,
                v.plate_number,
                v.make,
                v.model,
                jo.job_order_number,
                CONCAT(m.first_name, ' ', m.last_name) AS assigned_mechanic_name,
                CONCAT(r.first_name, ' ', r.last_name) AS reviewed_by_name
            FROM " . $this->table_name . " sr
            INNER JOIN customer c ON sr.customer_id = c.customer_id
            INNER JOIN service s ON sr.service_id = s.service_id
            INNER JOIN vehicle v ON sr.vehicle_id = v.vehicle_id
            LEFT JOIN job_order jo ON sr.converted_job_order_id = jo.job_order_id
            LEFT JOIN user m ON sr.assigned_mechanic_id = m.user_id
            LEFT JOIN user r ON sr.reviewed_by = r.user_id
        ";

        if ($status !== 'All') {
            $query .= " WHERE sr.status = ? ";
            $params[] = $status;
        }

        $query .= "
            ORDER BY
                FIELD(sr.status, 'Pending', 'Converted', 'Rejected', 'Cancelled'),
                sr.created_at DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        return $stmt;
    }

    public function readOneDetailed($service_request_id) {
        $query = "
            SELECT
                sr.*,
                c.first_name,
                c.last_name,
                c.contact_number,
                c.email,
                s.service_name,
                s.category AS service_category,
                s.base_price,
                s.requires_down_payment,
                v.plate_number,
                v.make,
                v.model
            FROM " . $this->table_name . " sr
            INNER JOIN customer c ON sr.customer_id = c.customer_id
            INNER JOIN service s ON sr.service_id = s.service_id
            INNER JOIN vehicle v ON sr.vehicle_id = v.vehicle_id
            WHERE sr.service_request_id = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([intval($service_request_id)]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function mechanicIsValid($mechanic_id) {
        $query = "
            SELECT user_id
            FROM user
            WHERE user_id = ?
              AND role = 'Head Mechanic'
              AND is_active = 1
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([intval($mechanic_id)]);

        return $stmt->rowCount() > 0;
    }

    private function formatPreferredAppointmentText($date, $time) {
        if (empty($date)) {
            return 'No preferred appointment date provided';
        }

        $formattedDate = date('M d, Y', strtotime($date));

        if (!empty($time)) {
            return $formattedDate . ' ' . date('h:i A', strtotime($time));
        }

        return $formattedDate . ' - No preferred time';
    }

    public function approveAndConvert($service_request_id, $estimated_cost, $assigned_mechanic_id, $reviewed_by, $expected_completion_date = null) {
        try {
            $this->conn->beginTransaction();

            $queryRequest = "
                SELECT
                    sr.*,
                    s.service_name,
                    s.requires_down_payment
                FROM " . $this->table_name . " sr
                INNER JOIN service s ON sr.service_id = s.service_id
                WHERE sr.service_request_id = ?
                FOR UPDATE
            ";

            $stmtRequest = $this->conn->prepare($queryRequest);
            $stmtRequest->execute([intval($service_request_id)]);
            $request = $stmtRequest->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new Exception("Service request not found.");
            }

            if ($request['status'] !== 'Pending') {
                throw new Exception("Only pending service requests can be approved.");
            }

            if (!$this->mechanicIsValid($assigned_mechanic_id)) {
                throw new Exception("Please assign a valid active mechanic.");
            }

            $joNumber = 'JO-' . strtoupper(substr(uniqid(), -5));
            $requiresDownPayment = intval($request['requires_down_payment']);
            $downPaymentAmount = $requiresDownPayment ? round(floatval($estimated_cost) * 0.50, 2) : 0;

            $preferredAppointment = $this->formatPreferredAppointmentText(
                $request['preferred_appointment_date'],
                $request['preferred_appointment_time']
            );

            /*
                Clear job order description:
                - Requested Service = selected service type
                - Customer Concern / Symptoms = specific issue described by customer
                These are intentionally separate because customers may choose a service,
                but the mechanic still needs the actual symptom/concern for diagnosis.
            */
            $description =
                "Request Source: Online Service Appointment\n\n" .
                "Requested Service: " . $request['service_name'] . "\n\n" .
                "Customer Concern / Symptoms: " . $request['concern_description'] . "\n\n" .
                "Preferred Appointment: " . $preferredAppointment;

            $queryJO = "
                INSERT INTO job_order (
                    vehicle_id,
                    customer_id,
                    created_by,
                    assigned_mechanic_id,
                    job_order_number,
                    description,
                    request_source,
                    status,
                    estimated_cost,
                    requires_down_payment,
                    down_payment_amount,
                    expected_completion_date,
                    date_created
                ) VALUES (
                    :vehicle_id,
                    :customer_id,
                    :created_by,
                    :assigned_mechanic_id,
                    :job_order_number,
                    :description,
                    'Online',
                    'Pending',
                    :estimated_cost,
                    :requires_down_payment,
                    :down_payment_amount,
                    :expected_completion_date,
                    NOW()
                )
            ";

            $stmtJO = $this->conn->prepare($queryJO);

            $stmtJO->execute([
                ':vehicle_id' => intval($request['vehicle_id']),
                ':customer_id' => intval($request['customer_id']),
                ':created_by' => intval($reviewed_by),
                ':assigned_mechanic_id' => intval($assigned_mechanic_id),
                ':job_order_number' => $joNumber,
                ':description' => htmlspecialchars(strip_tags($description)),
                ':estimated_cost' => floatval($estimated_cost),
                ':requires_down_payment' => $requiresDownPayment,
                ':down_payment_amount' => $downPaymentAmount,
                ':expected_completion_date' => !empty($expected_completion_date) ? $expected_completion_date : null
            ]);

            $jobOrderId = $this->conn->lastInsertId();

            $queryJobService = "
                INSERT INTO job_order_service (
                    job_order_id,
                    service_id,
                    quantity,
                    unit_price,
                    subtotal,
                    notes
                ) VALUES (
                    :job_order_id,
                    :service_id,
                    1,
                    :unit_price,
                    :subtotal,
                    :notes
                )
            ";

            $stmtJobService = $this->conn->prepare($queryJobService);
            $stmtJobService->execute([
                ':job_order_id' => intval($jobOrderId),
                ':service_id' => intval($request['service_id']),
                ':unit_price' => floatval($estimated_cost),
                ':subtotal' => floatval($estimated_cost),
                ':notes' => 'Created from online service request ' . $request['request_number']
            ]);

            $queryUpdate = "
                UPDATE " . $this->table_name . "
                SET
                    status = 'Converted',
                    estimated_cost = :estimated_cost,
                    assigned_mechanic_id = :assigned_mechanic_id,
                    expected_completion_date = :expected_completion_date,
                    reviewed_by = :reviewed_by,
                    reviewed_at = NOW(),
                    approved_at = NOW(),
                    converted_job_order_id = :converted_job_order_id
                WHERE service_request_id = :service_request_id
            ";

            $stmtUpdate = $this->conn->prepare($queryUpdate);
            $stmtUpdate->execute([
                ':estimated_cost' => floatval($estimated_cost),
                ':assigned_mechanic_id' => intval($assigned_mechanic_id),
                ':expected_completion_date' => !empty($expected_completion_date) ? $expected_completion_date : null,
                ':reviewed_by' => intval($reviewed_by),
                ':converted_job_order_id' => intval($jobOrderId),
                ':service_request_id' => intval($service_request_id)
            ]);

            $this->conn->commit();

            return [
                'job_order_id' => $jobOrderId,
                'job_order_number' => $joNumber
            ];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    public function reject($service_request_id, $reason, $reviewed_by) {
        $query = "
            UPDATE " . $this->table_name . "
            SET
                status = 'Rejected',
                rejection_reason = :rejection_reason,
                reviewed_by = :reviewed_by,
                reviewed_at = NOW(),
                rejected_at = NOW()
            WHERE service_request_id = :service_request_id
              AND status = 'Pending'
        ";

        $stmt = $this->conn->prepare($query);

        $cleanReason = htmlspecialchars(strip_tags(trim($reason)));
        $reviewedBy = intval($reviewed_by);
        $requestId = intval($service_request_id);

        $stmt->bindParam(':rejection_reason', $cleanReason);
        $stmt->bindParam(':reviewed_by', $reviewedBy, PDO::PARAM_INT);
        $stmt->bindParam(':service_request_id', $requestId, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    public function cancelByCustomer($service_request_id, $customer_id) {
        $query = "
            UPDATE " . $this->table_name . "
            SET
                status = 'Cancelled',
                cancelled_at = NOW()
            WHERE service_request_id = :service_request_id
              AND customer_id = :customer_id
              AND status = 'Pending'
        ";

        $stmt = $this->conn->prepare($query);

        $requestId = intval($service_request_id);
        $customerId = intval($customer_id);

        $stmt->bindParam(':service_request_id', $requestId, PDO::PARAM_INT);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }
}
?>