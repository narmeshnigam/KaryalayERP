<?php
/**
 * Application bootstrap: session, buffering, flash helpers.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);

    if (ob_get_level() === 0) {
        ob_start();
        define('APP_OUTPUT_BUFFER_STARTED', true);
    } else {
        define('APP_OUTPUT_BUFFER_STARTED', false);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once __DIR__ . '/flash.php';
    require_once __DIR__ . '/url_helper.php';
}
