<?php
require_once __DIR__ . '/app.php';

class Database {
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . env('DB_HOST', 'db') . ";dbname=" . env('DB_NAME', 'daspms');
            $this->conn = new PDO($dsn, env('DB_USER', 'root'), (string)env('DB_PASS', ''), [PDO::ATTR_TIMEOUT => 5]);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("SET time_zone = '" . date('P') . "'");
        } catch(PDOException $exception) {
            error_log("DASPMS DB CONNECTION ERROR: " . $exception->getMessage());
            http_response_code(500);
            exit("The system is temporarily unavailable. Please try again later.");
        }
        return $this->conn;
    }
}
?>
