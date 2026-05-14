<?php
// Start the session if it hasn't already been started. This allows us to use $_SESSION for storing user data,
// flash messages, and CSRF tokens throughout the application.
// We check the session status to avoid calling session_start() multiple times, which would cause a warning.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define BASE_URL for generating absolute URLs in the application. 
// This is calculated based on the document root and the directory of the current script, allowing for flexibility in deployment environments.
if (!defined('BASE_URL')) {
    define('BASE_URL', str_replace('\\', '/', substr(
        dirname(__DIR__),
        strlen(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'))
    )));
}

// Generate a CSRF token if one doesn't already exist. This token will be used to protect against
// Cross-Site Request Forgery attacks by ensuring that form submissions originate from the same site.
// The token is generated using random_bytes for cryptographic security and stored in the session for later verification.
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
