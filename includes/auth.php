<?php
// ============================================================
// includes/auth.php
// Session-based authentication guard
// ============================================================

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require admin to be logged in.
 * Redirects to login page if not authenticated.
 */
function requireLogin(): void {
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Check if admin is currently logged in.
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Return current logged-in admin username.
 */
function currentAdmin(): string {
    return $_SESSION['admin_username'] ?? 'Admin';
}

/**
 * Destroy session and log out.
 */
function logoutAdmin(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
