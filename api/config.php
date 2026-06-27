<?php
// api/config.php
// Replace these values with your actual DB credentials

define('DB_HOST', 'localhost');
define('DB_NAME', 'waste_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

function json_response(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_json_body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

// Session helpers
function session_household(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['household'] ?? null;
}

function session_admin(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['admin'] ?? null;
}

function require_household(): array {
    $h = session_household();
    if (!$h) json_response(['error' => 'Unauthorized'], 401);
    return $h;
}

function require_admin(): array {
    $a = session_admin();
    if (!$a) json_response(['error' => 'Unauthorized'], 401);
    return $a;
}
