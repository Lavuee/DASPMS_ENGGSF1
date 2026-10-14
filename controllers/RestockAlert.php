<?php
/*
    Low-stock email alerts to suppliers.
    Automatic checks must run AFTER the stock-changing transaction commits,
    so a rolled-back sale never triggers an email.
*/

require_once __DIR__ . '/../includes/mailer.php';

class RestockAlert {

    private static function writeLog($message) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $time = date('Y-m-d H:i:s');
        @file_put_contents($logDir . '/email_debug.log', "[$time] " . $message . PHP_EOL, FILE_APPEND);
    }

    private static function findPartWithSupplier($db, $part_id, $activeOnly) {
        $query = "
            SELECT p.part_name, p.quantity_on_hand, p.low_stock_threshold, s.supplier_name, s.email
            FROM part p
            JOIN supplier s ON p.supplier_id = s.supplier_id
            WHERE p.part_id = ?
        ";

        if ($activeOnly) {
            $query .= " AND p.is_active = 1 AND s.is_active = 1";
        }

        $stmt = $db->prepare($query);
        $stmt->execute([intval($part_id)]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function sendAlert($part) {
        return sendHtmlMail(
            $part['email'],
            "Restock Request: " . html_entity_decode($part['part_name'], ENT_QUOTES, 'UTF-8'),
            self::generateEmailHTML($part)
        );
    }

    private static function generateEmailHTML($part) {
        $supplierName = mailEscape($part['supplier_name']);
        $partName = mailEscape(html_entity_decode($part['part_name'], ENT_QUOTES, 'UTF-8'));
        $quantity = intval($part['quantity_on_hand']);
        $body = emailParagraph('Hello ' . $supplierName . ',')
            . emailParagraph('This is a notification from our inventory system. The part below is running low and needs restocking.')
            . emailDetails([
                'Part' => $partName,
                'Stock left' => $quantity . ($quantity === 1 ? ' piece' : ' pieces'),
                'Date' => date('M j, Y'),
            ])
            . emailParagraph('Please arrange a restock delivery at your earliest convenience. We will follow up if a formal purchase order is required.')
            . '<p style="margin:0;">Thank you,<br>Norily&#039;s Vehicle Repair Shop</p>';

        return renderEmailLayout($body, "Sent automatically by DASPMS, the inventory system of Norily's Vehicle Repair Shop, Vila Rosario, La Union.");
    }

    /**
     * Called automatically after stock is deducted. Fails silently but logs errors.
     */
    public static function checkAndSend($db, $part_id) {
        try {
            $part = self::findPartWithSupplier($db, $part_id, true);

            if ($part && !empty($part['email']) && intval($part['quantity_on_hand']) <= intval($part['low_stock_threshold'])) {
                self::writeLog("AUTO-TRIGGER: Threshold reached for {$part['part_name']}. Attempting to email {$part['email']}...");

                if (self::sendAlert($part)) {
                    self::writeLog("AUTO-TRIGGER: SUCCESS - Email handed to sendmail.");
                } else {
                    self::writeLog("AUTO-TRIGGER: FAILED - " . mailLastError());
                }
            }
        } catch (Exception $e) {
            self::writeLog("AUTO-TRIGGER: SYSTEM ERROR - " . $e->getMessage());
        }
    }

    /**
     * Runs checkAndSend() once for each distinct part id.
     */
    public static function checkParts($db, array $part_ids) {
        foreach (array_unique(array_map('intval', $part_ids)) as $part_id) {
            self::checkAndSend($db, $part_id);
        }
    }

    /**
     * Called by manual button. Forces email and returns feedback to UI.
     */
    public static function manualSend($db, $part_id) {
        try {
            $part = self::findPartWithSupplier($db, $part_id, false);

            if (!$part) return ['success' => false, 'message' => 'Part not found.'];
            if (empty($part['email'])) return ['success' => false, 'message' => 'No supplier email linked to this part.'];

            self::writeLog("MANUAL: Attempting to email {$part['email']} for {$part['part_name']}...");

            if (self::sendAlert($part)) {
                self::writeLog("MANUAL: SUCCESS - Email sent.");
                return ['success' => true, 'message' => "Restock alert successfully sent to {$part['supplier_name']}!"];
            }

            $errDetail = mailLastError();
            self::writeLog("MANUAL: FAILED - " . $errDetail);
            return ['success' => false, 'message' => "Failed to send email. Check logs. Detail: " . $errDetail];
        } catch (Exception $e) {
            self::writeLog("MANUAL: SYSTEM ERROR - " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error during email dispatch.'];
        }
    }
}
