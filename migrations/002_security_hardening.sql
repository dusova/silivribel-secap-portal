ALTER TABLE audit_log
    MODIFY action ENUM(
        'create',
        'update',
        'delete',
        'verify',
        'unverify',
        'login',
        'login_fail',
        'export',
        'access_denied',
        'role_change',
        'password_reset',
        'status_change'
    ) NOT NULL;

CREATE INDEX idx_login_user_ip_time ON login_attempts (username, ip_address, attempted_at);
CREATE INDEX idx_login_user_time ON login_attempts (username, attempted_at);
