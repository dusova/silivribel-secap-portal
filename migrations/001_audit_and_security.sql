CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED             COMMENT 'İşlemi yapan kullanıcı',
    action      ENUM('create','update','delete','verify','unverify','login','login_fail') NOT NULL,
    entity_type VARCHAR(50)     NOT NULL,
    entity_id   INT UNSIGNED    NOT NULL,
    old_value   JSON                     NULL,
    new_value   JSON                     NULL,
    ip_address  VARCHAR(45)              NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_user   (user_id),
    INDEX idx_audit_time   (created_at),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(45)  NOT NULL,
    username     VARCHAR(80)  NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_login_ip_time (ip_address, attempted_at),
    INDEX idx_login_time    (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE INDEX idx_entries_dept_year ON data_entries (department_id, year);
