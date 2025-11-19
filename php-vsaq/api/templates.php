<?php
/**
 * Template Management API Handlers
 * Handles questionnaire template CRUD operations
 */

function handleGetTemplates() {
    $db = Database::getInstance()->getPdo();

    $includeArchived = $_GET['archived'] ?? 'false';
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
