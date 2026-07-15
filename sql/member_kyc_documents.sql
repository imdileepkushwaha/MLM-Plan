-- Per-document KYC (PAN / Bank / Aadhaar)
CREATE TABLE IF NOT EXISTS member_kyc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    doc_type ENUM('pan','bank','aadhar') NOT NULL,
    status ENUM('not_submitted','pending','approved','rejected') NOT NULL DEFAULT 'not_submitted',
    -- PAN
    pan_number VARCHAR(20) NULL,
    pan_name VARCHAR(100) NULL,
    -- Bank
    account_holder VARCHAR(100) NULL,
    account_number VARCHAR(50) NULL,
    ifsc_code VARCHAR(20) NULL,
    bank_name VARCHAR(100) NULL,
    branch_name VARCHAR(100) NULL,
    -- Aadhaar / Address
    aadhar_number VARCHAR(20) NULL,
    address_line TEXT NULL,
    -- File + review
    document_file VARCHAR(255) NULL,
    admin_note TEXT NULL,
    submitted_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_member_doc (member_id, doc_type),
    KEY idx_kyc_status (status),
    KEY idx_kyc_type (doc_type),
    CONSTRAINT fk_kyc_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extra Aadhaar columns (safe if table already exists)
ALTER TABLE member_kyc_documents
    ADD COLUMN IF NOT EXISTS document_back VARCHAR(255) NULL AFTER document_file,
    ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL AFTER address_line,
    ADD COLUMN IF NOT EXISTS state VARCHAR(100) NULL AFTER country,
    ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL AFTER state,
    ADD COLUMN IF NOT EXISTS area VARCHAR(100) NULL AFTER city,
    ADD COLUMN IF NOT EXISTS pincode VARCHAR(20) NULL AFTER area;
