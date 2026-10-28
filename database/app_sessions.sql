USE defaultdb;
CREATE TABLE IF NOT EXISTS app_sessions (
    session_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX idx_session_expiry (expires_at)
) ENGINE=InnoDB;
