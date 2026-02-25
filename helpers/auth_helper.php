<?php
/**
 * Auth Helper functions
 */

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) || isset($_SESSION['id']);
    }
}

if (!function_exists('redirectIfNotLoggedIn')) {
    function redirectIfNotLoggedIn() {
        if (!isLoggedIn()) {
            header('Location: ' . BASE_URL . 'auth/login.php');
            exit();
        }
    }
}

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        // Robust check for user ID in session
        return $_SESSION['user_id'] ?? ($_SESSION['id'] ?? null);
    }
}
?>
