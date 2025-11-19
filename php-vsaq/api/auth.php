<?php
/**
 * Authentication API Handlers
 * Handles WebAuthn registration, login, and session management
 */

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
    $rpId = $_SERVER['HTTP_HOST'];

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
