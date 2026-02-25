<?php
/**
 * NextGenShop – Authentication Helpers
 */

/**
 * Check if a user is logged in.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Check if the logged-in user is an admin.
 */
function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Require the user to be logged in; redirect otherwise.
 */
function require_login(string $redirect = '/pages/login.php'): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Require the user to be an admin; redirect otherwise.
 */
function require_admin(): void
{
    require_login('/pages/login.php');
    if (!is_admin()) {
        header('Location: /index.php?error=unauthorized');
        exit;
    }
}

/**
 * Log a user in and populate session data.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];

    // Merge guest cart into user cart
    merge_guest_cart((int) $user['id']);
}

/**
 * Log the current user out.
 */
function logout_user(): void
{
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

/**
 * Return the currently logged-in user's data from the DB.
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare(
            'SELECT id, name, email, role, avatar, phone, address, created_at FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }

    return $user;
}

/**
 * Generate and store a CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token.
 */
function csrf_validate(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Output a hidden CSRF input field.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}
