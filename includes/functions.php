<?php
/**
 * NextGenShop – General Helper Functions
 */

/**
 * HTML-encode a value for safe output.
 */
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format a price with currency symbol.
 */
function format_price(float $price): string
{
    return CURRENCY_SYMBOL . number_format($price, 2);
}

/**
 * Redirect to a URL.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Set a flash message.
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Retrieve and clear flash messages.
 */
function get_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Render flash messages as Bootstrap alerts.
 */
function render_flash(): string
{
    $messages = get_flash();
    if (empty($messages)) {
        return '';
    }

    $map = [
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];

    $html = '';
    foreach ($messages as $type => $message) {
        $cls  = $map[$type] ?? 'info';
        $html .= '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">'
               . h($message)
               . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
               . '</div>';
    }

    return $html;
}

/**
 * Generate a URL-friendly slug from a string.
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Truncate text to a given length.
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
}

/**
 * Generate a random order number.
 */
function generate_order_number(): string
{
    return 'ORD-' . strtoupper(bin2hex(random_bytes(5)));
}

/**
 * Get all categories (cached per request).
 */
function get_categories(): array
{
    static $categories = null;
    if ($categories === null) {
        $stmt = db()->query('SELECT * FROM categories ORDER BY sort_order, name');
        $categories = $stmt->fetchAll();
    }
    return $categories;
}

/**
 * Get the cart item count for the current user/session.
 */
function cart_count(): int
{
    static $count = null;
    if ($count === null) {
        if (is_logged_in()) {
            $stmt = db()->prepare(
                'SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?'
            );
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            $stmt = db()->prepare(
                'SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE session_id = ?'
            );
            $stmt->execute([session_id()]);
        }
        $count = (int) $stmt->fetchColumn();
    }
    return $count;
}

/**
 * Get the wishlist item count for the current user.
 */
function wishlist_count(): int
{
    if (!is_logged_in()) {
        return 0;
    }
    static $count = null;
    if ($count === null) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM wishlist WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $count = (int) $stmt->fetchColumn();
    }
    return $count;
}

/**
 * Merge a guest cart (by session_id) into a user cart on login.
 */
function merge_guest_cart(int $user_id): void
{
    $session_id = session_id();
    $pdo = db();

    // Fetch guest cart items
    $stmt = $pdo->prepare('SELECT product_id, quantity FROM cart WHERE session_id = ?');
    $stmt->execute([$session_id]);
    $guest_items = $stmt->fetchAll();

    if (empty($guest_items)) {
        return;
    }

    $upsert = $pdo->prepare(
        'INSERT INTO cart (user_id, product_id, quantity)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
    );

    $delete = $pdo->prepare('DELETE FROM cart WHERE session_id = ? AND product_id = ?');

    foreach ($guest_items as $item) {
        $upsert->execute([$user_id, $item['product_id'], $item['quantity']]);
        $delete->execute([$session_id, $item['product_id']]);
    }
}

/**
 * Sanitize an integer from user input.
 */
function input_int(string $key, int $default = 0): int
{
    return isset($_REQUEST[$key]) ? (int) $_REQUEST[$key] : $default;
}

/**
 * Sanitize a string from user input.
 */
function input_str(string $key, string $default = ''): string
{
    return isset($_REQUEST[$key]) ? trim((string) $_REQUEST[$key]) : $default;
}

/**
 * Check if a product is in the current user's wishlist.
 */
function in_wishlist(int $product_id): bool
{
    if (!is_logged_in()) {
        return false;
    }
    $stmt = db()->prepare('SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Get star rating HTML.
 */
function star_rating(float $rating, int $max = 5): string
{
    $html = '<span class="star-rating">';
    for ($i = 1; $i <= $max; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="bi bi-star-fill text-warning"></i>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<i class="bi bi-star-half text-warning"></i>';
        } else {
            $html .= '<i class="bi bi-star text-warning"></i>';
        }
    }
    $html .= '</span>';
    return $html;
}

/**
 * Get average rating for a product.
 */
function product_avg_rating(int $product_id): float
{
    $stmt = db()->prepare(
        'SELECT AVG(rating) FROM reviews WHERE product_id = ? AND status = "approved"'
    );
    $stmt->execute([$product_id]);
    return round((float) $stmt->fetchColumn(), 1);
}
