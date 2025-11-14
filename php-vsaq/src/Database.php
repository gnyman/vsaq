<?php
/**
 * Database Setup Script
 * Creates SQLite database with all necessary tables
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dbPath = __DIR__ . '/data/vsaq.db';
        $dbDir = dirname($dbPath);

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createTables();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }

    private function createTables() {
        // Admins table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                created_at INTEGER NOT NULL,
                last_login INTEGER
            )
        ");

        // WebAuthn credentials table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS webauthn_credentials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_id INTEGER NOT NULL,
                credential_id TEXT UNIQUE NOT NULL,
                public_key TEXT NOT NULL,
                counter INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL,
                FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
            )
        ");

        // Questionnaire templates table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS questionnaire_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                content TEXT NOT NULL,
                created_by INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                is_archived INTEGER DEFAULT 0,
                FOREIGN KEY (created_by) REFERENCES admins(id)
            )
        ");

        // Questionnaire instances (sent to specific targets)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS questionnaire_instances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                template_id INTEGER NOT NULL,
                unique_link TEXT UNIQUE NOT NULL,
                target_name TEXT,
                target_email TEXT,
                created_by INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                sent_at INTEGER,
                submitted_at INTEGER,
                is_locked INTEGER DEFAULT 0,
                version INTEGER DEFAULT 1,
                FOREIGN KEY (template_id) REFERENCES questionnaire_templates(id),
                FOREIGN KEY (created_by) REFERENCES admins(id)
            )
        ");

        // Answers table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS answers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                instance_id INTEGER NOT NULL,
                question_id TEXT NOT NULL,
                answer_value TEXT,
                updated_at INTEGER NOT NULL,
                version INTEGER DEFAULT 1,
                UNIQUE(instance_id, question_id),
                FOREIGN KEY (instance_id) REFERENCES questionnaire_instances(id) ON DELETE CASCADE
            )
        ");

        // Sessions table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                admin_id INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL,
                FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
            )
        ");

        // WebAuthn challenges (temporary storage)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS webauthn_challenges (
                challenge TEXT PRIMARY KEY,
                username TEXT,
                created_at INTEGER NOT NULL
            )
        ");

        // Create indexes for performance
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_instances_link ON questionnaire_instances(unique_link)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_answers_instance ON answers(instance_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_admin ON sessions(admin_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at)");
    }

    public function cleanExpiredSessions() {
        $this->pdo->exec("DELETE FROM sessions WHERE expires_at < " . time());
    }

    public function cleanExpiredChallenges() {
        $this->pdo->exec("DELETE FROM webauthn_challenges WHERE created_at < " . (time() - 300)); // 5 minutes
    }
}
