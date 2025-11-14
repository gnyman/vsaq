<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/renderer.php';

// Initialize database
initDB();

$db = getDB();
$message = '';
$questionnaire = null;
$response = null;

// Handle magic link
if (isset($_GET['link'])) {
    $magic_link = $_GET['link'];
    $stmt = $db->prepare('SELECT r.*, q.* FROM responses r JOIN questionnaires q ON r.questionnaire_id = q.id WHERE r.magic_link = :link');
    $stmt->bindValue(':link', $magic_link, SQLITE3_TEXT);
    $result = $stmt->execute();
    $data = $result->fetchArray(SQLITE3_ASSOC);

    if ($data) {
        $questionnaire = $data;
        $response = $data;

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $answers = [];
            foreach ($_POST as $key => $value) {
                if ($key !== 'submit') {
                    $answers[$key] = $value;
                }
            }

            $stmt = $db->prepare('UPDATE responses SET answers_json = :answers, updated_at = CURRENT_TIMESTAMP WHERE magic_link = :link');
            $stmt->bindValue(':answers', json_encode($answers), SQLITE3_TEXT);
            $stmt->bindValue(':link', $magic_link, SQLITE3_TEXT);
            $stmt->execute();

            $message = 'Your responses have been saved successfully!';
            $response['answers_json'] = json_encode($answers);
        }
    } else {
        $message = 'Invalid or expired link.';
    }
} elseif (isset($_GET['q'])) {
    // Handle questionnaire slug
    $slug = $_GET['q'];
    $stmt = $db->prepare('SELECT * FROM questionnaires WHERE slug = :slug');
    $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
    $result = $stmt->execute();
    $questionnaire = $result->fetchArray(SQLITE3_ASSOC);

    if (!$questionnaire) {
        $message = 'Questionnaire not found.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $questionnaire ? e($questionnaire['title']) : 'VSAQ' ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h1>VSAQ - Vendor Security Assessment Questionnaire</h1>
        <p>PHP Edition</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= strpos($message, 'success') !== false ? 'success' : 'info' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($questionnaire): ?>
        <form method="POST" action="">
            <?php
            $renderer = new VSAQRenderer();
            if ($response && $response['answers_json']) {
                $renderer->setAnswers(json_decode($response['answers_json'], true));
            }
            echo $renderer->renderQuestionnaire($questionnaire['template_json']);
            ?>

            <div class="actions">
                <button type="submit" name="submit" class="btn btn-success">Save Responses</button>
                <a href="index.php" class="btn btn-secondary">Back to Home</a>
            </div>
        </form>
    <?php else: ?>
        <div class="vsaq-questionnaire">
            <h2>Welcome to VSAQ</h2>
            <p>Please use a valid questionnaire link or select from available questionnaires:</p>

            <?php
            $result = $db->query('SELECT id, slug, title, description FROM questionnaires ORDER BY created_at DESC');
            echo '<ul class="questionnaire-list">';
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                echo '<li>';
                echo '<a href="?q=' . urlencode($row['slug']) . '">' . e($row['title']) . '</a>';
                if ($row['description']) {
                    echo '<small>' . e($row['description']) . '</small>';
                }
                echo '</li>';
            }
            echo '</ul>';
            ?>

            <div class="actions">
                <a href="../admin/" class="btn">Go to Admin Panel</a>
            </div>
        </div>
    <?php endif; ?>

    <script>
    // Auto-save functionality
    if (document.querySelector('form')) {
        let saveTimeout;
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, textarea, select');

        inputs.forEach(input => {
            input.addEventListener('change', () => {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    console.log('Auto-saving...');
                    // Could add AJAX auto-save here
                }, 2000);
            });
        });
    }
    </script>
</body>
</html>
