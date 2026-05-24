<?php
// Start the session if it hasn't already been started. This allows us to use $_SESSION for storing user data,
// flash messages, and CSRF tokens throughout the application.
// We check the session status to avoid calling session_start() multiple times, which would cause a warning.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $appRoot = str_replace('\\', '/', realpath(dirname(__DIR__)));
    $base = '';
    if ($docRoot !== '' && $appRoot !== false && strpos($appRoot, $docRoot) === 0) {
        $base = substr($appRoot, strlen($docRoot));
    }
    define('BASE_URL', rtrim($base, '/'));
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Flash message functions for setting and retrieving temporary messages that persist across a single request.
function set_flash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

// Retrieve and clear a flash message of a given type. This allows for displaying one-time messages to the user,
// such as success or error notifications, without them persisting across multiple requests.
function get_flash($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}
?>
