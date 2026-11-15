<?php
// Shared setup for every API endpoint: JSON/CORS headers, DB access, Bearer-token
// authentication, role checks and safe record helpers.

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../functions/connect.php';

const API_TOKEN_TTL_DAYS = 30;
const API_RIGHTS = ['view_report', 'take_survey', 'correct_survey', 'approval', 'users_management', 'register_farmer'];

function jsonResponse($code, $body)
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

function requireMethod($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header("Allow: $method");
        jsonResponse(405, ["message" => "Use $method for this endpoint."]);
    }
}

function readJsonBody()
{
    $body = json_decode(file_get_contents("php://input"), true);
    if (!is_array($body)) {
        jsonResponse(400, ["message" => "Request body must be valid JSON."]);
    }
    return $body;
}

// ---------------------------------------------------------------- tokens

function issueApiToken($userId)
{
    $token = bin2hex(random_bytes(32));
    executeQuery(
        "INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))",
        [$userId, hash('sha256', $token), API_TOKEN_TTL_DAYS]
    );
    $row = executeQuery("SELECT expires_at FROM api_tokens WHERE token_hash = ?", [hash('sha256', $token)])
        ->get_result()->fetch_assoc();
    return [$token, $row['expires_at']];
}

function bearerToken()
{
    $header = '';
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (!empty($_SERVER[$key])) {
            $header = $_SERVER[$key];
            break;
        }
    }
    if (!$header && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
            }
        }
    }
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($header), $m)) {
        return strtolower($m[1]);
    }
    return null;
}

function userRights($designation)
{
    $rights = [];
    foreach (API_RIGHTS as $right) {
        if (!empty($designation[$right]) && (int) $designation[$right] === 1) {
            $rights[] = $right;
        }
    }
    return $rights;
}

/**
 * Authenticate the request from its Bearer token. Ends with 401 when missing, unknown,
 * revoked or expired. The user is always taken from the token, never from the request.
 */
function requireApiUser()
{
    $token = bearerToken();
    if (!$token) {
        jsonResponse(401, ["message" => "Missing or malformed Authorization: Bearer token."]);
    }
    $row = executeQuery(
        "SELECT t.id AS token_id, u.id, u.firstname, u.lastname,
                d.view_report, d.take_survey, d.correct_survey, d.approval, d.users_management, d.register_farmer
           FROM api_tokens t
           JOIN users u ON u.id = t.user_id
           LEFT JOIN designation d ON d.id = u.designation_id
          WHERE t.token_hash = ? AND t.revoked_at IS NULL AND t.expires_at > NOW()
          LIMIT 1",
        [hash('sha256', $token)]
    )->get_result()->fetch_assoc();
    if (!$row) {
        jsonResponse(401, ["message" => "Invalid or expired token. Log in again."]);
    }
    executeQuery("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?", [$row['token_id']]);

    return [
        'id' => (int) $row['id'],
        'token_id' => (int) $row['token_id'],
        'name' => trim($row['firstname'] . ' ' . $row['lastname']),
        'rights' => userRights($row),
    ];
}

/**
 * Ends with 403 unless the user holds at least one of the given rights.
 */
function requireApiRight($user, ...$rights)
{
    if (!array_intersect($rights, $user['rights'])) {
        jsonResponse(403, ["message" => "You do not have permission for this action (needs: " . implode(' or ', $rights) . ")."]);
    }
}

// --------------------------------------------------------------- records
// Table names are always hard-coded by the endpoint, never taken from the request.

function listUnverified($table)
{
    return selectAll($table, ['status' => 'NOT VERIFIED']);
}

function findRecord($table, $id)
{
    if (!ctype_digit((string) $id)) {
        jsonResponse(400, ["message" => "A numeric record id is required."]);
    }
    return selectOne($table, ['id' => $id]);
}

function getUnverified($table, $id)
{
    $record = findRecord($table, $id);
    if (!$record || $record['status'] !== 'NOT VERIFIED') {
        jsonResponse(404, ["message" => "Record not found or already verified."]);
    }
    return $record;
}

function verifyRecord($table, $id)
{
    getUnverified($table, $id);
    update($table, $id, 'id', ['status' => 'VERIFIED']);
    jsonResponse(200, ["message" => "Record marked as verified."]);
}

/**
 * Columns a client may write: real, non-generated columns, excluding id and status.
 */
function writableColumns($table)
{
    $columns = [];
    $stmt = executeQuery("SHOW COLUMNS FROM `$table`");
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $col) {
        if (stripos($col['Extra'], 'GENERATED') !== false) {
            continue;
        }
        if (!in_array($col['Field'], ['id', 'status'], true)) {
            $columns[] = $col['Field'];
        }
    }
    return $columns;
}

/**
 * Keep only writable columns. Unknown keys are rejected rather than silently dropped,
 * because keys become column names in SQL.
 */
function allowedColumns($table, $data)
{
    unset($data['id'], $data['userid']);
    $writable = writableColumns($table);
    $unknown = array_values(array_diff(array_keys($data), $writable));
    if ($unknown) {
        jsonResponse(422, ["message" => "Fields not allowed: " . implode(', ', $unknown)]);
    }
    return $data;
}

function updateRecord($table, $body)
{
    $id = isset($body['id']) ? $body['id'] : '';
    if (!findRecord($table, $id)) {
        jsonResponse(404, ["message" => "Record not found."]);
    }
    $data = allowedColumns($table, $body);
    if (!$data) {
        jsonResponse(400, ["message" => "No fields to update."]);
    }
    $changed = update($table, $id, 'id', $data);
    jsonResponse(200, ["message" => $changed ? "Record updated." : "No changes were needed."]);
}

/**
 * Insert one record or a list of records. Every record is checked against the
 * endpoint's field list before anything is written; new records start NOT VERIFIED.
 */
function createRecords($table, array $fields, $body)
{
    if (!$body) {
        jsonResponse(400, ["message" => "No records supplied."]);
    }
    $records = array_keys($body) === range(0, count($body) - 1) ? $body : [$body];
    $rows = [];
    foreach ($records as $i => $record) {
        if (!is_array($record)) {
            jsonResponse(400, ["message" => "Record $i is not an object."]);
        }
        $unknown = array_values(array_diff(array_keys($record), $fields));
        if ($unknown) {
            jsonResponse(422, ["message" => "Record $i has fields that are not allowed: " . implode(', ', $unknown)]);
        }
        $row = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $record) && $record[$field] !== null) {
                $row[$field] = $record[$field];
            }
        }
        $row['status'] = 'NOT VERIFIED';
        $rows[] = $row;
    }

    global $connection;
    $connection->begin_transaction();
    $ids = [];
    foreach ($rows as $row) {
        $id = insert($table, $row);
        if (!$id) {
            $connection->rollback();
            jsonResponse(500, ["message" => "Could not save the records; nothing was saved."]);
        }
        $ids[] = $id;
    }
    $connection->commit();
    jsonResponse(201, ["message" => count($ids) . " record(s) saved.", "ids" => $ids]);
}
