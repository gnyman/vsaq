<?php
require_once __DIR__ . '/inc/config.php';

// Initialize database
initDB();

$db = getDB();

// Sample questionnaires
$questionnaires = [
    [
        'title' => 'Web Application Security Assessment',
        'slug' => 'webapp',
        'description' => 'Comprehensive security assessment for web applications',
        'template_file' => __DIR__ . '/../questionnaires/webapp.json'
    ],
    [
        'title' => 'Infrastructure Security Assessment',
        'slug' => 'infrastructure',
        'description' => 'Security questionnaire for infrastructure and cloud services',
        'template_file' => __DIR__ . '/../questionnaires/infrastructure.json'
    ],
    [
        'title' => 'Physical and Datacenter Security',
        'slug' => 'physical-datacenter',
        'description' => 'Assessment of physical and datacenter security controls',
        'template_file' => __DIR__ . '/../questionnaires/physical_and_datacenter.json'
    ],
    [
        'title' => 'Security and Privacy Programs',
        'slug' => 'security-privacy',
        'description' => 'Evaluation of security and privacy programs',
        'template_file' => __DIR__ . '/../questionnaires/security_privacy_programs.json'
    ],
    [
        'title' => 'Basic Security Questionnaire',
        'slug' => 'basic',
        'description' => 'A simple questionnaire for basic security assessment',
        'template' => [
            'questionnaire' => [
                [
                    'type' => 'block',
                    'text' => 'Company Information',
                    'id' => 'company_info',
                    'items' => [
                        [
                            'type' => 'line',
                            'text' => 'Company Name',
                            'id' => 'company_name',
                            'required' => true
                        ],
                        [
                            'type' => 'line',
                            'text' => 'Contact Email',
                            'id' => 'contact_email',
                            'required' => true
                        ],
                        [
                            'type' => 'box',
                            'text' => 'Company Description',
                            'id' => 'company_description'
                        ]
                    ]
                ],
                [
                    'type' => 'block',
                    'text' => 'Security Controls',
                    'id' => 'security_controls',
                    'items' => [
                        [
                            'type' => 'yesno',
                            'text' => 'Do you have a dedicated security team?',
                            'id' => 'has_security_team',
                            'required' => true
                        ],
                        [
                            'type' => 'yesno',
                            'text' => 'Do you perform regular security audits?',
                            'id' => 'performs_audits',
                            'required' => true
                        ],
                        [
                            'type' => 'check',
                            'text' => 'We encrypt data at rest',
                            'id' => 'encrypts_at_rest'
                        ],
                        [
                            'type' => 'check',
                            'text' => 'We encrypt data in transit',
                            'id' => 'encrypts_in_transit'
                        ],
                        [
                            'type' => 'radiogroup',
                            'text' => 'How often do you update your systems?',
                            'id' => 'update_frequency',
                            'choices' => ['Daily', 'Weekly', 'Monthly', 'Quarterly'],
                            'required' => true
                        ]
                    ]
                ]
            ]
        ]
    ],
    [
        'title' => 'Employee Onboarding Security',
        'slug' => 'employee-onboarding',
        'description' => 'Security procedures for new employee onboarding',
        'template' => [
            'questionnaire' => [
                [
                    'type' => 'block',
                    'text' => 'Employee Information',
                    'id' => 'employee_info',
                    'items' => [
                        [
                            'type' => 'line',
                            'text' => 'Full Name',
                            'id' => 'full_name',
                            'required' => true
                        ],
                        [
                            'type' => 'line',
                            'text' => 'Department',
                            'id' => 'department',
                            'required' => true
                        ],
                        [
                            'type' => 'line',
                            'text' => 'Start Date',
                            'id' => 'start_date',
                            'required' => true
                        ]
                    ]
                ],
                [
                    'type' => 'block',
                    'text' => 'Security Training',
                    'id' => 'security_training',
                    'items' => [
                        [
                            'type' => 'info',
                            'text' => 'All employees must complete security training within their first week.'
                        ],
                        [
                            'type' => 'check',
                            'text' => 'I have completed the security awareness training',
                            'id' => 'completed_awareness',
                            'required' => true
                        ],
                        [
                            'type' => 'check',
                            'text' => 'I have read and understood the acceptable use policy',
                            'id' => 'read_aup',
                            'required' => true
                        ],
                        [
                            'type' => 'check',
                            'text' => 'I have configured two-factor authentication',
                            'id' => 'configured_2fa',
                            'required' => true
                        ],
                        [
                            'type' => 'box',
                            'text' => 'Do you have any security concerns or questions?',
                            'id' => 'security_questions'
                        ]
                    ]
                ]
            ]
        ]
    ]
];

echo "Populating database with sample questionnaires...\n\n";

foreach ($questionnaires as $q) {
    // Check if questionnaire already exists
    $stmt = $db->prepare('SELECT id FROM questionnaires WHERE slug = :slug');
    $stmt->bindValue(':slug', $q['slug'], SQLITE3_TEXT);
    $result = $stmt->execute();
    $existing = $result->fetchArray(SQLITE3_ASSOC);

    if ($existing) {
        echo "Skipping '{$q['title']}' - already exists\n";
        continue;
    }

    // Load template
    if (isset($q['template_file']) && file_exists($q['template_file'])) {
        $template_json = file_get_contents($q['template_file']);
    } elseif (isset($q['template'])) {
        $template_json = json_encode($q['template']);
    } else {
        echo "Skipping '{$q['title']}' - no template found\n";
        continue;
    }

    // Insert questionnaire
    $stmt = $db->prepare('INSERT INTO questionnaires (title, slug, description, template_json) VALUES (:title, :slug, :description, :template)');
    $stmt->bindValue(':title', $q['title'], SQLITE3_TEXT);
    $stmt->bindValue(':slug', $q['slug'], SQLITE3_TEXT);
    $stmt->bindValue(':description', $q['description'], SQLITE3_TEXT);
    $stmt->bindValue(':template', $template_json, SQLITE3_TEXT);

    if ($stmt->execute()) {
        echo "✓ Added: {$q['title']}\n";
    } else {
        echo "✗ Failed to add: {$q['title']}\n";
    }
}

echo "\n\nCreating demo response link for 'Basic Security Questionnaire'...\n";

// Create a demo response for the basic questionnaire
$stmt = $db->prepare('SELECT id FROM questionnaires WHERE slug = :slug');
$stmt->bindValue(':slug', 'basic', SQLITE3_TEXT);
$result = $stmt->execute();
$basic_q = $result->fetchArray(SQLITE3_ASSOC);

if ($basic_q) {
    $magic_link = 'demo_' . bin2hex(random_bytes(16));

    $stmt = $db->prepare('INSERT INTO responses (questionnaire_id, magic_link) VALUES (:qid, :link)');
    $stmt->bindValue(':qid', $basic_q['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':link', $magic_link, SQLITE3_TEXT);

    if ($stmt->execute()) {
        echo "✓ Created demo magic link\n\n";
        echo "Demo Link: /php-vsaq/public/index.php?link=$magic_link\n";
        echo "\nYou can use this link to test the questionnaire without authentication!\n";
    }
}

echo "\n\nDatabase seeding complete!\n";
echo "Visit /php-vsaq/admin/ to manage questionnaires\n";
echo "Visit /php-vsaq/public/ to view questionnaires\n";
