<?php
// api/auth.php
// Handles: POST /api/auth.php?action=register_household
//          POST /api/auth.php?action=login_household
//          POST /api/auth.php?action=register_admin
//          POST /api/auth.php?action=login_admin
//          POST /api/auth.php?action=logout
//          GET  /api/auth.php?action=me

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'config.php';

$action = $_GET['action'] ?? '';
$body   = get_json_body();

switch ($action) {

    // ----------------------------------------------------------
    case 'register_household':
        $required = ['name','address','phone_number','email','password'];
        foreach ($required as $f) {
            if (empty($body[$f])) json_response(['error' => "Missing field: $f"], 422);
        }
        $db   = getDB();
        $hash = password_hash($body['password'], PASSWORD_BCRYPT);
        try {
            $stmt = $db->prepare("INSERT INTO Household (name, address, phone_number, email, password_hash)
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$body['name'], $body['address'], $body['phone_number'], $body['email'], $hash]);
            $id = $db->lastInsertId();
            json_response(['message' => 'Household registered', 'household_id' => $id], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') json_response(['error' => 'Email already registered'], 409);
            json_response(['error' => 'Registration failed'], 500);
        }
        break;

    // ----------------------------------------------------------
    case 'login_household':
        if (empty($body['email']) || empty($body['password']))
            json_response(['error' => 'Email and password required'], 422);
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM Household WHERE email = ?");
        $stmt->execute([$body['email']]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($body['password'], $user['password_hash']))
            json_response(['error' => 'Invalid credentials'], 401);
        unset($user['password_hash']);
        $_SESSION['household'] = $user;
        json_response(['message' => 'Login successful', 'user' => $user]);
        break;

    // ----------------------------------------------------------
    case 'register_admin':
        $required = ['username','email','password'];
        foreach ($required as $f) {
            if (empty($body[$f])) json_response(['error' => "Missing field: $f"], 422);
        }
        $db   = getDB();
        $hash = password_hash($body['password'], PASSWORD_BCRYPT);
        try {
            $stmt = $db->prepare("INSERT INTO Admin (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$body['username'], $body['email'], $hash]);
            $id = $db->lastInsertId();
            json_response(['message' => 'Admin registered', 'admin_id' => $id], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') json_response(['error' => 'Username or email already taken'], 409);
            json_response(['error' => 'Registration failed'], 500);
        }
        break;

    // ----------------------------------------------------------
    case 'login_admin':
        if (empty($body['email']) || empty($body['password']))
            json_response(['error' => 'Email and password required'], 422);
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM Admin WHERE email = ?");
        $stmt->execute([$body['email']]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($body['password'], $admin['password_hash']))
            json_response(['error' => 'Invalid credentials'], 401);
        unset($admin['password_hash']);
        $_SESSION['admin'] = $admin;
        json_response(['message' => 'Admin login successful', 'admin' => $admin]);
        break;

    // ----------------------------------------------------------
    case 'logout':
        $_SESSION = [];
        session_destroy();
        json_response(['message' => 'Logged out']);
        break;

    // ----------------------------------------------------------
    case 'me':
        if ($h = session_household()) json_response(['role' => 'household', 'user' => $h]);
        if ($a = session_admin())     json_response(['role' => 'admin',     'user' => $a]);
        json_response(['error' => 'Not authenticated'], 401);
        break;

    default:
        json_response(['error' => 'Unknown action'], 404);
}
