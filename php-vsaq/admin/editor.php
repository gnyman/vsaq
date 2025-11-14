<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/renderer.php';

// Initialize database
initDB();

$db = getDB();
$message = '';
$error = '';
$questionnaire = null;

// Load existing questionnaire
if (isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT * FROM questionnaires WHERE id = :id');
    $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $questionnaire = $result->fetchArray(SQLITE3_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $description = $_POST['description'] ?? '';
    $template_json = $_POST['template_json'] ?? '';

    // Validate JSON
    $template = json_decode($template_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = 'Invalid JSON: ' . json_last_error_msg();
    } else {
        if (isset($_POST['id']) && $_POST['id']) {
            // Update existing
            $stmt = $db->prepare('UPDATE questionnaires SET title = :title, slug = :slug, description = :description, template_json = :template, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->bindValue(':id', $_POST['id'], SQLITE3_INTEGER);
        } else {
            // Create new
            $stmt = $db->prepare('INSERT INTO questionnaires (title, slug, description, template_json) VALUES (:title, :slug, :description, :template)');
        }

        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':template', $template_json, SQLITE3_TEXT);

        if ($stmt->execute()) {
            $message = 'Questionnaire saved successfully!';
            if (!isset($_POST['id']) || !$_POST['id']) {
                $id = $db->lastInsertRowID();
                header('Location: editor.php?id=' . $id);
                exit;
            } else {
                // Reload the questionnaire
                $stmt = $db->prepare('SELECT * FROM questionnaires WHERE id = :id');
                $stmt->bindValue(':id', $_POST['id'], SQLITE3_INTEGER);
                $result = $stmt->execute();
                $questionnaire = $result->fetchArray(SQLITE3_ASSOC);
            }
        } else {
            $error = 'Failed to save questionnaire.';
        }
    }
}

// Default template for new questionnaires
$default_template = [
    'questionnaire' => [
        [
            'type' => 'block',
            'text' => 'Sample Questionnaire',
            'id' => 'block_main',
            'items' => [
                [
                    'type' => 'info',
                    'text' => 'This is an example questionnaire. Edit the JSON on the left to customize it.'
                ],
                [
                    'type' => 'line',
                    'text' => 'What is your name?',
                    'id' => 'name',
                    'required' => true
                ],
                [
                    'type' => 'box',
                    'text' => 'Please describe your project:',
                    'id' => 'description'
                ],
                [
                    'type' => 'yesno',
                    'text' => 'Do you have security measures in place?',
                    'id' => 'has_security',
                    'required' => true
                ]
            ]
        ]
    ]
];

$current_template = $questionnaire ? $questionnaire['template_json'] : json_encode($default_template, JSON_PRETTY_PRINT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionnaire Editor</title>
    <link rel="stylesheet" href="../public/style.css">
    <style>
        .editor-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .editor-panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        .editor-textarea {
            width: 100%;
            min-height: 600px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .preview-panel {
            max-height: 800px;
            overflow-y: auto;
        }
        @media (max-width: 1024px) {
            .editor-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= $questionnaire ? 'Edit' : 'Create' ?> Questionnaire</h1>
        <p>Interactive Editor with Live Preview</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="editorForm">
        <input type="hidden" name="id" value="<?= $questionnaire ? $questionnaire['id'] : '' ?>">

        <div class="editor-panel" style="margin-bottom: 20px;">
            <div class="form-group">
                <label for="title">Questionnaire Title:</label>
                <input type="text" id="title" name="title" value="<?= $questionnaire ? e($questionnaire['title']) : '' ?>" required>
            </div>
            <div class="form-group">
                <label for="slug">Slug (URL-friendly name):</label>
                <input type="text" id="slug" name="slug" value="<?= $questionnaire ? e($questionnaire['slug']) : '' ?>" required pattern="[a-z0-9_-]+">
                <small>Use only lowercase letters, numbers, hyphens, and underscores</small>
            </div>
            <div class="form-group">
                <label for="description">Description (optional):</label>
                <textarea id="description" name="description" rows="2"><?= $questionnaire ? e($questionnaire['description']) : '' ?></textarea>
            </div>
        </div>

        <div class="editor-container">
            <div class="editor-panel">
                <h2>JSON Template</h2>
                <textarea id="template_json" name="template_json" class="editor-textarea"><?= e($current_template) ?></textarea>
                <div style="margin-top: 10px;">
                    <button type="button" onclick="updatePreview()" class="btn btn-secondary">Update Preview</button>
                    <button type="button" onclick="formatJSON()" class="btn btn-secondary">Format JSON</button>
                </div>
            </div>

            <div class="editor-panel preview-panel">
                <h2>Live Preview</h2>
                <div id="preview">
                    <?php
                    $renderer = new VSAQRenderer();
                    echo $renderer->renderQuestionnaire($current_template);
                    ?>
                </div>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-success">Save Questionnaire</button>
            <a href="index.php" class="btn btn-secondary">Back to Admin</a>
        </div>
    </form>

    <script>
    // Auto-generate slug from title
    document.getElementById('title').addEventListener('input', function() {
        if (!document.getElementById('slug').value || <?= $questionnaire ? 'false' : 'true' ?>) {
            let slug = this.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
            document.getElementById('slug').value = slug;
        }
    });

    // Update preview
    function updatePreview() {
        const templateJson = document.getElementById('template_json').value;

        try {
            JSON.parse(templateJson); // Validate JSON

            // Send AJAX request to render preview
            fetch('preview.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: templateJson
            })
            .then(response => response.text())
            .then(html => {
                document.getElementById('preview').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('preview').innerHTML = '<div class="alert alert-error">Failed to update preview</div>';
            });
        } catch (e) {
            document.getElementById('preview').innerHTML = '<div class="alert alert-error">Invalid JSON: ' + e.message + '</div>';
        }
    }

    // Format JSON
    function formatJSON() {
        const textarea = document.getElementById('template_json');
        try {
            const json = JSON.parse(textarea.value);
            textarea.value = JSON.stringify(json, null, 2);
            updatePreview();
        } catch (e) {
            alert('Invalid JSON: ' + e.message);
        }
    }

    // Auto-update preview on change (debounced)
    let previewTimeout;
    document.getElementById('template_json').addEventListener('input', function() {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(updatePreview, 1000);
    });
    </script>
</body>
</html>
