<?php
/**
 * Bharat GPS HR System — Complete API Backend
 * ============================================
 * File: api.php
 * Place in: /hr/api.php  (same folder as index.html, or a subfolder)
 *
 * SETUP:
 * 1. Upload api.php to your server (e.g. public_html/hr/api.php)
 * 2. Create a 'data' folder next to it: public_html/hr/data/
 * 3. Set data folder permissions to 755 (writeable by web server)
 * 4. In index.html, set:  const API_URL = 'https://yourdomain.com/hr/api.php';
 * 5. Open the app, go to Admin Tools → click "Push Local Data to Server"
 *    (first time only, to migrate existing data)
 *
 * That's it. All users now share the same data from any device/location.
 */

// ── CORS — allow your domain (or * for any) ──
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

// ── Data folder ──
define('DATA_DIR', __DIR__ . '/data/');

if (!is_dir(DATA_DIR)) {
    if (!mkdir(DATA_DIR, 0755, true)) {
        jsonError('Cannot create data directory. Create it manually and chmod 755.', 500);
    }
}

// ── Route request ──
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'getall') {
        handleGetAll();
    } elseif ($action === 'get') {
        $key = $_GET['key'] ?? '';
        handleGet($key);
    } else {
        // Default GET = getall (for backward compat)
        handleGetAll();
    }
} elseif ($method === 'POST') {
    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);

    if (!$payload) {
        jsonError('Invalid JSON body', 400);
    }

    $action = $payload['action'] ?? 'set';

    if ($action === 'set') {
        $key   = $payload['key'] ?? '';
        $value = $payload['value'] ?? null;
        if (empty($key)) jsonError('key is required', 400);
        handleSet($key, $value);

    } elseif ($action === 'setall') {
        $data = $payload['data'] ?? [];
        if (!is_array($data)) jsonError('data must be object', 400);
        handleSetAll($data);

    } elseif ($action === 'get') {
        $key = $payload['key'] ?? '';
        handleGet($key);

    } else {
        jsonError('Unknown action: ' . $action, 400);
    }
} else {
    jsonError('Method not allowed', 405);
}

// ══════════════════════════════════════════
// HANDLERS
// ══════════════════════════════════════════

/**
 * GET ALL — returns all stored key/value pairs
 */
function handleGetAll() {
    $data = [];
    $files = glob(DATA_DIR . '*.json');
    if ($files) {
        foreach ($files as $file) {
            $key = basename($file, '.json');
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $val = json_decode($raw, true);
                if ($val !== null) $data[$key] = $val;
            }
        }
    }
    json_ok(['data' => $data, 'count' => count($data)]);
}

/**
 * GET ONE — returns a single key
 */
function handleGet($key) {
    $key = sanitizeKey($key);
    if (empty($key)) jsonError('key is required', 400);

    $file = DATA_DIR . $key . '.json';
    if (!file_exists($file)) {
        json_ok(['key' => $key, 'value' => null]);
        return;
    }

    $raw = @file_get_contents($file);
    if ($raw === false) jsonError('Failed to read file', 500);

    $value = json_decode($raw, true);
    json_ok(['key' => $key, 'value' => $value]);
}

/**
 * SET ONE — write a single key/value
 */
function handleSet($key, $value) {
    $key = sanitizeKey($key);
    if (empty($key)) jsonError('Invalid key', 400);

    $file = DATA_DIR . $key . '.json';
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // Atomic write via temp file
    $tmp = $file . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $json) === false) {
        @unlink($tmp);
        jsonError('Failed to write data. Check folder permissions (chmod 755 data/)', 500);
    }
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        jsonError('Failed to save file', 500);
    }

    json_ok(['key' => $key, 'saved' => true]);
}

/**
 * SET ALL — bulk write multiple keys (for migration from localStorage)
 */
function handleSetAll($data) {
    $saved = 0;
    $errors = [];

    foreach ($data as $key => $value) {
        $key = sanitizeKey($key);
        if (empty($key)) continue;

        $file = DATA_DIR . $key . '.json';
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $tmp  = $file . '.tmp.' . getmypid();

        if (file_put_contents($tmp, $json) !== false && rename($tmp, $file)) {
            $saved++;
        } else {
            @unlink($tmp);
            $errors[] = $key;
        }
    }

    json_ok(['ok' => true, 'saved' => $saved, 'errors' => $errors, 'count' => $saved]);
}

// ══════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════

/**
 * Sanitize key — only allow alphanumeric, dash, underscore
 * Prevents directory traversal or filesystem abuse
 */
function sanitizeKey($key) {
    return preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$key);
}

function json_ok($data) {
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
