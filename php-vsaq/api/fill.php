<?php
/**
 * Form Filling API Handlers
 * Handles questionnaire filling and submission
 */

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
