-- =====================================================================
-- DASPMS demo data
-- Replaces the made-up test records with realistic ones that show every
-- feature (each job / order / request status, down payments, an overdue
-- bill, a voided POS sale, low stock, an expiring reservation).
--
-- Dates are relative to the moment the script runs, so run it again right
-- before a demo and "today", "this week", overdue and expiring items stay
-- correct.   Import:  mysql -u root daspms < database/seeds/demo_data.sql
--
-- Kept as is: staff logins (renamed only), the team's own accounts
-- (customers 14-16, users 15-16), services, part photos, supplier emails.
-- Test logins (password123): owner@norilys.com, cashier@norilys.com,
-- mechanic@norilys.com, customer@norilys.com (Juan Dela Cruz),
-- maria.santos@example.com, ana.reyes@example.com
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- ---------- 1. Remove the old test records ----------
DELETE FROM activity_log;
DELETE FROM cart_item;
DELETE FROM payment;
DELETE FROM pos_item;
DELETE FROM pos_transaction;
DELETE FROM payment_transaction;
DELETE FROM payment_webhook_event;
DELETE FROM invoice;
DELETE FROM part_order_item;
DELETE FROM part_order;
DELETE FROM job_order_part;
DELETE FROM job_order_service;
DELETE FROM service_request;
DELETE FROM job_order;
DELETE FROM stock_in;
DELETE FROM password_reset;
DELETE FROM rate_limit;
DELETE FROM vehicle;
DELETE FROM customer WHERE customer_id NOT IN (1, 14, 15, 16);
DELETE FROM user WHERE user_id IN (5, 6);       -- old made-up customer accounts
DELETE FROM part WHERE part_id = 2;            -- "yig" test part (its photos go with it)

-- ---------- 2. Staff and customer logins ----------
-- Created when missing (fresh install from daspms.sql only has the Owner); existing accounts
-- keep their username, email and password and only get the demo names.
SET @demo_hash = '$2y$10$369wt6VzazjJ3mborJzz6.bg3EfRtOCAwJt0rkKUb7ndbSzBfbywy';  -- password123
INSERT INTO user (user_id, first_name, last_name, username, password_hash, role, is_active, is_verified, email_verified_at, created_at, email) VALUES
(1,  'Ernesto', 'Valdez',    'owner',                    @demo_hash, 'Owner',         1, 1, NOW() - INTERVAL 90 DAY, NOW() - INTERVAL 90 DAY, 'owner@norilys.com'),
(2,  'Joy',     'Mercado',   'cashier',                  @demo_hash, 'Cashier',       1, 1, NOW() - INTERVAL 90 DAY, NOW() - INTERVAL 90 DAY, 'cashier@norilys.com'),
(3,  'Rodel',   'Pascual',   'mechanic',                 @demo_hash, 'Head Mechanic', 1, 1, NOW() - INTERVAL 90 DAY, NOW() - INTERVAL 90 DAY, 'mechanic@norilys.com'),
(4,  'Juan',    'Dela Cruz', 'customer',                 @demo_hash, 'Customer',      1, 1, NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 60 DAY, 'customer@norilys.com'),
(20, 'Maria',   'Santos',    'maria.santos@example.com', @demo_hash, 'Customer',      1, 1, NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 45 DAY, 'maria.santos@example.com'),
(21, 'Ana',     'Reyes',     'ana.reyes@example.com',    @demo_hash, 'Customer',      1, 1, NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 40 DAY, 'ana.reyes@example.com')
ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), is_active = 1;

INSERT INTO customer (customer_id, first_name, last_name, email, contact_number, address, created_at, user_id, status)
VALUES (1, 'Juan', 'Dela Cruz', 'customer@norilys.com', '09171234567', 'Purok 3, Brgy. Poblacion East, Rosario, La Union', NOW() - INTERVAL 60 DAY, 4, 'Active')
ON DUPLICATE KEY UPDATE first_name = 'Juan', middle_name = NULL, last_name = 'Dela Cruz', contact_number = '09171234567',
    address = VALUES(address), status = 'Active', credit_balance = 0, credit_due_date = NULL, created_at = VALUES(created_at), user_id = 4;

