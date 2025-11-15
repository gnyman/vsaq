<?php
/**
 * VSAQ PHP - Main Entry Point with Admin Interface
 * A simple, dependency-free PHP implementation of Google's VSAQ
 */

// Start session
session_start();

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Note: CSP configured per-route as needed

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
            case '/api/admin/populate-samples':
                requireAuth();
                handlePopulateSamples();
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
// AUTH HANDLERS
// ============================================================================

function handleRegisterOptions() {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';

    if (empty($username)) {
        throw new Exception('Username required');
    }

    // Check if any admin already exists (only allow one admin)
    $db = Database::getInstance()->getPdo();
    $stmt = $db->query("SELECT COUNT(*) FROM admins");
    $adminCount = $stmt->fetchColumn();

    if ($adminCount > 0) {
        http_response_code(403);
        throw new Exception('An admin already exists. Only one admin account is allowed.');
    }

    $origin = getOrigin();
    $rpId = getValidatedHost();

    $webauthn = new WebAuthn($rpId, 'VSAQ Admin', $origin);
    $options = $webauthn->generateRegistrationOptions($username);

    echo json_encode($options);
}

function handleRegisterVerify() {
    $data = json_decode(file_get_contents('php://input'), true);
    $credential = $data['credential'] ?? null;
    $username = $data['username'] ?? '';

    if (!$credential || !$username) {
        throw new Exception('Invalid data');
    }

    $origin = getOrigin();
    $rpId = $_SERVER['HTTP_HOST'];

    $webauthn = new WebAuthn($rpId, 'VSAQ Admin', $origin);
    $webauthn->verifyRegistration($credential, $username);

    echo json_encode(['success' => true]);
}

function handleLoginOptions() {
    $origin = getOrigin();
    $rpId = $_SERVER['HTTP_HOST'];

    $webauthn = new WebAuthn($rpId, 'VSAQ Admin', $origin);
    $options = $webauthn->generateAuthenticationOptions();

    echo json_encode($options);
}

