<?php
// api/wastebins.php
// GET    ?action=list
// GET    ?action=get&id=X
// POST   ?action=create         (admin)
// PUT    ?action=update&id=X    (admin)
// DELETE ?action=delete&id=X    (admin)
// GET    ?action=search&q=term

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'config.php';

$action = $_GET['action'] ?? '';
$body   = get_json_body();

switch ($action) {

    case 'list':
        session_household() || require_admin();
        $db = getDB();
        json_response($db->query("SELECT * FROM WasteBin ORDER BY wastebin_id")->fetchAll());
        break;

    case 'search':
        session_household() || require_admin();
        $q = '%' . ($_GET['q'] ?? '') . '%';
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM WasteBin WHERE location LIKE ? OR type LIKE ? ORDER BY wastebin_id");
        $stmt->execute([$q, $q]);
        json_response($stmt->fetchAll());
        break;

    case 'get':
        session_household() || require_admin();
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM WasteBin WHERE wastebin_id = ?");
        $stmt->execute([(int)$_GET['id']]);
        $row  = $stmt->fetch();
        if (!$row) json_response(['error' => 'Not found'], 404);
        json_response($row);
        break;

    case 'create':
        require_admin();
        foreach (['location','type','capacity'] as $f)
            if (empty($body[$f])) json_response(['error' => "Missing: $f"], 422);
        $db = getDB();
        $db->prepare("INSERT INTO WasteBin (location, type, capacity) VALUES (?,?,?)")
           ->execute([$body['location'], $body['type'], $body['capacity']]);
        json_response(['message' => 'WasteBin created', 'id' => $db->lastInsertId()], 201);
        break;

    case 'update':
        require_admin();
        $fields = []; $params = [];
        foreach (['location','type','capacity'] as $f)
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
        if (!$fields) json_response(['error' => 'Nothing to update'], 422);
        $params[] = (int)$_GET['id'];
        getDB()->prepare("UPDATE WasteBin SET " . implode(', ', $fields) . " WHERE wastebin_id = ?")
               ->execute($params);
        json_response(['message' => 'Updated']);
        break;

    case 'delete':
        require_admin();
        getDB()->prepare("DELETE FROM WasteBin WHERE wastebin_id = ?")->execute([(int)$_GET['id']]);
        json_response(['message' => 'Deleted']);
        break;

    default:
        json_response(['error' => 'Unknown action'], 404);
}
