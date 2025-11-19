<?php
/**
 * VSAQ PHP - Main Entry Point with Admin Interface
 * A simple, dependency-free PHP implementation of Google's VSAQ
 */

// Start session
session_start();

// Auto-load classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize database
$db = Database::getInstance();
$db->cleanExpiredSessions();
$db->cleanExpiredChallenges();

// Get request info
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/php-vsaq', '', $path);

// Root redirect
if ($path === '/' || $path === '/index.php' || $path === '') {
    header('Location: /php-vsaq/admin/');
    exit;
}

// Admin routes
if (strpos($path, '/admin') === 0) {
    serveStaticFile($path);
    exit;
}

// Fill form route (/f/{unique_link})
if (preg_match('#^/f/([a-zA-Z0-9_-]+)$#', $path, $matches)) {
    serveFillForm($matches[1]);
    exit;
}

// API endpoints
if (strpos($path, '/api/') === 0) {
    header('Content-Type: application/json');

    // Include API handlers
    require_once __DIR__ . '/api/auth.php';
    require_once __DIR__ . '/api/templates.php';
    require_once __DIR__ . '/api/instances.php';
    require_once __DIR__ . '/api/fill.php';

    try {
        switch ($path) {
            // Auth endpoints
            case '/api/auth/register/options':
                handleRegisterOptions();
                break;
            case '/api/auth/register/verify':
                handleRegisterVerify();
                break;
            case '/api/auth/login/options':
                handleLoginOptions();
                break;
            case '/api/auth/login/verify':
                handleLoginVerify();
                break;
            case '/api/auth/logout':
                handleLogout();
                break;
            case '/api/auth/check':
                handleAuthCheck();
                break;
            case '/api/auth/can-register':
                handleCanRegister();
                break;

            // Admin - Questionnaire Templates
            case '/api/admin/templates':
                requireAuth();
                if ($method === 'GET') handleGetTemplates();
                elseif ($method === 'POST') handleCreateTemplate();
                break;
            case (preg_match('#^/api/admin/templates/(\d+)$#', $path, $m) ? true : false):
                requireAuth();
                $templateId = $m[1];
                if ($method === 'GET') handleGetTemplate($templateId);
                elseif ($method === 'PUT') handleUpdateTemplate($templateId);
                elseif ($method === 'DELETE') handleDeleteTemplate($templateId);
                break;
            case (preg_match('#^/api/admin/templates/(\d+)/duplicate$#', $path, $m) ? true : false):
                requireAuth();
                handleDuplicateTemplate($m[1]);
                break;
            case (preg_match('#^/api/admin/templates/(\d+)/archive$#', $path, $m) ? true : false):
                requireAuth();
                handleArchiveTemplate($m[1]);
                break;

            // Admin - Questionnaire Instances
            case '/api/admin/instances':
                requireAuth();
                if ($method === 'GET') handleGetInstances();
                elseif ($method === 'POST') handleCreateInstance();
                break;
            case (preg_match('#^/api/admin/instances/(\d+)$#', $path, $m) ? true : false):
                requireAuth();
                $instanceId = $m[1];
                if ($method === 'GET') handleGetInstance($instanceId);
                elseif ($method === 'DELETE') handleDeleteInstance($instanceId);
                break;
            case (preg_match('#^/api/admin/instances/(\d+)/unlock$#', $path, $m) ? true : false):
                requireAuth();
                handleUnlockInstance($m[1]);
                break;

            // Form filling endpoints
            case (preg_match('#^/api/fill/([a-zA-Z0-9_-]+)$#', $path, $m) ? true : false):
                handleGetFillData($m[1]);
                break;
            case (preg_match('#^/api/fill/([a-zA-Z0-9_-]+)/save$#', $path, $m) ? true : false):
                handleSaveFillData($m[1]);
                break;
            case (preg_match('#^/api/fill/([a-zA-Z0-9_-]+)/submit$#', $path, $m) ? true : false):
                handleSubmitForm($m[1]);
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Serve static files
serveStaticFile($path);

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function getOrigin() {
    // Check for common proxy headers that indicate HTTPS
    $isHttps = false;

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $isHttps = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $isHttps = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        $isHttps = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $isHttps = true;
    }

    $protocol = $isHttps ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'];
}

function getCurrentAdmin() {
    $sessionId = $_COOKIE['vsaq_session'] ?? null;

    if (!$sessionId) {
        return false;
    }

    $origin = getOrigin();
    $rpId = $_SERVER['HTTP_HOST'];

    $webauthn = new WebAuthn($rpId, 'VSAQ Admin', $origin);
    return $webauthn->verifySession($sessionId);
}

function requireAuth() {
    $adminId = getCurrentAdmin();
    if (!$adminId) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $adminId;
}

function serveFillForm($uniqueLink) {
    // Serve the fill form HTML
    $filePath = __DIR__ . '/public/fill.html';
    if (file_exists($filePath)) {
        header('Content-Type: text/html');
        readfile($filePath);
    } else {
        http_response_code(404);
        echo '404 Not Found';
    }
}

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
