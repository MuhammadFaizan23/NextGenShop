-- NextGenShop Database Schema
-- Performance-optimized with proper indexes

CREATE DATABASE IF NOT EXISTS nextgenshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nextgenshop;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    avatar VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_parent (parent_id),
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    short_description VARCHAR(500) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2) DEFAULT NULL,
    stock INT NOT NULL DEFAULT 0,
    image VARCHAR(255) DEFAULT NULL,
    images JSON DEFAULT NULL,
    sku VARCHAR(100) DEFAULT NULL UNIQUE,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive', 'draft') NOT NULL DEFAULT 'active',
    views INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_featured (is_featured),
    INDEX idx_price (price),
    INDEX idx_created (created_at),
    FULLTEXT INDEX idx_search (name, description, short_description),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    session_id VARCHAR(128) DEFAULT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_session (session_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Wishlist table
CREATE TABLE IF NOT EXISTS wishlist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_product (user_id, product_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    total DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_name VARCHAR(100) NOT NULL,
    shipping_email VARCHAR(150) NOT NULL,
    shipping_phone VARCHAR(20) DEFAULT NULL,
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(100) NOT NULL,
    shipping_state VARCHAR(100) DEFAULT NULL,
    shipping_zip VARCHAR(20) DEFAULT NULL,
    shipping_country VARCHAR(100) NOT NULL DEFAULT 'US',
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_image VARCHAR(255) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,2) NOT NULL,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Product reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(200) DEFAULT NULL,
    body TEXT DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_product (user_id, product_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample categories
INSERT INTO categories (name, slug, description) VALUES
('Electronics', 'electronics', 'Latest gadgets and electronic devices'),
('Clothing', 'clothing', 'Fashion and apparel for all'),
('Books', 'books', 'Wide range of books across all genres'),
('Home & Garden', 'home-garden', 'Everything for your home and garden'),
('Sports', 'sports', 'Sports equipment and activewear');

-- Sample products
INSERT INTO products (category_id, name, slug, description, short_description, price, sale_price, stock, image, is_featured, sku) VALUES
(1, 'Wireless Noise-Cancelling Headphones', 'wireless-noise-cancelling-headphones', 'Experience premium sound quality with our state-of-the-art wireless headphones. Featuring active noise cancellation, 30-hour battery life, and ultra-comfortable ear cushions for extended listening sessions.', 'Premium wireless headphones with ANC and 30hr battery.', 149.99, 119.99, 50, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80', 1, 'ELEC-001'),
(1, 'Smart Watch Pro', 'smart-watch-pro', 'Stay connected with our Smart Watch Pro. Track your fitness, receive notifications, monitor your heart rate and sleep patterns. Water-resistant with a stunning AMOLED display.', 'Advanced smartwatch with health tracking and AMOLED display.', 299.99, NULL, 30, 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&q=80', 1, 'ELEC-002'),
(1, 'Portable Bluetooth Speaker', 'portable-bluetooth-speaker', 'Take your music anywhere with our portable Bluetooth speaker. 360-degree sound, waterproof design, and 12 hours of playtime make it the perfect companion for outdoor adventures.', '360° sound, waterproof, 12hr battery Bluetooth speaker.', 79.99, 59.99, 100, 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=600&q=80', 0, 'ELEC-003'),
(1, '4K Ultra HD Monitor', '4k-ultra-hd-monitor', 'Elevate your visual experience with our 27-inch 4K Ultra HD monitor. With 99% sRGB color coverage, 144Hz refresh rate, and HDR support, it is perfect for gaming and creative professionals.', '27" 4K monitor, 144Hz, 99% sRGB, HDR support.', 449.99, 399.99, 15, 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&q=80', 1, 'ELEC-004'),
(2, 'Classic Fit Oxford Shirt', 'classic-fit-oxford-shirt', 'Timeless style meets modern comfort in our Classic Fit Oxford Shirt. Made from 100% premium cotton, available in multiple colors, perfect for business or casual wear.', '100% premium cotton Oxford shirt for all occasions.', 49.99, NULL, 200, 'https://images.unsplash.com/photo-1603252109303-2751441dd157?w=600&q=80', 0, 'CLTH-001'),
(2, 'Slim Fit Jeans', 'slim-fit-jeans', 'Our Slim Fit Jeans combine classic denim style with modern stretch technology for all-day comfort. Features a flattering silhouette and durable construction.', 'Stretch denim slim fit jeans for all-day comfort.', 69.99, 54.99, 150, 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=600&q=80', 1, 'CLTH-002'),
(3, 'The Art of Clean Code', 'the-art-of-clean-code', 'A practical guide for developers on how to write clean, maintainable, and efficient code. Covers best practices, design patterns, and refactoring techniques with real-world examples.', 'Practical guide to writing clean, maintainable code.', 34.99, 27.99, 75, 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=600&q=80', 0, 'BOOK-001'),
(3, 'JavaScript: The Definitive Guide', 'javascript-the-definitive-guide', 'The definitive reference for JavaScript programming, covering ES2022 and beyond. From fundamentals to advanced topics, this book is your complete guide to modern JavaScript development.', 'Complete reference for modern JavaScript development.', 44.99, NULL, 60, 'https://images.unsplash.com/photo-1532012197267-da84d127e765?w=600&q=80', 0, 'BOOK-002'),
(4, 'Ergonomic Office Chair', 'ergonomic-office-chair', 'Work in comfort with our fully adjustable ergonomic office chair. Features lumbar support, adjustable armrests, breathable mesh back, and 5-star base for stability.', 'Fully adjustable ergonomic chair with lumbar support.', 299.99, 249.99, 25, 'https://images.unsplash.com/photo-1580480055273-228ff5388ef8?w=600&q=80', 1, 'HOME-001'),
(5, 'Professional Yoga Mat', 'professional-yoga-mat', 'Elevate your yoga practice with our premium non-slip yoga mat. Made from eco-friendly TPE material, it provides excellent grip, cushioning, and comes with a carry strap.', 'Eco-friendly non-slip yoga mat with carry strap.', 39.99, NULL, 120, 'https://images.unsplash.com/photo-1601925228701-4f38d68ce8b2?w=600&q=80', 0, 'SPRT-001');

-- Default admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@nextgenshop.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/Lew5yLHvCLF6rNHcy', 'admin');