function handleLoginVerify() {
    $data = json_decode(file_get_contents('php://input'), true);
    $credential = $data['credential'] ?? null;

    if (!$credential) {
        throw new Exception('Invalid data');
    }

    $origin = getOrigin();
    $rpId = $_SERVER['HTTP_HOST'];

    $webauthn = new WebAuthn($rpId, 'VSAQ Admin', $origin);
    $adminId = $webauthn->verifyAuthentication($credential);

    if (!$adminId) {
        throw new Exception('Authentication failed');
    }

    $sessionId = $webauthn->createSession($adminId);

    setcookie('vsaq_session', $sessionId, [
        'expires' => time() + (7 * 24 * 60 * 60),
        'path' => '/',
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);

    echo json_encode(['success' => true]);
}

function handleLogout() {
    $sessionId = $_COOKIE['vsaq_session'] ?? null;

    if ($sessionId) {
        $origin = getOrigin();
        $rpId = $_SERVER['HTTP_HOST'];

        $webauthn = new WebAuthn($rpId, 'VSAQ Admin', $origin);
        $webauthn->deleteSession($sessionId);
    }

    setcookie('vsaq_session', '', time() - 3600, '/');
    echo json_encode(['success' => true]);
}

function handleAuthCheck() {
    $adminId = getCurrentAdmin();
    if ($adminId) {
        echo json_encode(['authenticated' => true, 'admin_id' => $adminId]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
}

function handleCanRegister() {
    $db = Database::getInstance()->getPdo();
    $stmt = $db->query("SELECT COUNT(*) FROM admins");
    $adminCount = $stmt->fetchColumn();

    echo json_encode(['can_register' => $adminCount === 0]);
}

// ============================================================================
// TEMPLATE HANDLERS
// ============================================================================

function handleGetTemplates() {
    $db = Database::getInstance()->getPdo();

    // Security: Validate input - only accept 'true' or 'false'
    $includeArchived = $_GET['archived'] ?? 'false';
    if ($includeArchived !== 'true' && $includeArchived !== 'false') {
        $includeArchived = 'false';
    }

    $sql = "SELECT t.*, a.username as created_by_username
            FROM questionnaire_templates t
            JOIN admins a ON t.created_by = a.id";

    if ($includeArchived === 'false') {
        $sql .= " WHERE t.is_archived = 0";
    }

    $sql .= " ORDER BY t.created_at DESC";

    $stmt = $db->query($sql);
    $templates = $stmt->fetchAll();

    echo json_encode($templates);
}

function handleGetTemplate($templateId) {
    $db = Database::getInstance()->getPdo();

    $stmt = $db->prepare("SELECT * FROM questionnaire_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();

    if (!$template) {
        http_response_code(404);
        echo json_encode(['error' => 'Template not found']);
        return;
    }

    echo json_encode($template);
}

function handleCreateTemplate() {
    $db = Database::getInstance()->getPdo();
    $adminId = getCurrentAdmin();
    $data = json_decode(file_get_contents('php://input'), true);

    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $content = $data['content'] ?? '';

    if (empty($name) || empty($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and content required']);
        return;
    }

    // Validate JSON
    json_decode($content);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON content']);
        return;
    }

    $stmt = $db->prepare("INSERT INTO questionnaire_templates (name, description, content, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $description, $content, $adminId, time(), time()]);

    $templateId = $db->lastInsertId();

    echo json_encode(['success' => true, 'id' => $templateId]);
}

function handleUpdateTemplate($templateId) {
    $db = Database::getInstance()->getPdo();
    $data = json_decode(file_get_contents('php://input'), true);

    // Check if template is already sent
    $stmt = $db->prepare("SELECT COUNT(*) FROM questionnaire_instances WHERE template_id = ? AND sent_at IS NOT NULL");
    $stmt->execute([$templateId]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot edit template that has been sent']);
        return;
    }

    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $content = $data['content'] ?? '';

    if (empty($name) || empty($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and content required']);
        return;
    }

    // Validate JSON
    json_decode($content);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON content']);
        return;
    }

    $stmt = $db->prepare("UPDATE questionnaire_templates SET name = ?, description = ?, content = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$name, $description, $content, time(), $templateId]);

    echo json_encode(['success' => true]);
}

function handleDeleteTemplate($templateId) {
    $db = Database::getInstance()->getPdo();

    // Check if template has instances
    $stmt = $db->prepare("SELECT COUNT(*) FROM questionnaire_instances WHERE template_id = ?");
    $stmt->execute([$templateId]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot delete template with instances. Archive it instead.']);
        return;
    }

    $stmt = $db->prepare("DELETE FROM questionnaire_templates WHERE id = ?");
    $stmt->execute([$templateId]);

    echo json_encode(['success' => true]);
}

function handleDuplicateTemplate($templateId) {
    $db = Database::getInstance()->getPdo();
    $adminId = getCurrentAdmin();

    $stmt = $db->prepare("SELECT * FROM questionnaire_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();

    if (!$template) {
        http_response_code(404);
        echo json_encode(['error' => 'Template not found']);
        return;
    }

    $newName = $template['name'] . ' (Copy)';
    $stmt = $db->prepare("INSERT INTO questionnaire_templates (name, description, content, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$newName, $template['description'], $template['content'], $adminId, time(), time()]);

    $newId = $db->lastInsertId();

    echo json_encode(['success' => true, 'id' => $newId]);
}

function handleArchiveTemplate($templateId) {
    $db = Database::getInstance()->getPdo();
    $data = json_decode(file_get_contents('php://input'), true);
    $archive = $data['archive'] ?? true;

    $stmt = $db->prepare("UPDATE questionnaire_templates SET is_archived = ? WHERE id = ?");
    $stmt->execute([$archive ? 1 : 0, $templateId]);

    echo json_encode(['success' => true]);
}

// ============================================================================
// INSTANCE HANDLERS
// ============================================================================

function handleGetInstances() {
    $db = Database::getInstance()->getPdo();

    $sql = "SELECT i.*, t.name as template_name, a.username as created_by_username,
            (SELECT COUNT(*) FROM answers WHERE instance_id = i.id) as answer_count
            FROM questionnaire_instances i
            JOIN questionnaire_templates t ON i.template_id = t.id
            JOIN admins a ON i.created_by = a.id
            ORDER BY i.created_at DESC";

    $stmt = $db->query($sql);
    $instances = $stmt->fetchAll();

    echo json_encode($instances);
}

function handleGetInstance($instanceId) {
    $db = Database::getInstance()->getPdo();

    $stmt = $db->prepare("
        SELECT i.*, t.name as template_name, t.content as template_content
        FROM questionnaire_instances i
        JOIN questionnaire_templates t ON i.template_id = t.id
        WHERE i.id = ?
    ");
    $stmt->execute([$instanceId]);
    $instance = $stmt->fetch();

    if (!$instance) {
        http_response_code(404);
        echo json_encode(['error' => 'Instance not found']);
        return;
    }

    // Get answers
    $stmt = $db->prepare("SELECT question_id, answer_value, updated_at, version FROM answers WHERE instance_id = ?");
    $stmt->execute([$instanceId]);
    $answers = $stmt->fetchAll();

    $instance['answers'] = $answers;

    echo json_encode($instance);
}

function handleCreateInstance() {
    $db = Database::getInstance()->getPdo();
    $adminId = getCurrentAdmin();
    $data = json_decode(file_get_contents('php://input'), true);

    $templateId = $data['template_id'] ?? 0;
    $targetName = $data['target_name'] ?? '';
    $targetEmail = $data['target_email'] ?? '';

    if (!$templateId) {
        http_response_code(400);
        echo json_encode(['error' => 'Template ID required']);
        return;
    }

    // Generate unique link
    $uniqueLink = bin2hex(random_bytes(16));

    $stmt = $db->prepare("INSERT INTO questionnaire_instances (template_id, unique_link, target_name, target_email, created_by, created_at, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$templateId, $uniqueLink, $targetName, $targetEmail, $adminId, time(), time()]);

    $instanceId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $instanceId,
        'unique_link' => $uniqueLink,
        'url' => '/php-vsaq/f/' . $uniqueLink
    ]);
}

function handleDeleteInstance($instanceId) {
    $db = Database::getInstance()->getPdo();

    // Check if submitted
    $stmt = $db->prepare("SELECT submitted_at FROM questionnaire_instances WHERE id = ?");
    $stmt->execute([$instanceId]);
    $instance = $stmt->fetch();

    if ($instance && $instance['submitted_at']) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot delete submitted instance']);
        return;
    }

    $stmt = $db->prepare("DELETE FROM questionnaire_instances WHERE id = ?");
    $stmt->execute([$instanceId]);

    echo json_encode(['success' => true]);
}

function handleUnlockInstance($instanceId) {
    $db = Database::getInstance()->getPdo();

    $stmt = $db->prepare("UPDATE questionnaire_instances SET is_locked = 0, submitted_at = NULL WHERE id = ?");
    $stmt->execute([$instanceId]);

    echo json_encode(['success' => true]);
}

// ============================================================================
// FORM FILLING HANDLERS
// ============================================================================

function handleGetFillData($uniqueLink) {
    $db = Database::getInstance()->getPdo();

    $stmt = $db->prepare("
        SELECT i.*, t.name as questionnaire_name, t.description as questionnaire_description, t.content as template_content
        FROM questionnaire_instances i
        JOIN questionnaire_templates t ON i.template_id = t.id
        WHERE i.unique_link = ?
    ");
    $stmt->execute([$uniqueLink]);
    $instance = $stmt->fetch();

    if (!$instance) {
        http_response_code(404);
        echo json_encode(['error' => 'Questionnaire not found']);
        return;
    }

    // Get answers
    $stmt = $db->prepare("SELECT question_id, answer_value, updated_at, version FROM answers WHERE instance_id = ?");
    $stmt->execute([$instance['id']]);
    $answersArr = $stmt->fetchAll();

    $answers = [];
    foreach ($answersArr as $answer) {
        $answers[$answer['question_id']] = [
            'value' => $answer['answer_value'],
            'version' => $answer['version'],
            'updated_at' => $answer['updated_at']
        ];
    }

    echo json_encode([
        'instance_id' => $instance['id'],
        'questionnaire_name' => $instance['questionnaire_name'],
        'questionnaire_description' => $instance['questionnaire_description'],
        'template_content' => $instance['template_content'],
        'is_locked' => (bool)$instance['is_locked'],
        'submitted_at' => $instance['submitted_at'],
        'answers' => $answers,
        'version' => $instance['version']
    ]);
}

function handleSaveFillData($uniqueLink) {
    $db = Database::getInstance()->getPdo();
    $data = json_decode(file_get_contents('php://input'), true);

    $questionId = $data['question_id'] ?? '';
    $answerValue = $data['answer_value'] ?? '';
    $clientVersion = $data['version'] ?? 1;

    if (empty($questionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Question ID required']);
        return;
    }

    // Get instance
    $stmt = $db->prepare("SELECT id, is_locked, version FROM questionnaire_instances WHERE unique_link = ?");
    $stmt->execute([$uniqueLink]);
    $instance = $stmt->fetch();

    if (!$instance) {
        http_response_code(404);
        echo json_encode(['error' => 'Questionnaire not found']);
        return;
    }

    if ($instance['is_locked']) {
        http_response_code(403);
        echo json_encode(['error' => 'Questionnaire is locked']);
        return;
    }

    // Check for conflicts
    $stmt = $db->prepare("SELECT version, updated_at FROM answers WHERE instance_id = ? AND question_id = ?");
    $stmt->execute([$instance['id'], $questionId]);
    $existingAnswer = $stmt->fetch();

    if ($existingAnswer && $existingAnswer['version'] > $clientVersion) {
        // Conflict detected
        echo json_encode([
            'conflict' => true,
            'server_version' => $existingAnswer['version'],
            'updated_at' => $existingAnswer['updated_at']
        ]);
        return;
    }

    // Save answer
    $newVersion = ($existingAnswer['version'] ?? 0) + 1;

    $stmt = $db->prepare("
        INSERT INTO answers (instance_id, question_id, answer_value, updated_at, version)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT(instance_id, question_id)
        DO UPDATE SET answer_value = ?, updated_at = ?, version = ?
    ");
    $stmt->execute([
        $instance['id'],
        $questionId,
        $answerValue,
        time(),
        $newVersion,
        $answerValue,
        time(),
        $newVersion
    ]);

    echo json_encode([
        'success' => true,
        'version' => $newVersion,
        'updated_at' => time()
    ]);
}

function handleSubmitForm($uniqueLink) {
    $db = Database::getInstance()->getPdo();

    // Get instance
    $stmt = $db->prepare("SELECT id, is_locked FROM questionnaire_instances WHERE unique_link = ?");
    $stmt->execute([$uniqueLink]);
    $instance = $stmt->fetch();

    if (!$instance) {
        http_response_code(404);
        echo json_encode(['error' => 'Questionnaire not found']);
        return;
    }

    if ($instance['is_locked']) {
        http_response_code(403);
        echo json_encode(['error' => 'Questionnaire already submitted']);
        return;
    }

    // Lock and mark as submitted
    $stmt = $db->prepare("UPDATE questionnaire_instances SET is_locked = 1, submitted_at = ? WHERE id = ?");
    $stmt->execute([time(), $instance['id']]);

    echo json_encode(['success' => true]);
}

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

    // Security: Validate HTTP_HOST to prevent header injection
    $host = getValidatedHost();
    return $protocol . '://' . $host;
}

function getValidatedHost() {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

    // Security: Validate hostname format to prevent injection
    // Allow only alphanumeric, dots, hyphens, and port numbers
    if (!preg_match('/^[a-zA-Z0-9.-]+(:[0-9]+)?$/', $host)) {
        // Invalid host format, use SERVER_NAME or default
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    }

    // Additional validation: Check against allowed hosts if configured
    // In production, you should whitelist allowed hostnames
    // For now, we'll just ensure it doesn't contain dangerous characters

    return $host;
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
    // Security: Prevent path traversal attacks
    // Build the full path
    $basePath = __DIR__;
    $requestedPath = $basePath . $path;

    // Resolve to real path (eliminates ../ and symbolic links)
    $realPath = realpath($requestedPath);

    // Check if file exists and is within allowed directory
    if (!$realPath || !file_exists($realPath) || is_dir($realPath)) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    // Security: Ensure the resolved path is within the base directory
    // This prevents directory traversal attacks like /admin/../../etc/passwd
    if (strpos($realPath, $basePath) !== 0) {
        http_response_code(403);
        echo '403 Forbidden';
        return;
    }

    // Additional security: Only serve files from specific allowed directories
    $allowedDirs = [
        $basePath . '/admin',
        $basePath . '/public'
    ];

    $isAllowed = false;
    foreach ($allowedDirs as $allowedDir) {
        $realAllowedDir = realpath($allowedDir);
        if ($realAllowedDir && strpos($realPath, $realAllowedDir) === 0) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        http_response_code(403);
        echo '403 Forbidden';
        return;
    }

    $extension = pathinfo($realPath, PATHINFO_EXTENSION);
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
    readfile($realPath);
}

function handlePopulateSamples() {
    $db = Database::getInstance()->getPdo();
    $adminId = getCurrentAdmin();

    // Sample questionnaires to import
    $questionnaires = [
        [
            'file' => __DIR__ . '/../questionnaires/webapp.json',
            'name' => 'Web Application Security',
            'description' => 'Comprehensive security assessment for web applications covering HTTPS, authentication, data handling, and more.'
        ],
        [
            'file' => __DIR__ . '/../questionnaires/infrastructure.json',
            'name' => 'Infrastructure Security',
            'description' => 'Assessment of network infrastructure, firewalls, access controls, and system hardening practices.'
        ],
        [
            'file' => __DIR__ . '/../questionnaires/security_privacy_programs.json',
            'name' => 'Security and Privacy Programs',
            'description' => 'Evaluation of security and privacy policies, procedures, training, and governance.'
        ],
        [
            'file' => __DIR__ . '/../questionnaires/physical_and_datacenter.json',
            'name' => 'Physical and Datacenter Security',
            'description' => 'Physical security controls, datacenter access, environmental controls, and asset management.'
        ],
        [
            'file' => __DIR__ . '/../questionnaires/test_template.json',
            'name' => 'Test Template (Simple)',
            'description' => 'A simple test template for trying out VSAQ features.'
        ],
        [
            'file' => __DIR__ . '/../questionnaires/test_template_extension.json',
            'name' => 'Test Template Extension',
            'description' => 'Extension example showing how to build on existing templates.'
        ]
    ];

    $imported = [];
    $skipped = [];
    $errors = [];
    $demoTemplateId = null;

    foreach ($questionnaires as $q) {
        if (!file_exists($q['file'])) {
            $errors[] = "File not found: " . basename($q['file']);
            continue;
        }

        // Read and clean JSON (remove comment lines)
        $jsonContent = file_get_contents($q['file']);

        // Remove lines that start with // (these are comments, not JSON)
        // JSON strings containing "http://" will have quotes around them
        $lines = explode("\n", $jsonContent);
        $cleanedLines = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            // Skip comment-only lines (start with //)
            // Real JSON with URLs will have quotes: "http://..."
            if (strpos($trimmed, '//') === 0) {
                continue;
            }
            $cleanedLines[] = $line;
        }
        $jsonContent = implode("\n", $cleanedLines);

        // Validate JSON
        $parsed = json_decode($jsonContent);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "Invalid JSON in " . basename($q['file']) . ": " . json_last_error_msg();
            continue;
        }

        // Check if template with this name already exists
        $stmt = $db->prepare("SELECT id FROM questionnaire_templates WHERE name = ?");
        $stmt->execute([$q['name']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $skipped[] = $q['name'];
            if ($q['name'] === 'Web Application Security') {
                $demoTemplateId = $existing['id'];
            }
            continue;
        }

        // Insert template
        $stmt = $db->prepare("
            INSERT INTO questionnaire_templates (name, description, content, created_by, created_at, updated_at, is_archived)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");

        $now = time();
        $stmt->execute([
            $q['name'],
            $q['description'],
            $jsonContent,
            $adminId,
            $now,
            $now
        ]);

        $templateId = $db->lastInsertId();
        $imported[] = $q['name'];

        // Save the first template ID for demo
        if ($q['name'] === 'Web Application Security') {
            $demoTemplateId = $templateId;
        }
    }

    // Create demo instance if we have the Web App template
    $demoLink = null;
    if ($demoTemplateId) {
        // Check if demo already exists
        $stmt = $db->prepare("SELECT unique_link FROM questionnaire_instances WHERE unique_link LIKE 'demo-%' AND template_id = ? LIMIT 1");
        $stmt->execute([$demoTemplateId]);
        $existingDemo = $stmt->fetch();

        if ($existingDemo) {
            $demoLink = '/php-vsaq/f/' . $existingDemo['unique_link'];
        } else {
            // Generate unique link
            $uniqueLink = 'demo-' . bin2hex(random_bytes(12));

            $stmt = $db->prepare("
                INSERT INTO questionnaire_instances (template_id, unique_link, target_name, target_email, created_by, created_at, sent_at, is_locked, submitted_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, NULL)
            ");

            $now = time();
            $stmt->execute([
                $demoTemplateId,
                $uniqueLink,
                'Demo Company',
                'demo@example.com',
                $adminId,
                $now,
                $now
            ]);

            $demoLink = '/php-vsaq/f/' . $uniqueLink;
        }
    }

    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'demo_link' => $demoLink
    ]);
}
