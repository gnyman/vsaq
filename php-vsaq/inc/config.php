<?php
// VSAQ PHP Configuration

define('DB_PATH', __DIR__ . '/../vsaq.db');
define('BASE_URL', '/php-vsaq');

// Database connection
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_PATH);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode=WAL');
    }
    return $db;
}

// Initialize database
function initDB() {
    $db = getDB();
    $schema = file_get_contents(__DIR__ . '/../schema.sql');
    $db->exec($schema);
}

// Generate magic link
function generateMagicLink() {
    return bin2hex(random_bytes(32));
}

// Escape HTML
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
