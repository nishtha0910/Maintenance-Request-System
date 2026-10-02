ALTER TABLE users
ADD COLUMN locked_until DATETIME NULL DEFAULT NULL;

CREATE TABLE login_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_login_attempts_user_time (user_id, attempted_at),
    CONSTRAINT fk_login_attempts_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
);