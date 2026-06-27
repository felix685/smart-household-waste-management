<?php
// api/households.php
// GET    ?action=list           (admin only)
// GET    ?action=get&id=X       (admin or own household)
// PUT    ?action=update&id=X    (own household)
// DELETE ?action=delete&id=X    (admin only)
// GET    ?action=search&q=term  (admin only)

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = get_json_body();

switch ($action) {

    // ----------------------------------------------------------
    case 'list':
        require_admin();
        $db   = getDB();
        $stmt = $db->query("SELECT household_id, name, address, phone_number, email, created_at FROM Household ORDER BY household_id");
        json_response($stmt->fetchAll());
        break;

    // ----------------------------------------------------------
    case 'search':
        require_admin();
        $q  = '%' . ($_GET['q'] ?? '') . '%';
        $db = getDB();
        $stmt = $db->prepare("SELECT household_id, name, address, phone_number, email, created_at
                               FROM Household
                               WHERE name LIKE ? OR address LIKE ? OR email LIKE ?
                               ORDER BY household_id");
        $stmt->execute([$q, $q, $q]);
        json_response($stmt->fetchAll());
        break;

    // ----------------------------------------------------------
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $h  = session_household();
        $a  = session_admin();
        if (!$h && !$a) json_response(['error' => 'Unauthorized'], 401);
        // household can only view their own record
        if ($h && $h['household_id'] !== $id) json_response(['error' => 'Forbidden'], 403);

        $db   = getDB();
        $stmt = $db->prepare("SELECT household_id, name, address, phone_number, email, created_at FROM Household WHERE household_id = ?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if (!$row) json_response(['error' => 'Not found'], 404);
        json_response($row);
        break;

    // ----------------------------------------------------------
    case 'update':
        $id = (int)($_GET['id'] ?? 0);
        $h  = require_household();
        if ($h['household_id'] !== $id) json_response(['error' => 'Forbidden'], 403);

        $fields = [];
        $params = [];
        foreach (['name','address','phone_number'] as $f) {
            if (!empty($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
        }
        if (!empty($body['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($body['password'], PASSWORD_BCRYPT);
        }
        if (empty($fields)) json_response(['error' => 'Nothing to update'], 422);

        $params[] = $id;
        $db = getDB();
        $db->prepare("UPDATE Household SET " . implode(', ', $fields) . " WHERE household_id = ?")
           ->execute($params);
        json_response(['message' => 'Updated']);
        break;

    // ----------------------------------------------------------
    case 'delete':
        require_admin();
        $id = (int)($_GET['id'] ?? 0);
        $db = getDB();
        $db->prepare("DELETE FROM Household WHERE household_id = ?")->execute([$id]);
        json_response(['message' => 'Deleted']);
        break;

    default:
        json_response(['error' => 'Unknown action'], 404);
}