-- ---------- 3. Customers ----------
INSERT INTO customer (customer_id, first_name, last_name, email, contact_number, address, created_at, user_id, status) VALUES
(20, 'Maria',  'Santos',     'maria.santos@example.com', '09181112233', 'Brgy. Bani, Rosario, La Union',          NOW() - INTERVAL 45 DAY, 20, 'Active'),
(21, 'Roberto','Garcia',     NULL,                       '09192223344', 'Brgy. San Nicolas Norte, Agoo, La Union', NOW() - INTERVAL 50 DAY, NULL, 'Active'),
(22, 'Ana',    'Reyes',      'ana.reyes@example.com',    '09203334455', 'Brgy. Poblacion, Sto. Tomas, La Union',   NOW() - INTERVAL 40 DAY, 21, 'Active'),
(23, 'Carlo',  'Mendoza',    NULL,                       '09214445566', 'Brgy. Ambangonan, Pugo, La Union',        NOW() - INTERVAL 30 DAY, NULL, 'Active'),
(24, 'Liza',   'Fernandez',  NULL,                       '09225556677', 'Brgy. Damortis, Sto. Tomas, La Union',    NOW() - INTERVAL 28 DAY, NULL, 'Active'),
(25, 'Ramon',  'Villanueva', NULL,                       '09236667788', 'Brgy. Lloren, Tubao, La Union',           NOW() - INTERVAL 20 DAY, NULL, 'Active'),
(26, 'Grace',  'Bautista',   NULL,                       '09247778899', 'Brgy. Camp One, Rosario, La Union',       NOW() - INTERVAL 10 DAY, NULL, 'Active');

-- ---------- 4. Vehicles ----------
INSERT INTO vehicle (vehicle_id, customer_id, plate_number, make, model, year, color, status, created_at) VALUES
(20, 1,  'ABC 1234', 'Toyota',     'Vios',          2018, 'Silver', 'Active', NOW() - INTERVAL 60 DAY),
(21, 1,  'NBC 5821', 'Mitsubishi', 'L300',          2015, 'White',  'Active', NOW() - INTERVAL 59 DAY),
(22, 20, 'DAE 7743', 'Honda',      'City',          2020, 'Red',    'Active', NOW() - INTERVAL 45 DAY),
(23, 21, 'CAR 4412', 'Isuzu',      'D-Max',         2019, 'Black',  'Active', NOW() - INTERVAL 50 DAY),
(24, 22, 'AAQ 9087', 'Toyota',     'Innova',        2017, 'Gray',   'Active', NOW() - INTERVAL 40 DAY),
(25, 23, 'NEF 3390', 'Mitsubishi', 'Montero Sport', 2021, 'White',  'Active', NOW() - INTERVAL 30 DAY),
(26, 24, 'ZTR 6614', 'Suzuki',     'Ertiga',        2022, 'Blue',   'Active', NOW() - INTERVAL 28 DAY),
(27, 25, 'RNC 2208', 'Toyota',     'Hilux',         2016, 'Gray',   'Active', NOW() - INTERVAL 20 DAY),
(28, 26, 'WQL 5176', 'Mitsubishi', 'Mirage G4',     2019, 'Red',    'Active', NOW() - INTERVAL 10 DAY),
(29, 21, 'UKO 8830', 'Nissan',     'Navara',        2018, 'Orange', 'Active', NOW() - INTERVAL 49 DAY);

