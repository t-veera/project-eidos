<?php
/**
 * Project Eidos — NAS Data API
 * Reads and writes eidos-data.json in the same folder.
 *
 * GET  api.php  → returns stored projects JSON (or [] if none)
 * POST api.php  → saves request body as projects JSON
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$dataFile = __DIR__ . '/eidos-data.json';

// ── GET: return saved data ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($dataFile)) {
        readfile($dataFile);
    } else {
        echo '[]';
    }
    exit;
}

// ── POST: save data ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');

    // Validate JSON
    $decoded = json_decode($raw);
    if ($decoded === null || !is_array($decoded)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON array']);
        exit;
    }

    // Atomic write via temp file
    $tmp = $dataFile . '.tmp';
    if (file_put_contents($tmp, $raw, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not write data file']);
        exit;
    }
    rename($tmp, $dataFile);

    echo json_encode(['ok' => true, 'count' => count($decoded)]);
    exit;
}

// ── Other methods ────────────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
