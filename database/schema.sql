-- PPMIS Database Schema
-- Project Progress and Management Information System
-- DOST Region VIII

CREATE DATABASE IF NOT EXISTS ppmis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ppmis;

-- Users Table
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Firms / Proponents Table
CREATE TABLE firms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firm_name VARCHAR(300) NOT NULL,
    contact_person VARCHAR(200),
    contact_email VARCHAR(191),
    contact_phone VARCHAR(50),
    address TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_firm_name (firm_name(100)),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Projects Table
CREATE TABLE projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firm_id INT UNSIGNED NOT NULL,
    project_title VARCHAR(400) NOT NULL,
    fund_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    approval_letter VARCHAR(500),         -- path to uploaded approval letter
    current_stage ENUM(
        'approval',
        'first_untagging',
        'final_untagging',
        'pre_refunding',
        'refunding',
        'completed',
        'terminated'
    ) NOT NULL DEFAULT 'approval',
    status ENUM('active','refund','graduated','terminated') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_firm (firm_id),
    INDEX idx_stage (current_stage),
    INDEX idx_status (status),
    FOREIGN KEY (firm_id) REFERENCES firms(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Submissions Table (one row per document per project)
CREATE TABLE submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    stage ENUM(
        'approval',
        'first_untagging',
        'final_untagging',
        'pre_refunding',
        'refunding',
        'terminated'
    ) NOT NULL,
    document_type ENUM(
        'ppis_letter',
        'release_letter',
        'pdc',
        'revised_annex_d',
        'ipo',
        'acknowledgement',
        'cert_first_untagging',
        'original_receipt',
        'matrix_of_inspection',
        'cert_final_untagging',
        'certified_true_copy',
        'audited_financial_report',
        'supporting_documents'
    ) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_filename VARCHAR(300) NOT NULL,
    file_size INT UNSIGNED,
    mime_type VARCHAR(100),
    submitted_by INT UNSIGNED,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    INDEX idx_project_stage (project_id, stage),
    INDEX idx_doc_type (document_type),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- PDCs Table (post-dated checks - special handling with date/amount)
CREATE TABLE pdcs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    pdc_number INT UNSIGNED NOT NULL,       -- 1, 2, 3 ... 36
    file_path VARCHAR(500),
    original_filename VARCHAR(300),
    check_date DATE NOT NULL,               -- original date from PDC
    adjusted_date DATE NOT NULL,            -- readjusted to end of month
    amount DECIMAL(12,2) NOT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    is_notified TINYINT(1) NOT NULL DEFAULT 0,
    notified_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    submitted_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_date (adjusted_date),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Refund Schedule Table (generated from PDCs)
CREATE TABLE refund_schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    pdc_id INT UNSIGNED,
    refund_date DATE NOT NULL,
    refund_amount DECIMAL(12,2) NOT NULL,
    is_notified TINYINT(1) NOT NULL DEFAULT 0,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    notified_at TIMESTAMP NULL,
    done_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_date (refund_date),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (pdc_id) REFERENCES pdcs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Audit / Activity Log
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    project_id INT UNSIGNED,
    action VARCHAR(200) NOT NULL,
    document_type ENUM 'approval_letter','ppis_letter','release_letter','pdc','revised_annex_d','ipo','acknowledgement','cert_first_untagging','original_receipt','matrix_of_inspection','cert_final_untagging','certified_true_copy','audited_financial_report','supporting_documents' DEFAULT NULL,
    file_path VARCHAR(500),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_project (project_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed default admin user (username: angelo, password: angelo123)
INSERT INTO users (username, email, password_hash, full_name, role)
VALUES (
    'angelo',
    'admin@dost8.gov.ph',
    '$2b$12$esRmshdfibEuV7/89ft/MOZRo2CRMbGNebZTX7FFjhb26WjyocYAO',
    'System Administrator',
    'admin'
);

-- Seed sample firms
INSERT INTO firms (firm_name, contact_person, contact_email, contact_phone, created_by) VALUES
('TechCorp Solutions Inc.', 'Juan dela Cruz', 'juan@techcorp.ph', '09171234567', 1),
('Visayas Research Group', 'Maria Santos', 'maria@vrg.ph', '09281234567', 1),
('Eastern Mindanao Innovations', 'Pedro Reyes', 'pedro@emi.ph', '09391234567', 1);