-- ---------- 5. Suppliers (names only; the emails stay the team's) ----------
UPDATE supplier SET supplier_name = 'Ilocos Auto Electrical Supply', contact_person = 'Lorna Agustin', phone = '09171230001', address = 'San Fernando City, La Union', is_active = 1 WHERE supplier_id = 1;
UPDATE supplier SET supplier_name = 'Northern Luzon Motor Parts',    contact_person = 'Danilo Cruz',   phone = '09281230002', address = 'Baguio City, Benguet',        is_active = 1 WHERE supplier_id = 2;
UPDATE supplier SET supplier_name = 'La Union Auto Parts Trading',   contact_person = 'Celia Ramos',   phone = '09391230003', address = 'Agoo, La Union',              is_active = 1 WHERE supplier_id = 3;

-- ---------- 6. Parts: complete details, stock after the sales below ----------
UPDATE part SET brand = 'Generic',
    specification = 'Motor-start / run capacitor, 12V DC',
    compatibility = 'Starter, alternator and blower motor repairs, most vehicles',
    unit = 'piece', quantity_on_hand = 12, low_stock_threshold = 4, supplier_id = 3, primary_supplier_id = 3, is_active = 1
WHERE part_id = 3;
UPDATE part SET brand = 'Generic',
    specification = '12V DC rocker / toggle switch, 2-pin or 3-pin',
    compatibility = 'Lights, horn, fog lamps and accessories, most vehicles',
    unit = 'piece', quantity_on_hand = 15, low_stock_threshold = 5, supplier_id = 3, primary_supplier_id = 3, is_active = 1
WHERE part_id = 4;
UPDATE part SET brand = 'Generic',
    specification = 'Enamel-coated copper wire, sold by gauge (SWG)',
    compatibility = 'Rewinding of starters, alternators and electric motors',
    unit = 'roll', quantity_on_hand = 3, low_stock_threshold = 4, supplier_id = 1, primary_supplier_id = 1, is_active = 1
WHERE part_id = 5;
UPDATE part SET brand = 'Generic',
    specification = 'Replacement starter armature, sized per starter model',
    compatibility = 'Toyota, Mitsubishi, Isuzu and similar starter motors; confirm the model at the shop',
    full_description = CONCAT(TRIM(TRAILING '.' FROM full_description), '. Bring the old armature or the vehicle model so the shop can match the size.'),
    unit = 'piece', quantity_on_hand = 2, low_stock_threshold = 3, supplier_id = 1, primary_supplier_id = 1, is_active = 1
WHERE part_id = 6;
UPDATE part SET quantity_on_hand = 6, low_stock_threshold = 2, supplier_id = 2, primary_supplier_id = 2, is_active = 1
WHERE part_id = 7;

INSERT INTO stock_in (part_id, recorded_by, quantity_added, cost_per_unit, supplier_name, supplier_document_ref, date_received, notes) VALUES
(6, 2, 5,  100.00,  'Ilocos Auto Electrical Supply', 'DR-1042', CURDATE() - INTERVAL 25 DAY, 'Regular restock'),
(3, 2, 10, 300.00,  'La Union Auto Parts Trading',   'SI-2207', CURDATE() - INTERVAL 21 DAY, NULL),
(7, 1, 4,  1295.00, 'Northern Luzon Motor Parts',    'DR-3381', CURDATE() - INTERVAL 16 DAY, 'Two pairs reserved for Honda Civic jobs'),
(5, 2, 3,  150.00,  'Ilocos Auto Electrical Supply', 'DR-1067', CURDATE() - INTERVAL 11 DAY, NULL),
(4, 2, 12, 100.00,  'La Union Auto Parts Trading',   'SI-2231', CURDATE() - INTERVAL 8 DAY,  NULL);

-- ---------- 7. Job orders (one of every status) ----------
INSERT INTO job_order (job_order_id, vehicle_id, customer_id, created_by, assigned_mechanic_id, job_order_number, description, request_source, status,
                       estimated_cost, final_cost, date_created, expected_completion_date, date_completed, completed_by,
                       cancellation_reason, cancelled_at, cancelled_by, requires_down_payment, down_payment_amount) VALUES
-- completed and paid
(20, 20, 1,  2, 3, 'JO-7A1C4', 'Starter will not crank. Replace starter armature and clean the starter contacts.', 'Walk-in', 'Completed',
     1500, 1750, TIMESTAMP(CURDATE() - INTERVAL 24 DAY, '09:05:00'), CURDATE() - INTERVAL 22 DAY, TIMESTAMP(CURDATE() - INTERVAL 22 DAY, '15:30:00'), 2, NULL, NULL, NULL, 0, 0),
(21, 22, 20, 2, 3, 'JO-B42E9', 'Battery not charging. Check the alternator and replace the faulty capacitor.', 'Walk-in', 'Completed',
     900, 1400, TIMESTAMP(CURDATE() - INTERVAL 19 DAY, '10:20:00'), CURDATE() - INTERVAL 17 DAY, TIMESTAMP(CURDATE() - INTERVAL 17 DAY, '14:00:00'), 2, NULL, NULL, NULL, 0, 0),
-- completed, not paid, past the 7-day credit period (overdue)
(22, 23, 21, 2, 3, 'JO-C9D17', 'Radiator leak and overheating. Repair the radiator core and replace the hoses.', 'Walk-in', 'Completed',
     3000, 0, TIMESTAMP(CURDATE() - INTERVAL 15 DAY, '08:40:00'), CURDATE() - INTERVAL 12 DAY, TIMESTAMP(CURDATE() - INTERVAL 12 DAY, '16:00:00'), 2, NULL, NULL, NULL, 0, 0),
-- completed with a down payment, balance still due (not yet overdue)
(23, 24, 22, 1, 3, 'JO-D3F58', 'Engine overhaul: low compression and heavy oil consumption.', 'Walk-in', 'Completed',
     8500, 0, TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '10:00:00'), CURDATE() - INTERVAL 4 DAY, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '17:00:00'), 1, NULL, NULL, NULL, 1, 4250),
