-- MedikaFlow: persistent login throttling for installations that already
-- have the base schema. Safe to run more than once.
CREATE TABLE IF NOT EXISTS auth_login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_lookup (identifier_hash, ip_address, attempted_at),
    INDEX idx_login_attempts_ip_time (ip_address, attempted_at),
    INDEX idx_login_attempts_time (attempted_at)
) ENGINE=InnoDB;
