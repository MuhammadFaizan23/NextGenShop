<?php
/**
 * NextGenShop – Bootstrap / Autoloader
 *
 * Required at the top of every page. Sets up sessions, loads config,
 * database, and helper functions.
 */

// Security: prevent direct access to includes
if (!defined('NEXTGENSHOP')) {
    define('NEXTGENSHOP', true);
}

// Timezone
date_default_timezone_set('UTC');

// Error reporting (disable in production)
$debug = getenv('APP_DEBUG') === 'true';
error_reporting($debug ? E_ALL : 0);
ini_set('display_errors', $debug ? '1' : '0');

// Session configuration (must happen before session_start)
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['_last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['_last_regeneration'] = time();
} elseif (time() - $_SESSION['_last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['_last_regeneration'] = time();
}
