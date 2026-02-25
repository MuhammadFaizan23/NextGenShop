# NextGenShop

A modern, full-featured PHP e-commerce system with shopping cart, wishlist, user authentication, admin panel, and performance-optimized database queries.

## Features

### Customer Features
- **Product catalog** - Browse, search (full-text), filter by category and price, sort
- **Product detail** - Gallery, reviews and ratings, related products
- **Shopping cart** - Add/update/remove items, guest cart merging on login
- **Wishlist** - Save items, move to cart
- **Checkout** - Shipping info, payment method selection, order placement
- **Order history** - View past orders and their status
- **User profile** - Update personal info and change password

### Authentication
- Secure registration and login with bcrypt password hashing (cost 12)
- CSRF protection on all forms
- Session regeneration to prevent fixation attacks
- Role-based access control (customer / admin)
- Guest cart merges with user cart on login

### Admin Panel
- **Dashboard** - Revenue stats, recent orders, top products, monthly revenue chart
- **Product management** - Full CRUD with bulk status updates, search/filter
- **Category management** - Create, edit, delete categories
- **Order management** - View all orders, update status per order
- **User management** - View customers, toggle admin role, reset passwords
- **Review moderation** - Approve, reject, or delete pending reviews

### Performance Optimizations
- Prepared statements throughout (prevents SQL injection + improves performance)
- Optimized indexes on all frequently-queried columns
- Singleton PDO connection with ATTR_PERSISTENT
- Full-text index for product search
- Composite indexes on (user_id, created_at) for order queries
- Pagination on all list pages
- LIMIT applied at query level

## Project Structure

```
NextGenShop/
├── index.php                    # Homepage / product listing
├── config/
│   ├── app.php                  # Application constants
│   ├── database.php             # PDO singleton
│   └── bootstrap.php            # Session init, constants
├── includes/
│   ├── auth.php                 # Login, logout, CSRF helpers
│   ├── functions.php            # General helpers
│   ├── header.php               # Shared HTML header + navbar
│   └── footer.php               # Shared footer
├── pages/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── product.php              # Product detail
│   ├── cart.php                 # Shopping cart
│   ├── wishlist.php             # Wishlist
│   ├── checkout.php             # Checkout
│   ├── order_confirmation.php   # Post-purchase confirmation
│   ├── orders.php               # Order history
│   └── profile.php              # User profile
├── admin/
│   ├── index.php                # Admin dashboard
│   ├── products.php             # Product CRUD
│   ├── categories.php           # Category CRUD
│   ├── orders.php               # Order management
│   ├── users.php                # User management
│   ├── reviews.php              # Review moderation
│   └── includes/
│       ├── admin_header.php
│       └── admin_footer.php
├── assets/
│   ├── css/style.css
│   └── js/main.js
├── sql/
│   └── schema.sql               # DB schema + sample data
├── uploads/products/            # Product image uploads
└── .htaccess                    # Apache config
```

## Installation

### Requirements
- PHP 8.1+
- MySQL 8.0+ or MariaDB 10.6+
- Apache / Nginx with PHP support

### Setup

1. Clone the repository and place it in your web root.

2. Create the database:
   ```bash
   mysql -u root -p < sql/schema.sql
   ```

3. Configure the database in `config/database.php` or via environment variables:
   ```bash
   export DB_HOST=localhost
   export DB_NAME=nextgenshop
   export DB_USER=root
   export DB_PASS=your_password
   ```

4. Set permissions:
   ```bash
   chmod 755 uploads/products/
   ```

5. Visit `http://localhost/` in your browser.

### Demo Credentials

| Role     | Email                       | Password   |
|----------|-----------------------------|------------|
| Admin    | admin@nextgenshop.com       | admin123   |

## Security Features
- CSRF tokens on every form
- bcrypt password hashing (cost 12)
- PDO prepared statements (no SQL injection)
- htmlspecialchars output escaping
- Session fixation prevention
- HTTP security headers via .htaccess
- Role-based access control

## Tech Stack
- **Backend:** PHP 8.1+
- **Database:** MySQL 8 / MariaDB
- **Frontend:** Bootstrap 5.3, Bootstrap Icons, vanilla JS
- **Architecture:** Procedural PHP with MVC-inspired layout
