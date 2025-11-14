<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/renderer.php';

// Get JSON from POST body
$json = file_get_contents('php://input');

try {
    $template = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception(json_last_error_msg());
    }

    $renderer = new VSAQRenderer();
    echo $renderer->renderQuestionnaire($template);
} catch (Exception $e) {
    echo '<div class="alert alert-error">Error: ' . e($e->getMessage()) . '</div>';
}
