CREATE TABLE IF NOT EXISTS app_settings (
    k VARCHAR(50) NOT NULL PRIMARY KEY,
    v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO app_settings (k, v) VALUES
    ('auto_remarks_enabled', '1'),
    ('default_school_year', '2025-2026')
ON DUPLICATE KEY UPDATE
    v = VALUES(v);