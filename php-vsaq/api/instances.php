<?php
/**
 * Instance Management API Handlers
 * Handles questionnaire instance (sent questionnaire) operations
 */

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
