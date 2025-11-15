<?php
/**
 * Script to populate database with sample questionnaires
 * Run this once to seed the database with example templates
 */

require_once __DIR__ . '/src/Database.php';

// Initialize database
$db = Database::getInstance()->getPdo();

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

echo "Populating database with sample questionnaires...\n\n";

// First, check if admin exists (we need one for created_by field)
$stmt = $db->query("SELECT id FROM admins LIMIT 1");
$admin = $stmt->fetch();

if (!$admin) {
    echo "ERROR: No admin user found in database.\n";
    echo "Please create an admin account first by visiting the admin interface.\n";
    exit(1);
}

$adminId = $admin['id'];
echo "Using admin ID: $adminId\n\n";

$importedCount = 0;
$demoTemplateId = null;

foreach ($questionnaires as $q) {
    if (!file_exists($q['file'])) {
        echo "WARNING: File not found: {$q['file']}\n";
        continue;
    }

    // Read and clean JSON (remove comments)
    $jsonContent = file_get_contents($q['file']);

    // Remove // comments (but be careful with URLs)
    $lines = explode("\n", $jsonContent);
    $cleanedLines = [];
    foreach ($lines as $line) {
        // Remove lines that are just comments
        if (preg_match('/^\s*\/\//', $line)) {
            continue;
        }
        $cleanedLines[] = $line;
    }
    $jsonContent = implode("\n", $cleanedLines);

    // Validate JSON
    $parsed = json_decode($jsonContent);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "ERROR: Invalid JSON in {$q['file']}: " . json_last_error_msg() . "\n";
        continue;
    }

    // Check if template with this name already exists
    $stmt = $db->prepare("SELECT id FROM questionnaire_templates WHERE name = ?");
    $stmt->execute([$q['name']]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "SKIP: Template '{$q['name']}' already exists (ID: {$existing['id']})\n";
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
    echo "IMPORTED: {$q['name']} (ID: $templateId)\n";
    $importedCount++;

    // Save the first template ID for demo
    if ($q['name'] === 'Web Application Security') {
        $demoTemplateId = $templateId;
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "Import complete: $importedCount new templates added\n";

// Create demo instance
if ($demoTemplateId) {
    echo "\nCreating demo questionnaire instance...\n";

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

    $instanceId = $db->lastInsertId();

    echo "\nDEMO QUESTIONNAIRE CREATED!\n";
    echo str_repeat('=', 60) . "\n";
    echo "Template: Web Application Security\n";
    echo "Instance ID: $instanceId\n";
    echo "Magic Link: /php-vsaq/f/$uniqueLink\n";
    echo "\nYou can access the demo questionnaire without authentication at:\n";
    echo "http://your-server/php-vsaq/f/$uniqueLink\n";
    echo str_repeat('=', 60) . "\n";
} else {
    echo "\nWARNING: Could not create demo instance (Web Application Security template not found)\n";
}

echo "\nDone!\n";
