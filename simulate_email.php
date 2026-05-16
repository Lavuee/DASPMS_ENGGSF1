<?php
// Enable strict error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/Database.php';
require_once 'controllers/RestockAlert.php';

echo "<h2>DASPMS Automated Email Simulator</h2>";

try {
    $db = (new Database())->getConnection();
    
    // Step 1: Find a valid active part that is linked to a supplier with an email
    $stmt = $db->query("
        SELECT p.part_id, p.part_name, p.low_stock_threshold, s.supplier_name, s.email 
        FROM part p
        JOIN supplier s ON p.supplier_id = s.supplier_id
        WHERE p.is_active = 1 AND s.is_active = 1 AND s.email != ''
        LIMIT 1
    ");
    $testPart = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$testPart) {
        die("<strong style='color:red;'>Simulation Failed:</strong> Could not find an active part linked to a supplier with a valid email address. Please set one up in the Inventory UI first.");
    }

    $partId = $testPart['part_id'];
    $threshold = $testPart['low_stock_threshold'];
    $triggerStock = max(0, $threshold - 1); // Force stock to drop 1 below the threshold

    echo "<p>Found Test Part: <strong>{$testPart['part_name']}</strong> (Threshold: {$threshold})</p>";
    echo "<p>Supplier Target: <strong>{$testPart['supplier_name']}</strong> ({$testPart['email']})</p>";
    echo "<p>Forcing stock down to: <strong>{$triggerStock}</strong> to trigger the system...</p>";

    // Step 2: Force the database update
    $updateStmt = $db->prepare("UPDATE part SET quantity_on_hand = ? WHERE part_id = ?");
    $updateStmt->execute([$triggerStock, $partId]);

    // Step 3: Trigger the Restock Engine (Exactly as it happens in POS/JobOrder)
    RestockAlert::checkAndSend($db, $partId);

    echo "<h3 style='color:green;'>Simulation Complete!</h3>";
    echo "<p>The RestockAlert engine has fired. Please check the inbox for <strong>{$testPart['email']}</strong> to see your automated HTML notification.</p>";

} catch (Exception $e) {
    echo "<strong style='color:red;'>System Error:</strong> " . $e->getMessage();
}
?>