<?php
// api/qa.php
// GET  ?action=list             (admin all, household own)
// GET  ?action=get&id=X
// POST ?action=create           (household)
// PUT  ?action=update&id=X      (household - edit own question)
// DELETE ?action=delete&id=X    (admin)
// POST ?action=reply&id=X       (admin)
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

function getReplies(PDO $db, int $qa_id): array {
    $stmt = $db->prepare("
        SELECT r.*, ad.username AS admin_username
        FROM QA_Reply r
        JOIN Admin ad ON ad.admin_id = r.admin_id
        WHERE r.qa_id = ?
        ORDER BY r.replied_at ASC
    ");
    $stmt->execute([$qa_id]);
    return $stmt->fetchAll();
}

switch ($action) {

    case 'list':
        $db = getDB();
        if ($a) {
            $rows = $db->query("
                SELECT q.*, hh.name AS household_name
                FROM QA q
                JOIN Household hh ON hh.household_id = q.household_id
                ORDER BY q.date DESC
            ")->fetchAll();
        } else {
            $stmt = $db->prepare("SELECT * FROM QA WHERE household_id = ? ORDER BY date DESC");
            $stmt->execute([$h['household_id']]);
            $rows = $stmt->fetchAll();
        }
        foreach ($rows as &$row)
            $row['replies'] = getReplies($db, (int)$row['qa_id']);
        json_response($rows);
        break;

    case 'search':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        $q  = '%' . ($_GET['q'] ?? '') . '%';
        $db = getDB();
        $stmt = $db->prepare("
            SELECT q.*, hh.name AS household_name
            FROM QA q
            JOIN Household hh ON hh.household_id = q.household_id
            WHERE q.content LIKE ? OR hh.name LIKE ? OR q.status LIKE ?
            ORDER BY q.date DESC
        ");
        $stmt->execute([$q, $q, $q]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row)
            $row['replies'] = getReplies($db, (int)$row['qa_id']);
        json_response($rows);
        break;

    case 'get':
        $id   = (int)($_GET['id'] ?? 0);
        $db   = getDB();
        $stmt = $db->prepare("SELECT q.*, hh.name AS household_name FROM QA q
                              JOIN Household hh ON hh.household_id = q.household_id
                              WHERE q.qa_id = ?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if (!$row) json_response(['error' => 'Not found'], 404);
        if ($h && $h['household_id'] !== (int)$row['household_id']) json_response(['error' => 'Forbidden'], 403);
        $row['replies'] = getReplies($db, $id);
        json_response($row);
        break;

    case 'create':
        if (!$h) json_response(['error' => 'Forbidden'], 403);
        if (empty($body['content'])) json_response(['error' => 'Content required'], 422);
        $db = getDB();
        $db->prepare("INSERT INTO QA (household_id, content) VALUES (?, ?)")
           ->execute([$h['household_id'], $body['content']]);
        json_response(['message' => 'Question submitted', 'qa_id' => $db->lastInsertId()], 201);
        break;

    case 'update':
        if (!$h) json_response(['error' => 'Forbidden'], 403);
        $id   = (int)($_GET['id'] ?? 0);
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM QA WHERE qa_id = ?");
        $stmt->execute([$id]);
        $qa   = $stmt->fetch();
        if (!$qa) json_response(['error' => 'Not found'], 404);
        if ((int)$qa['household_id'] !== $h['household_id']) json_response(['error' => 'Forbidden'], 403);
        if (empty($body['content'])) json_response(['error' => 'Content required'], 422);
        $db->prepare("UPDATE QA SET content = ? WHERE qa_id = ?")->execute([$body['content'], $id]);
        json_response(['message' => 'Updated']);
        break;

    case 'delete':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        getDB()->prepare("DELETE FROM QA WHERE qa_id = ?")->execute([(int)$_GET['id']]);
        json_response(['message' => 'Deleted']);
        break;

    case 'reply':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        $id = (int)($_GET['id'] ?? 0);
        if (empty($body['content'])) json_response(['error' => 'Content required'], 422);
        $db = getDB();
        $db->prepare("INSERT INTO QA_Reply (qa_id, admin_id, content) VALUES (?,?,?)")
           ->execute([$id, $a['admin_id'], $body['content']]);
        $db->prepare("UPDATE QA SET status = 'answered' WHERE qa_id = ?")->execute([$id]);
        json_response(['message' => 'Reply posted'], 201);
        break;

    default:
        json_response(['error' => 'Unknown action'], 404);
}
