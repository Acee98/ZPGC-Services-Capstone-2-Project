-- Technician applications (specialty + resume) after Outlook verify.
-- Safe to re-run: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS technician_applications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    specialty VARCHAR(32) NOT NULL,
    proposed_specialty VARCHAR(32) DEFAULT NULL,
    resume_stored_name VARCHAR(255) NOT NULL,
    resume_original_name VARCHAR(255) NOT NULL,
    resume_mime VARCHAR(128) NOT NULL DEFAULT 'application/octet-stream',
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    role_change_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_tech_app_user (user_id),
    KEY idx_tech_app_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