-- ready for pickup (came from an online service request)
(24, 25, 23, 2, 3, 'JO-E61A2', 'Engine does not crank in the morning. Starter repair with armature replacement.', 'Online', 'Ready for Pickup',
     1500, 1750, TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '09:10:00'), CURDATE() - INTERVAL 2 DAY, NULL, NULL, NULL, NULL, NULL, 0, 0),
-- in progress, down payment paid
(25, 26, 24, 2, 3, 'JO-F7B30', 'Alternator rewinding. Battery light on while driving.', 'Walk-in', 'In Progress',
     1200, 1500, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '09:00:00'), CURDATE() + INTERVAL 1 DAY, NULL, NULL, NULL, NULL, NULL, 1, 600),
-- in progress (customer login: Juan)
(26, 21, 1,  2, 3, 'JO-A88D6', 'Alternator check: dim headlights and weak battery.', 'Walk-in', 'In Progress',
     900, 0, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '13:00:00'), CURDATE() + INTERVAL 1 DAY, NULL, NULL, NULL, NULL, NULL, 0, 0),
-- pending, no mechanic yet
(27, 27, 25, 2, NULL, 'JO-B19F4', 'Radiator overheating on long drives. Pressure test and repair.', 'Walk-in', 'Pending',
     3000, 0, NOW() - INTERVAL 3 HOUR, CURDATE() + INTERVAL 3 DAY, NULL, NULL, NULL, NULL, NULL, 0, 0),
-- pending, down payment required but not paid yet (online)
(28, 28, 26, 2, 3, 'JO-C50E7', 'Starter repair: clicking sound when starting.', 'Online', 'Pending',
     1500, 0, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '08:30:00'), CURDATE() + INTERVAL 2 DAY, NULL, NULL, NULL, NULL, NULL, 1, 750),
-- cancelled with a reason
(29, 29, 21, 2, 3, 'JO-D27B1', 'Engine knocking check.', 'Walk-in', 'Cancelled',
     2500, 0, TIMESTAMP(CURDATE() - INTERVAL 9 DAY, '11:00:00'), CURDATE() - INTERVAL 6 DAY, NULL, NULL,
     'Customer decided to bring the vehicle to the dealer instead.', TIMESTAMP(CURDATE() - INTERVAL 8 DAY, '09:15:00'), 2, 0, 0),
-- completed and paid today
(30, 22, 20, 2, 3, 'JO-E04C9', 'Replace worn outer tie rod ends (front) and check the wheel alignment.', 'Walk-in', 'Completed',
     800, 2650, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '10:00:00'), CURDATE(), NOW() - INTERVAL 2 HOUR, 2, NULL, NULL, NULL, 0, 0);

