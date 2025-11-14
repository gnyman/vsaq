<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/renderer.php';

// Initialize database
initDB();

$db = getDB();
$message = '';
$error = '';

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'delete':
            if (isset($_GET['id'])) {
                $stmt = $db->prepare('DELETE FROM questionnaires WHERE id = :id');
                $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
                $stmt->execute();

                $stmt = $db->prepare('DELETE FROM responses WHERE questionnaire_id = :id');
                $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
                $stmt->execute();

                $message = 'Questionnaire deleted successfully!';
            }
            break;

        case 'create_response':
            if (isset($_GET['id'])) {
                $magic_link = generateMagicLink();
                $stmt = $db->prepare('INSERT INTO responses (questionnaire_id, magic_link) VALUES (:qid, :link)');
                $stmt->bindValue(':qid', $_GET['id'], SQLITE3_INTEGER);
                $stmt->bindValue(':link', $magic_link, SQLITE3_TEXT);
                $stmt->execute();

                header('Location: ?action=view_response&link=' . $magic_link);
                exit;
            }
            break;
    }
}

// Get list of questionnaires
$questionnaires = [];
$result = $db->query('SELECT * FROM questionnaires ORDER BY created_at DESC');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $questionnaires[] = $row;
}

// View specific response
$response_data = null;
if (isset($_GET['link'])) {
    $stmt = $db->prepare('SELECT r.*, q.title as q_title, q.slug FROM responses r JOIN questionnaires q ON r.questionnaire_id = q.id WHERE r.magic_link = :link');
    $stmt->bindValue(':link', $_GET['link'], SQLITE3_TEXT);
    $result = $stmt->execute();
    $response_data = $result->fetchArray(SQLITE3_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VSAQ Admin Panel</title>
    <link rel="stylesheet" href="../public/style.css">
</head>
<body>
    <div class="header">
        <h1>VSAQ Admin Panel</h1>
        <p>Manage Questionnaires and Responses</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['link']) && $response_data): ?>
        <!-- View Response -->
        <div class="vsaq-questionnaire">
            <h2>Response Link for: <?= e($response_data['q_title']) ?></h2>
            <div class="alert alert-info">
                <strong>Magic Link:</strong><br>
                <div class="magic-link">
                    <?php
                    $full_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/public/index.php?link=' . $response_data['magic_link'];
                    echo e($full_link);
                    ?>
                </div>
            </div>
            <div class="actions">
                <a href="<?= e($full_link) ?>" target="_blank" class="btn">Open Link</a>
                <button onclick="navigator.clipboard.writeText('<?= e($full_link) ?>'); alert('Link copied to clipboard!');" class="btn btn-secondary">Copy Link</button>
                <a href="?" class="btn btn-secondary">Back to Admin</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Questionnaire List -->
        <div class="vsaq-questionnaire">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Questionnaires</h2>
                <a href="editor.php" class="btn">Create New Questionnaire</a>
            </div>

            <?php if (empty($questionnaires)): ?>
                <p>No questionnaires found. <a href="editor.php">Create your first questionnaire</a>.</p>
            <?php else: ?>
                <ul class="questionnaire-list">
                    <?php foreach ($questionnaires as $q): ?>
                        <li>
                            <a href="editor.php?id=<?= $q['id'] ?>"><?= e($q['title']) ?></a>
                            <?php if ($q['description']): ?>
                                <small><?= e($q['description']) ?></small>
                            <?php endif; ?>
                            <div class="inline-actions">
                                <a href="../public/index.php?q=<?= urlencode($q['slug']) ?>" target="_blank">View</a>
                                <a href="editor.php?id=<?= $q['id'] ?>">Edit</a>
                                <a href="?action=create_response&id=<?= $q['id'] ?>">Create Response Link</a>
                                <a href="?action=delete&id=<?= $q['id'] ?>" onclick="return confirm('Are you sure you want to delete this questionnaire?');" style="color: #e74c3c;">Delete</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="actions">
                <a href="../public/index.php" class="btn btn-secondary">Go to Public View</a>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>
