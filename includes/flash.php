<?php
/**
 * Simple flash messaging utility with session-backed storage.
 */

if (!defined('FLASH_SESSION_KEY')) {
    define('FLASH_SESSION_KEY', '__app_flash_messages');
}

if (!function_exists('flash_normalize_type')) {
    function flash_normalize_type(string $type): string
    {
        $type = strtolower($type);
        $allowed = ['success', 'error', 'warning', 'info'];
        return in_array($type, $allowed, true) ? $type : 'info';
    }
}

if (!function_exists('flash_ensure_session')) {
    function flash_ensure_session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('flash_add')) {
    function flash_add(string $type, string $message, string $category = 'global'): void
    {
        flash_ensure_session();

        $type = flash_normalize_type($type);
        $message = trim($message);
        if ($message === '') {
            return;
        }

        if (!isset($_SESSION[FLASH_SESSION_KEY]) || !is_array($_SESSION[FLASH_SESSION_KEY])) {
            $_SESSION[FLASH_SESSION_KEY] = [];
        }

        $_SESSION[FLASH_SESSION_KEY][] = [
            'type' => $type,
            'message' => $message,
            'category' => $category,
            'timestamp' => time(),
        ];
    }
}

if (!function_exists('flash_collect_legacy')) {
    function flash_collect_legacy(): void
    {
        static $migrated = false;
        if ($migrated) {
            return;
        }
        $migrated = true;

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            return;
        }

        foreach ($_SESSION as $key => $value) {
            if (!is_string($key) || !is_string($value) || trim($value) === '') {
                continue;
            }
            if (preg_match('/_(success|error|warning|info)$/', $key, $matches)) {
                flash_add($matches[1], $value);
                unset($_SESSION[$key]);
            }
        }
    }
}

if (!function_exists('flash_all')) {
    function flash_all(bool $clear = true): array
    {
        flash_ensure_session();
        flash_collect_legacy();

        $messages = $_SESSION[FLASH_SESSION_KEY] ?? [];
        if ($clear) {
            unset($_SESSION[FLASH_SESSION_KEY]);
        }

        return $messages;
    }
}

if (!function_exists('flash_peek')) {
    function flash_peek(?string $type = null): array
    {
        $messages = flash_all(false);
        if ($type === null) {
            return $messages;
        }

        $type = flash_normalize_type($type);
        return array_values(array_filter(
            $messages,
            static fn($message) => ($message['type'] ?? '') === $type
        ));
    }
}

if (!function_exists('flash_has')) {
    function flash_has(?string $type = null): bool
    {
        if ($type === null) {
            return !empty(flash_peek());
        }

        return !empty(flash_peek($type));
    }
}

if (!function_exists('flash_render')) {
    function flash_render(?array $messages = null, bool $clear = true): string
    {
        if ($messages === null) {
            $messages = flash_all($clear);
        }

        if (empty($messages)) {
            return '';
        }

        $typeTitles = [
            'success' => 'Success',
            'error' => 'Something went wrong',
            'warning' => 'Attention',
            'info' => 'FYI',
        ];

        ob_start();
        echo '<div class="flash-messages" role="status" aria-live="polite">';
        foreach ($messages as $message) {
            $type = flash_normalize_type($message['type'] ?? 'info');
            $title = $typeTitles[$type] ?? 'Notice';
            $text = htmlspecialchars($message['message'] ?? '', ENT_QUOTES);
            $category = htmlspecialchars($message['category'] ?? 'global', ENT_QUOTES);

            echo '<div class="alert alert-' . $type . '" data-flash-category="' . $category . '">';
            echo '<div class="alert-content">';
            echo '<strong>' . htmlspecialchars($title, ENT_QUOTES) . ':</strong> ' . $text;
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }
}

if (!function_exists('flash_clear')) {
    function flash_clear(?string $type = null): void
    {
        if (!isset($_SESSION[FLASH_SESSION_KEY])) {
            return;
        }

        if ($type === null) {
            unset($_SESSION[FLASH_SESSION_KEY]);
            return;
        }

        $type = flash_normalize_type($type);
        $_SESSION[FLASH_SESSION_KEY] = array_values(array_filter(
            $_SESSION[FLASH_SESSION_KEY],
            static fn($message) => ($message['type'] ?? '') !== $type
        ));

        if (empty($_SESSION[FLASH_SESSION_KEY])) {
            unset($_SESSION[FLASH_SESSION_KEY]);
        }
    }
}