INSERT INTO job_order_service (job_order_id, service_id, quantity, unit_price, subtotal) VALUES
(20, 2, 1, 1500, 1500), (21, 1, 1, 900, 900), (22, 3, 1, 3000, 3000), (23, 4, 1, 8500, 8500),
(24, 2, 1, 1500, 1500), (25, 1, 1, 1200, 1200), (26, 1, 1, 900, 900), (27, 3, 1, 3000, 3000),
(28, 2, 1, 1500, 1500), (29, 4, 1, 2500, 2500);

INSERT INTO job_order_part (job_order_id, part_id, quantity_used, unit_price_at_use, subtotal, used_by, used_at, notes) VALUES
(20, 6, 1, 250,  250,  3, TIMESTAMP(CURDATE() - INTERVAL 23 DAY, '10:30:00'), NULL),
(21, 3, 1, 500,  500,  3, TIMESTAMP(CURDATE() - INTERVAL 18 DAY, '11:00:00'), NULL),
(24, 6, 1, 250,  250,  3, TIMESTAMP(CURDATE() - INTERVAL 4 DAY,  '14:10:00'), 'Old armature had worn commutator'),
(25, 5, 1, 300,  300,  3, TIMESTAMP(CURDATE() - INTERVAL 1 DAY,  '10:45:00'), 'For rewinding'),
(30, 7, 1, 1850, 1850, 3, TIMESTAMP(CURDATE() - INTERVAL 1 DAY,  '14:30:00'), 'Front left and right');

-- ---------- 8. Online service requests ----------
INSERT INTO service_request (service_request_id, request_number, customer_id, vehicle_id, service_id, concern_description,
                             preferred_appointment_date, preferred_appointment_time, status, estimated_cost, assigned_mechanic_id,
                             expected_completion_date, reviewed_by, reviewed_at, approved_at, rejected_at, rejection_reason,
                             converted_job_order_id, created_at) VALUES
(20, 'SR-3F8A21', 23, 25, 2, 'Engine does not crank in the morning; there is a clicking sound when starting.',
     CURDATE() - INTERVAL 5 DAY, '09:00:00', 'Converted', 1500, 3, CURDATE() - INTERVAL 2 DAY, 2,
     TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '08:30:00'), TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '08:30:00'), NULL, NULL, 24,
     TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '07:45:00')),
(21, 'SR-4B2C90', 26, 28, 2, 'Clicking sound when I turn the key, sometimes it starts on the third try.',
     CURDATE() - INTERVAL 1 DAY, '08:00:00', 'Converted', 1500, 3, CURDATE() + INTERVAL 2 DAY, 2,
     TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '08:20:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '08:20:00'), NULL, NULL, 28,
     TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '21:10:00')),
(22, 'SR-5D7E13', 1, 20, 3, 'Engine temperature goes up in traffic and the coolant level keeps dropping.',
     CURDATE() + INTERVAL 3 DAY, '10:00:00', 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
     NOW() - INTERVAL 5 HOUR),
(23, 'SR-6A9F42', 20, 22, 1, 'Battery light turns on while driving and the battery drains overnight.',
     CURDATE() + INTERVAL 1 DAY, '13:00:00', 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
     NOW() - INTERVAL 2 HOUR),
(24, 'SR-7C1B58', 22, 24, 1, 'Headlights flicker at night.',
     CURDATE() - INTERVAL 7 DAY, '15:00:00', 'Rejected', NULL, NULL, NULL, 2,
     TIMESTAMP(CURDATE() - INTERVAL 8 DAY, '09:00:00'), NULL, TIMESTAMP(CURDATE() - INTERVAL 8 DAY, '09:00:00'),
     'The selected date is fully booked. Please send a new request with another date.', NULL,
     TIMESTAMP(CURDATE() - INTERVAL 9 DAY, '19:30:00'));

-- ---------- 9. Web orders (parts reserved online) ----------
INSERT INTO part_order (order_id, customer_id, order_date, preferred_pickup_date, preferred_pickup_time, pickup_notes, payment_method,
                        gcash_reference, gcash_payment_amount, gcash_verification_status, gcash_verified_at, gcash_verified_by,
                        total_amount, status, cancellation_reason, cancelled_at, cancelled_by) VALUES
