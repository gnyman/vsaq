-- VSAQ PHP Database Schema

CREATE TABLE IF NOT EXISTS questionnaires (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug VARCHAR(255) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    template_json TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    questionnaire_id INTEGER NOT NULL,
    magic_link VARCHAR(64) UNIQUE NOT NULL,
    answers_json TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id)
);

CREATE INDEX idx_magic_link ON responses(magic_link);
CREATE INDEX idx_questionnaire_slug ON questionnaires(slug);
