<?php
// api/analytics.php
// GET ?action=collection_over_time   — total amount per month
// GET ?action=by_type                — total weight per recyclable category
// GET ?action=toxic_vs_nontoxic      — toxic vs non-toxic breakdown
// GET ?action=household_leaderboard  — top households by total collection
// GET ?action=bin_usage              — transportation counts per bin

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'config.php';

$h = session_household();
$a = session_admin();
if (!$h && !$a) json_response(['error' => 'Unauthorized'], 401);

$action = $_GET['action'] ?? '';
$db     = getDB();

// If household, scope to their own data
$hid = $h ? $h['household_id'] : null;

switch ($action) {

    // Monthly totals
    case 'collection_over_time':
        if ($hid) {
            $stmt = $db->prepare("
                SELECT DATE_FORMAT(date, '%Y-%m') AS month, SUM(amount) AS total
                FROM Collection_Record
                WHERE household_id = ?
                GROUP BY month ORDER BY month
            ");
            $stmt->execute([$hid]);
        } else {
            $stmt = $db->query("
                SELECT DATE_FORMAT(date, '%Y-%m') AS month, SUM(amount) AS total
                FROM Collection_Record
                GROUP BY month ORDER BY month
            ");
        }
        json_response($stmt->fetchAll());
        break;

    // Weight per recyclable category
    case 'by_type':
        if ($hid) {
            $stmt = $db->prepare("
                SELECT rt.category, SUM(cr.weight) AS total_weight
                FROM Collection_Recyclable cr
                JOIN Collection_Record rec ON rec.collection_id = cr.collection_id
                JOIN Recyclable_Type rt   ON rt.recyclable_type_id = cr.recyclable_type_id
                WHERE rec.household_id = ?
                GROUP BY rt.category ORDER BY total_weight DESC
            ");
            $stmt->execute([$hid]);
        } else {
            $stmt = $db->query("
                SELECT rt.category, SUM(cr.weight) AS total_weight
                FROM Collection_Recyclable cr
                JOIN Recyclable_Type rt ON rt.recyclable_type_id = cr.recyclable_type_id
                GROUP BY rt.category ORDER BY total_weight DESC
            ");
        }
        json_response($stmt->fetchAll());
        break;

    // Toxic vs non-toxic
    case 'toxic_vs_nontoxic':
        if ($hid) {
            $stmt = $db->prepare("
                SELECT rt.is_toxic, SUM(cr.weight) AS total_weight
                FROM Collection_Recyclable cr
                JOIN Collection_Record rec ON rec.collection_id = cr.collection_id
                JOIN Recyclable_Type rt   ON rt.recyclable_type_id = cr.recyclable_type_id
                WHERE rec.household_id = ?
                GROUP BY rt.is_toxic
            ");
            $stmt->execute([$hid]);
        } else {
            $stmt = $db->query("
                SELECT rt.is_toxic, SUM(cr.weight) AS total_weight
                FROM Collection_Recyclable cr
                JOIN Recyclable_Type rt ON rt.recyclable_type_id = cr.recyclable_type_id
                GROUP BY rt.is_toxic
            ");
        }
        $rows = $stmt->fetchAll();
        $out  = ['toxic' => 0, 'non_toxic' => 0];
        foreach ($rows as $r)
            $out[$r['is_toxic'] ? 'toxic' : 'non_toxic'] = (float)$r['total_weight'];
        json_response($out);
        break;

    // Leaderboard (admin only)
    case 'household_leaderboard':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        $stmt = $db->query("
            SELECT hh.name, SUM(c.amount) AS total_collected
            FROM Collection_Record c
            JOIN Household hh ON hh.household_id = c.household_id
            GROUP BY c.household_id ORDER BY total_collected DESC LIMIT 10
        ");
        json_response($stmt->fetchAll());
        break;

    // Bin usage
    case 'bin_usage':
        if (!$a) json_response(['error' => 'Forbidden'], 403);
        $stmt = $db->query("
            SELECT wb.location, wb.type, COUNT(*) AS trip_count
            FROM Transportation t
            JOIN WasteBin wb ON wb.wastebin_id = t.wastebin_id
            GROUP BY t.wastebin_id ORDER BY trip_count DESC
        ");
        json_response($stmt->fetchAll());
        break;

    default:
        json_response(['error' => 'Unknown action'], 404);
}
