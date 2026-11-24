CREATE TABLE IF NOT EXISTS admin_notification_reads (
    request_id INT NOT NULL,
    admin_id INT NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id, admin_id),
    CONSTRAINT fk_notification_reads_request FOREIGN KEY (request_id)
        REFERENCES admin_request_notifications(request_id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_reads_admin FOREIGN KEY (admin_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;