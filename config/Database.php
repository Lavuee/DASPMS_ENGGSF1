<?php
require_once __DIR__ . '/app.php';

class Database {
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME', 'daspms');
            // Fail fast on a wrong/unreachable DB_HOST instead of hanging until the web server gives up.
            $this->conn = new PDO($dsn, env('DB_USER', 'root'), (string)env('DB_PASS', ''), [PDO::ATTR_TIMEOUT => 5]);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Make NOW() match PHP's date()/time() so expiry checks agree.
            $this->conn->exec("SET time_zone = '" . date('P') . "'");
        } catch(PDOException $exception) {
            // Log the details for developers; never show connection details to visitors.
            error_log("DASPMS DB CONNECTION ERROR: " . $exception->getMessage());
            http_response_code(500);
            exit("The system is temporarily unavailable. Please try again later.");
        }
        return $this->conn;
    }
}
?>
