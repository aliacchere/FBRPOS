-- Additional tables for DPS POS FBR Integrated

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_name (name),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    credit_limit DECIMAL(10,2) DEFAULT 0.00,
    credit_used DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_name (name),
    INDEX idx_email (email),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update products table to include category_id and supplier_id
ALTER TABLE products ADD COLUMN IF NOT EXISTS category_id BIGINT UNSIGNED NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS supplier_id BIGINT UNSIGNED NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS description TEXT NULL;

-- Add foreign key constraints
ALTER TABLE products ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;
ALTER TABLE products ADD CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- Insert sample data
INSERT INTO categories (tenant_id, name, description) VALUES
(1, 'Electronics', 'Electronic devices and accessories'),
(1, 'Clothing', 'Apparel and fashion items'),
(1, 'Food & Beverages', 'Food and drink products'),
(1, 'Books', 'Books and educational materials'),
(1, 'Home & Garden', 'Home improvement and garden supplies');

INSERT INTO suppliers (tenant_id, name, email, phone, address) VALUES
(1, 'ABC Electronics Ltd', 'contact@abcelectronics.com', '+92-300-1234567', 'Karachi, Pakistan'),
(1, 'Fashion World', 'info@fashionworld.com', '+92-300-2345678', 'Lahore, Pakistan'),
(1, 'Food Distributors', 'sales@fooddist.com', '+92-300-3456789', 'Islamabad, Pakistan'),
(1, 'Book Publishers', 'orders@bookpub.com', '+92-300-4567890', 'Rawalpindi, Pakistan');

INSERT INTO customers (tenant_id, name, email, phone, address, credit_limit) VALUES
(1, 'Walk-in Customer', NULL, NULL, NULL, 0.00),
(1, 'John Doe', 'john@example.com', '+92-300-1111111', 'Karachi, Pakistan', 50000.00),
(1, 'Jane Smith', 'jane@example.com', '+92-300-2222222', 'Lahore, Pakistan', 30000.00),
(1, 'Ahmed Ali', 'ahmed@example.com', '+92-300-3333333', 'Islamabad, Pakistan', 25000.00);

-- Insert sample products
INSERT INTO products (tenant_id, name, sku, barcode, price, cost, stock_quantity, min_stock_level, category_id, supplier_id, description) VALUES
(1, 'Samsung Galaxy S21', 'SAMS21', '1234567890123', 150000.00, 120000.00, 10, 2, 1, 1, 'Latest Samsung smartphone'),
(1, 'iPhone 13', 'IPH13', '1234567890124', 180000.00, 150000.00, 8, 2, 1, 1, 'Apple iPhone 13'),
(1, 'Dell Laptop', 'DELL001', '1234567890125', 80000.00, 65000.00, 5, 1, 1, 1, 'Dell Inspiron laptop'),
(1, 'Nike T-Shirt', 'NIKE001', '1234567890126', 2500.00, 1500.00, 50, 10, 2, 2, 'Cotton t-shirt'),
(1, 'Adidas Shoes', 'ADIDAS001', '1234567890127', 8000.00, 5000.00, 25, 5, 2, 2, 'Running shoes'),
(1, 'Coca Cola', 'COKE001', '1234567890128', 50.00, 30.00, 200, 50, 3, 3, 'Soft drink'),
(1, 'Programming Book', 'BOOK001', '1234567890129', 1500.00, 800.00, 30, 5, 4, 4, 'Learn programming'),
(1, 'Garden Tools Set', 'GARDEN001', '1234567890130', 5000.00, 3000.00, 15, 3, 5, 1, 'Complete garden tools set');