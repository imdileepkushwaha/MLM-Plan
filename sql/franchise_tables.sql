-- Franchisee Master tables
CREATE TABLE IF NOT EXISTS franchisee_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(40) NULL,
    description TEXT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_franchisee_type_name (name),
    UNIQUE KEY uk_franchisee_type_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS franchisees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_id INT NOT NULL,
    sponsor_id INT NULL,
    franchisee_code VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(120) NULL,
    gender VARCHAR(20) NULL,
    dob DATE NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    aadhaar_no VARCHAR(20) NULL,
    pan_no VARCHAR(20) NULL,
    aadhaar_file VARCHAR(255) NULL,
    pan_file VARCHAR(255) NULL,
    photo_file VARCHAR(255) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    pincode VARCHAR(20) NULL,
    username VARCHAR(80) NULL,
    password VARCHAR(255) NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_by_role ENUM('admin','superadmin') DEFAULT 'admin',
    created_by_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_franchisee_code (franchisee_code),
    UNIQUE KEY uk_franchisee_username (username),
    KEY idx_franchisee_type (type_id),
    KEY idx_franchisee_sponsor (sponsor_id),
    KEY idx_franchisee_status (status),
    CONSTRAINT fk_franchisee_type FOREIGN KEY (type_id) REFERENCES franchisee_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS franchisee_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    franchisee_id INT NOT NULL,
    invoice_no VARCHAR(60) NULL,
    purchase_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    note TEXT NULL,
    status ENUM('pending','completed','cancelled') DEFAULT 'completed',
    created_by_role ENUM('admin','superadmin') DEFAULT 'admin',
    created_by_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fp_franchisee (franchisee_id),
    KEY idx_fp_date (purchase_date),
    CONSTRAINT fk_fp_franchisee FOREIGN KEY (franchisee_id) REFERENCES franchisees(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS franchisee_purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    KEY idx_fpi_purchase (purchase_id),
    KEY idx_fpi_product (product_id),
    CONSTRAINT fk_fpi_purchase FOREIGN KEY (purchase_id) REFERENCES franchisee_purchases(id) ON DELETE CASCADE,
    CONSTRAINT fk_fpi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS franchisee_stock (
    franchisee_id INT NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (franchisee_id, product_id),
    CONSTRAINT fk_fs_franchisee FOREIGN KEY (franchisee_id) REFERENCES franchisees(id) ON DELETE CASCADE,
    CONSTRAINT fk_fs_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
