<?php
/**
 * NextGenShop – Application Configuration
 */

define('APP_NAME', 'NextGenShop');
define('APP_URL', 'http://localhost');
define('APP_VERSION', '1.0.0');
define('CURRENCY_SYMBOL', '$');
define('ITEMS_PER_PAGE', 12);
define('ADMIN_EMAIL', 'admin@nextgenshop.com');
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('UPLOAD_URL', '/uploads/products/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('SESSION_LIFETIME', 86400); // 24 hours