(20, 1,  TIMESTAMP(CURDATE() - INTERVAL 15 DAY, '20:10:00'), CURDATE() - INTERVAL 13 DAY, '10:00:00', NULL, 'Cash on Pickup',
     NULL, NULL, 'Not Required', NULL, NULL, 400, 'Completed', NULL, NULL, NULL),
(21, 20, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '19:00:00'), CURDATE() + INTERVAL 1 DAY, '14:00:00', 'Will pick up after work.', 'Cash on Pickup',
     NULL, NULL, 'Not Required', NULL, NULL, 500, 'Ready for Pickup', NULL, NULL, NULL),
(22, 22, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '18:00:00'), CURDATE(), '16:00:00', NULL, 'Cash on Pickup',
     NULL, NULL, 'Not Required', NULL, NULL, 1850, 'Ready for Pickup', NULL, NULL, NULL),
(23, 1,  NOW() - INTERVAL 1 HOUR, CURDATE() + INTERVAL 2 DAY, '10:00:00', NULL, 'Cash on Pickup',
     NULL, NULL, 'Not Required', NULL, NULL, 300, 'Pending', NULL, NULL, NULL),
(24, 20, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '08:00:00'), CURDATE() + INTERVAL 1 DAY, '09:00:00', NULL, 'Cash on Pickup',
     NULL, NULL, 'Not Required', NULL, NULL, 400, 'Approved', NULL, NULL, NULL),
(25, 22, TIMESTAMP(CURDATE() - INTERVAL 7 DAY, '12:00:00'), CURDATE() - INTERVAL 5 DAY, '11:00:00', NULL, 'Cash on Pickup',
     NULL, NULL, 'Not Required', NULL, NULL, 1000, 'Cancelled', 'Customer found the part elsewhere.', TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '10:00:00'), 2),
(26, 1,  TIMESTAMP(CURDATE() - INTERVAL 20 DAY, '21:00:00'), CURDATE() - INTERVAL 18 DAY, '15:00:00', NULL, 'GCash Full Payment',
     'GC-3318045', 250, 'Verified', TIMESTAMP(CURDATE() - INTERVAL 19 DAY, '09:00:00'), 2, 250, 'Completed', NULL, NULL, NULL);

INSERT INTO part_order_item (order_id, part_id, quantity, unit_price) VALUES
(20, 4, 2, 200), (21, 3, 1, 500), (22, 7, 1, 1850), (23, 5, 1, 300), (24, 4, 2, 200), (25, 3, 2, 500), (26, 6, 1, 250);

-- ---------- 10. Invoices ----------
INSERT INTO invoice (invoice_id, customer_id, job_order_id, part_order_id, created_by, invoice_number, invoice_type,
                     subtotal_services, subtotal_parts, total_amount, amount_paid, balance_due, payment_status, date_issued, due_date) VALUES
