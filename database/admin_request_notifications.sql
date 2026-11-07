-- One new-request notification per request, shared by all maintenance admins.
CREATE TABLE IF NOT EXISTS admin_request_notifications (
    request_id INT NOT NULL PRIMARY KEY,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_notifications_created (created_at),
    CONSTRAINT fk_admin_notifications_request
        FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
