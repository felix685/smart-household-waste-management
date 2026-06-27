<?php
// api/transportation.php
// GET  ?action=list             (admin sees all, household sees own)
// GET  ?action=get&household_id=X&wastebin_id=Y&schedule_time=Z
// POST ?action=create           (household)
// PUT  ?action=update           (admin)
// DELETE ?action=delete         (admin)
// GET  ?action=search&q=term    (admin)

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'config.php';

$action = $_GET['action'] ?? '';
$body   = get_json_body();
$h      = session_household();
$a      = session_admin();
if (!$h && !$a) json_response(['error' => 'Unauthorized'], 401);

switch ($action) {

    case 'list':
        $db = getDB();
        if ($a) {
            $rows = $db->query("
                SELECT t.*, hh.name AS household_name, wb.location AS bin_location, wb.type AS bin_type
                FROM Transportation t
                JOIN Household hh ON hh.household_id = t.household_id
                JOIN WasteBin  wb ON wb.wastebin_id  = t.wastebin_id
                ORDER BY t.schedule_time DESC
            ")->fetchAll();
        } else {
            $stmt = $db->prepare("
                SELECT t.*, wb.location AS bin_location, wb.type AS bin_type
                FROM Transportation t
                JOIN WasteBin wb ON wb.wastebin_id = t.wastebin_id
                WHERE t.household_id = ?
                ORDER BY t.schedule_time DESC
            ");
            $stmt->execute([$h['household_id']]);
            $rows = $stmt->fetchAll();
        }
        json_response($rows);
        break;

    case 'search':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        $q  = '%' . ($_GET['q'] ?? '') . '%';
        $db = getDB();
        $stmt = $db->prepare("
            SELECT t.*, hh.name AS household_name, wb.location AS bin_location, wb.type AS bin_type
            FROM Transportation t
            JOIN Household hh ON hh.household_id = t.household_id
            JOIN WasteBin  wb ON wb.wastebin_id  = t.wastebin_id
            WHERE hh.name LIKE ? OR wb.location LIKE ? OR t.status LIKE ?
            ORDER BY t.schedule_time DESC
        ");
        $stmt->execute([$q, $q, $q]);
        json_response($stmt->fetchAll());
        break;

    case 'create':
        if (!$h) json_response(['error' => 'Forbidden'], 403);
        foreach (['wastebin_id','schedule_time'] as $f)
            if (empty($body[$f])) json_response(['error' => "Missing: $f"], 422);
        $db = getDB();
        try {
            $db->prepare("INSERT INTO Transportation (household_id, wastebin_id, status, schedule_time)
                          VALUES (?, ?, 'scheduled', ?)")
               ->execute([$h['household_id'], $body['wastebin_id'], $body['schedule_time']]);
            json_response(['message' => 'Transportation scheduled'], 201);
        } catch (PDOException $e) {
            json_response(['error' => 'Duplicate or invalid entry'], 409);
        }
        break;

    case 'update':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        foreach (['household_id','wastebin_id','schedule_time'] as $f)
            if (empty($body[$f])) json_response(['error' => "Missing PK: $f"], 422);
        $fields = []; $params = [];
        foreach (['status','schedule_time'] as $f)
            if (isset($body["new_$f"])) { $fields[] = "$f = ?"; $params[] = $body["new_$f"]; }
        if (!$fields) json_response(['error' => 'Nothing to update'], 422);
        $params = array_merge($params, [$body['household_id'], $body['wastebin_id'], $body['schedule_time']]);
        getDB()->prepare("UPDATE Transportation SET " . implode(', ', $fields) .
                         " WHERE household_id=? AND wastebin_id=? AND schedule_time=?")
               ->execute($params);
        json_response(['message' => 'Updated']);
        break;

    case 'delete':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        foreach (['household_id','wastebin_id','schedule_time'] as $f)
            if (empty($_GET[$f])) json_response(['error' => "Missing: $f"], 422);
        getDB()->prepare("DELETE FROM Transportation WHERE household_id=? AND wastebin_id=? AND schedule_time=?")
               ->execute([$_GET['household_id'], $_GET['wastebin_id'], $_GET['schedule_time']]);
        json_response(['message' => 'Deleted']);
        break;

    default:
        json_response(['error' => 'Unknown action'], 404);
}