(20, 1,  20, NULL, 2, CONCAT('INV-JOB-',  DATE_FORMAT(TIMESTAMP(CURDATE() - INTERVAL 22 DAY, '15:35:00'), '%Y%m%d-%H%i%s'), '-20'), 'Service', 1500, 250,  1750, 1750, 0,    'Paid',     TIMESTAMP(CURDATE() - INTERVAL 22 DAY, '15:35:00'), CURDATE() - INTERVAL 15 DAY),
(21, 20, 21, NULL, 2, CONCAT('INV-JOB-',  DATE_FORMAT(TIMESTAMP(CURDATE() - INTERVAL 17 DAY, '14:05:00'), '%Y%m%d-%H%i%s'), '-21'), 'Service', 900,  500,  1400, 1400, 0,    'Paid',     TIMESTAMP(CURDATE() - INTERVAL 17 DAY, '14:05:00'), CURDATE() - INTERVAL 10 DAY),
(22, 21, 22, NULL, 2, CONCAT('INV-JOB-',  DATE_FORMAT(TIMESTAMP(CURDATE() - INTERVAL 12 DAY, '16:10:00'), '%Y%m%d-%H%i%s'), '-22'), 'Service', 3000, 0,    3000, 0,    3000, 'Not Paid', TIMESTAMP(CURDATE() - INTERVAL 12 DAY, '16:10:00'), CURDATE() - INTERVAL 5 DAY),
(23, 22, 23, NULL, 1, CONCAT('INV-JOB-',  DATE_FORMAT(TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '10:15:00'), '%Y%m%d-%H%i%s'), '-23'), 'Service', 8500, 0,    8500, 4250, 4250, 'Partial',  TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '10:15:00'), CURDATE() + INTERVAL 4 DAY),
(24, 24, 25, NULL, 2, CONCAT('INV-JOB-',  DATE_FORMAT(TIMESTAMP(CURDATE() - INTERVAL 2 DAY,  '09:35:00'), '%Y%m%d-%H%i%s'), '-25'), 'Service', 1200, 300,  1500, 600,  900,  'Partial',  TIMESTAMP(CURDATE() - INTERVAL 2 DAY,  '09:35:00'), NULL),
(25, 20, 30, NULL, 2, CONCAT('INV-JOB-',  DATE_FORMAT(NOW() - INTERVAL 115 MINUTE, '%Y%m%d-%H%i%s'), '-30'),                     'Service', 800,  1850, 2650, 2650, 0,    'Paid',     NOW() - INTERVAL 115 MINUTE,                       CURDATE() + INTERVAL 7 DAY),
(26, 1,  NULL, 20,  2, CONCAT('INV-PART-', DATE_FORMAT(TIMESTAMP(CURDATE() - INTERVAL 14 DAY, '09:00:00'), '%Y%m%d-%H%i%s'), '-20'), 'Part',    0,    400,  400,  400,  0,    'Paid',     TIMESTAMP(CURDATE() - INTERVAL 14 DAY, '09:00:00'), NULL),
(27, 20, NULL, 21,  2, CONCAT('INV-PART-', DATE_FORMAT(NOW() - INTERVAL 1 DAY, '%Y%m%d-%H%i%s'), '-21'),                         'Part',    0,    500,  500,  0,    500,  'Not Paid', NOW() - INTERVAL 1 DAY,                            NULL),
-- issued 62 hours ago: the 3-day hold ends in about 10 hours, so it shows under "Reservations expiring"
(28, 22, NULL, 22,  2, CONCAT('INV-PART-', DATE_FORMAT(NOW() - INTERVAL 62 HOUR, '%Y%m%d-%H%i%s'), '-22'),                       'Part',    0,    1850, 1850, 0,    1850, 'Not Paid', NOW() - INTERVAL 62 HOUR,                          NULL),
(29, 1,  NULL, 26,  2, CONCAT('INV-PART-', DATE_FORMAT(TIMESTAMP(CURDATE() - INTERVAL 19 DAY, '09:05:00'), '%Y%m%d-%H%i%s'), '-26'), 'Part',    0,    250,  250,  250,  0,    'Paid',     TIMESTAMP(CURDATE() - INTERVAL 19 DAY, '09:05:00'), NULL);

INSERT INTO payment (invoice_id, received_by, amount, payment_method, reference_number, is_down_payment, payment_date, notes) VALUES
(20, 2, 1750, 'Cash',  NULL,         0, TIMESTAMP(CURDATE() - INTERVAL 22 DAY, '15:45:00'), NULL),
(21, 2, 1400, 'GCash', 'GC-4471823', 0, TIMESTAMP(CURDATE() - INTERVAL 17 DAY, '14:20:00'), NULL),
(23, 1, 4250, 'Cash',  NULL,         1, TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '10:20:00'), 'Down payment'),
(24, 2, 600,  'Cash',  NULL,         1, TIMESTAMP(CURDATE() - INTERVAL 2 DAY,  '09:40:00'), 'Down payment'),
(25, 2, 2650, 'GCash', 'GC-5590213', 0, NOW() - INTERVAL 110 MINUTE, NULL),
(26, 2, 400,  'Cash',  NULL,         0, TIMESTAMP(CURDATE() - INTERVAL 13 DAY, '11:00:00'), NULL),
(29, 2, 250,  'GCash', 'GC-3318045', 0, TIMESTAMP(CURDATE() - INTERVAL 19 DAY, '09:05:00'), NULL);

