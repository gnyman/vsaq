<?php
/**
 * VSAQ PHP - Main Entry Point
 * A simple, dependency-free PHP implementation of Google's VSAQ
 */

// Simple router
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query string
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/php-vsaq', '', $path);

// Route requests
if ($path === '/' || $path === '/index.php' || $path === '') {
    header('Location: /php-vsaq/public/index.html');
    exit;
}

// API endpoints
if (strpos($path, '/api/') === 0) {
    header('Content-Type: application/json');

    switch ($path) {
        case '/api/questionnaire':
            handleQuestionnaireRequest();
            break;
        case '/api/save':
            handleSaveRequest();
            break;
        case '/api/load':
            handleLoadRequest();
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
    }
    exit;
}

// Serve static files
serveStaticFile($path);

/**
 * Load a questionnaire template
 */
function handleQuestionnaireRequest() {
    $qpath = $_GET['qpath'] ?? '';

    if (empty($qpath)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing qpath parameter']);
        return;
    }

    // Security: prevent path traversal
    $qpath = str_replace(['..', '\\'], '', $qpath);
    $filepath = __DIR__ . '/../' . $qpath;

    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Questionnaire not found']);
        return;
    }

    $content = file_get_contents($filepath);

    // Remove JavaScript-style comments from JSON
    $content = preg_replace('~//.+~', '', $content); // Remove single-line comments
    $content = preg_replace('~/\*.*?\*/~s', '', $content); // Remove multi-line comments

    $json = json_decode($content, true);

    if ($json === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        return;
    }

    echo json_encode($json);
}

/**
 * Save answers (optional backend storage)
 */
function handleSaveRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['id']) || !isset($data['answers'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }

    $saveDir = __DIR__ . '/data/answers';
    if (!is_dir($saveDir)) {
        mkdir($saveDir, 0755, true);
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['id']) . '.json';
    $filepath = $saveDir . '/' . $filename;

    file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true, 'id' => $data['id']]);
}

/**
 * Load saved answers
 */
function handleLoadRequest() {
    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id parameter']);
        return;
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.json';
    $filepath = __DIR__ . '/data/answers/' . $filename;

    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Answers not found']);
        return;
    }

    $content = file_get_contents($filepath);
    echo $content;
}

/**
 * Serve static files
 */
function serveStaticFile($path) {
    $filepath = __DIR__ . $path;

    if (!file_exists($filepath) || is_dir($filepath)) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $extension = pathinfo($filepath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];

    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    header("Content-Type: $mimeType");
    readfile($filepath);
}
