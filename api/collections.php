<?php
// api/collections.php
// GET  ?action=list             (admin all, household own)
// GET  ?action=get&id=X
// POST ?action=create           (admin or household)
// PUT  ?action=update&id=X      (admin)
// DELETE ?action=delete&id=X    (admin)
// GET  ?action=search&q=term

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

// Helper: fetch recyclable breakdown for a collection
function getBreakdown(PDO $db, int $id): array {
    $stmt = $db->prepare("
        SELECT rt.category, rt.is_toxic, cr.weight
        FROM Collection_Recyclable cr
        JOIN Recyclable_Type rt ON rt.recyclable_type_id = cr.recyclable_type_id
        WHERE cr.collection_id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

switch ($action) {

    case 'list':
        $db = getDB();
        if ($a) {
            $rows = $db->query("
                SELECT c.*, hh.name AS household_name
                FROM Collection_Record c
                JOIN Household hh ON hh.household_id = c.household_id
                ORDER BY c.date DESC
            ")->fetchAll();
        } else {
            $stmt = $db->prepare("SELECT * FROM Collection_Record WHERE household_id = ? ORDER BY date DESC");
            $stmt->execute([$h['household_id']]);
            $rows = $stmt->fetchAll();
        }
        // attach breakdown to each row
        foreach ($rows as &$row)
            $row['breakdown'] = getBreakdown($db, (int)$row['collection_id']);
        json_response($rows);
        break;

    case 'search':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        $q  = '%' . ($_GET['q'] ?? '') . '%';
        $db = getDB();
        $stmt = $db->prepare("
            SELECT c.*, hh.name AS household_name
            FROM Collection_Record c
            JOIN Household hh ON hh.household_id = c.household_id
            WHERE hh.name LIKE ? OR c.date LIKE ?
            ORDER BY c.date DESC
        ");
        $stmt->execute([$q, $q]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row)
            $row['breakdown'] = getBreakdown($db, (int)$row['collection_id']);
        json_response($rows);
        break;

    case 'get':
        $id   = (int)($_GET['id'] ?? 0);
        $db   = getDB();
        $stmt = $db->prepare("SELECT c.*, hh.name AS household_name FROM Collection_Record c
                              JOIN Household hh ON hh.household_id = c.household_id
                              WHERE c.collection_id = ?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if (!$row) json_response(['error' => 'Not found'], 404);
        if ($h && $h['household_id'] !== (int)$row['household_id']) json_response(['error' => 'Forbidden'], 403);
        $row['breakdown'] = getBreakdown($db, $id);
        json_response($row);
        break;

    case 'create':
        // body: { household_id (admin only), date, amount, types: [{recyclable_type_id, weight}, ...] }
        $hid = $a ? ($body['household_id'] ?? null) : $h['household_id'];
        if (!$hid) json_response(['error' => 'household_id required'], 422);
        foreach (['date','amount'] as $f)
            if (empty($body[$f])) json_response(['error' => "Missing: $f"], 422);
        $types = $body['types'] ?? [];
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO Collection_Record (household_id, date, amount) VALUES (?,?,?)")
               ->execute([$hid, $body['date'], $body['amount']]);
            $cid = $db->lastInsertId();
            foreach ($types as $t) {
                $db->prepare("INSERT INTO Collection_Recyclable (collection_id, recyclable_type_id, weight) VALUES (?,?,?)")
                   ->execute([$cid, $t['recyclable_type_id'], $t['weight']]);
            }
            $db->commit();
            json_response(['message' => 'Collection created', 'collection_id' => $cid], 201);
        } catch (PDOException $e) {
            $db->rollBack();
            json_response(['error' => 'Failed: ' . $e->getMessage()], 500);
        }
        break;

    case 'update':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        $id = (int)($_GET['id'] ?? 0);
        $fields = []; $params = [];
        foreach (['date','amount'] as $f)
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
        $db = getDB();
        $db->beginTransaction();
        try {
            if ($fields) {
                $params[] = $id;
                $db->prepare("UPDATE Collection_Record SET " . implode(', ', $fields) . " WHERE collection_id = ?")
                   ->execute($params);
            }
            if (isset($body['types'])) {
                $db->prepare("DELETE FROM Collection_Recyclable WHERE collection_id = ?")->execute([$id]);
                foreach ($body['types'] as $t) {
                    $db->prepare("INSERT INTO Collection_Recyclable (collection_id, recyclable_type_id, weight) VALUES (?,?,?)")
                       ->execute([$id, $t['recyclable_type_id'], $t['weight']]);
                }
            }
            $db->commit();
            json_response(['message' => 'Updated']);
        } catch (PDOException $e) {
            $db->rollBack();
            json_response(['error' => 'Failed'], 500);
        }
        break;

    case 'delete':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        getDB()->prepare("DELETE FROM Collection_Record WHERE collection_id = ?")->execute([(int)$_GET['id']]);
        json_response(['message' => 'Deleted']);
        break;

    // Recyclable types list (needed for dropdowns)
    case 'types':
        json_response(getDB()->query("SELECT * FROM Recyclable_Type ORDER BY category")->fetchAll());
        break;

    default:
        json_response(['error' => 'Unknown action'], 404);
}