-- ---------- 11. POS walk-in sales (one voided) ----------
INSERT INTO pos_transaction (pos_id, customer_id, customer_type, walkin_customer_name, walkin_contact_number, walkin_address, vehicle_id,
                             processed_by, transaction_date, total_amount, status, payment_method, reference_number, void_reason, voided_at, voided_by) VALUES
(20, NULL, 'Walk-in',    'Pedro Aquino', '09271234500', 'Rosario, La Union', NULL, 2, TIMESTAMP(CURDATE() - INTERVAL 9 DAY, '11:15:00'), 500,  'Completed', 'Cash',  NULL, NULL, NULL, NULL),
(21, 21,   'Registered', NULL,           NULL,          NULL,                23,   2, TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '15:40:00'), 600,  'Completed', 'GCash', 'GC-7712094', NULL, NULL, NULL),
(22, NULL, 'Walk-in',    'Mark Soriano', '09351239876', 'Agoo, La Union',    NULL, 2, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '10:05:00'), 500,  'Cancelled', 'Cash',  NULL,
     'Wrong item rung up; the customer needed a different armature size.', TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '10:30:00'), 1),
(23, NULL, 'Walk-in',    'Ben Castillo', '09451230987', 'Pugo, La Union',    NULL, 2, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '16:20:00'), 1850, 'Completed', 'Cash',  NULL, NULL, NULL, NULL),
(24, NULL, 'Walk-in',    'Rosa Lim',     NULL,          NULL,                NULL, 2, NOW() - INTERVAL 30 MINUTE,                        300,  'Completed', 'Cash',  NULL, NULL, NULL, NULL);

INSERT INTO pos_item (pos_id, part_id, quantity_sold, unit_price_at_sale, subtotal) VALUES
(20, 3, 1, 500, 500), (21, 4, 3, 200, 600), (22, 6, 2, 250, 500), (23, 7, 1, 1850, 1850), (24, 5, 1, 300, 300);

-- ---------- 12. Activity log (Owner-only audit page) ----------
INSERT INTO activity_log (user_id, action, entity_type, entity_id, entity_ref, details, created_at) VALUES
(2, 'Recorded payment',      'Invoice',   20, (SELECT invoice_number FROM invoice WHERE invoice_id = 20), '₱1,750.00 by Cash',                       TIMESTAMP(CURDATE() - INTERVAL 22 DAY, '15:45:00')),
(2, 'Recorded payment',      'Invoice',   26, (SELECT invoice_number FROM invoice WHERE invoice_id = 26), '₱400.00 by Cash',                         TIMESTAMP(CURDATE() - INTERVAL 13 DAY, '11:00:00')),
(2, 'Recorded payment',      'Invoice',   21, (SELECT invoice_number FROM invoice WHERE invoice_id = 21), '₱1,400.00 by GCash (ref GC-4471823)',     TIMESTAMP(CURDATE() - INTERVAL 17 DAY, '14:20:00')),
(1, 'Recorded down payment', 'Job order', 23, 'JO-D3F58', CONCAT('₱4,250.00 by Cash · invoice ', (SELECT invoice_number FROM invoice WHERE invoice_id = 23)), TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '10:20:00')),
(2, 'Cancelled job order',   'Job order', 29, 'JO-D27B1', 'Reason: Customer decided to bring the vehicle to the dealer instead.', TIMESTAMP(CURDATE() - INTERVAL 8 DAY, '09:15:00')),
(2, 'Cancelled web order',   'Web order', 25, 'PO-25',    'Reason: Customer found the part elsewhere.',                         TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '10:00:00')),
(1, 'Voided sale',           'POS sale',  22, 'POS-00022', 'Amount ₱500.00. Reason: Wrong item rung up; the customer needed a different armature size.', TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '10:30:00')),
(2, 'Recorded down payment', 'Job order', 25, 'JO-F7B30', CONCAT('₱600.00 by Cash · invoice ', (SELECT invoice_number FROM invoice WHERE invoice_id = 24)), TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '09:40:00')),
(2, 'Recorded payment',      'Invoice',   25, (SELECT invoice_number FROM invoice WHERE invoice_id = 25), '₱2,650.00 by GCash (ref GC-5590213)',     NOW() - INTERVAL 110 MINUTE);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
