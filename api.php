<?php
/**
 * Project Eidos — NAS Data API
 * Reads and writes eidos-data.json in the same folder.
 *
 * GET  api.php  → returns { lastModified, data } (lastModified=0 if no file)
 * POST api.php  → body must be { lastModified, data }; saves atomically
 */

header('Content-Type: application/json; charset=utf-8');
// Reflect origin so file:// (null origin) and http:// origins both work
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'null';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-store');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$dataFile = __DIR__ . '/eidos-data.json';

// ── GET: return saved data ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($dataFile)) {
        echo json_encode(['lastModified' => 0, 'data' => []]);
        exit;
    }

    $raw     = file_get_contents($dataFile);
    $decoded = json_decode($raw, true);

    if (isset($decoded['lastModified']) && isset($decoded['data'])) {
        // Already in new format — return as-is
        echo $raw;
    } else if (is_array($decoded)) {
        // Legacy format: raw array — wrap it
        echo json_encode(['lastModified' => 0, 'data' => $decoded]);
    } else {
        echo json_encode(['lastModified' => 0, 'data' => []]);
    }
    exit;
}

// ── POST: save data ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw     = file_get_contents('php://input');
    $decoded = json_decode($raw, true);

    if ($decoded === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Accept { lastModified, data } or legacy raw array
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        $payload = $decoded;
    } else if (is_array($decoded) && !isset($decoded['data'])) {
        $payload = ['lastModified' => (int)(microtime(true) * 1000), 'data' => $decoded];
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid format']);
        exit;
    }

    // Atomic write via temp file
    $tmp = $dataFile . '.tmp';
    if (file_put_contents($tmp, json_encode($payload), LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not write data file']);
        exit;
    }
    rename($tmp, $dataFile);

    echo json_encode([
        'ok'           => true,
        'lastModified' => $payload['lastModified'],
        'count'        => count($payload['data']),
    ]);
    exit;
}

// ── Other methods ────────────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
