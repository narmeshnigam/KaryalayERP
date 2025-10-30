<?php
/**
 * Automatically enforces table-based permissions for the current script based on
 * the configuration declared in config/table_access_map.php.
 */

if (!empty($GLOBALS['AUTHZ_SKIP_AUTO_GUARD'])) {
    return;
}

$project_root = realpath(__DIR__ . '/..');
$script_file = $_SERVER['SCRIPT_FILENAME'] ?? '';
$relative_path = '';

if ($script_file) {
    $script_real = realpath($script_file);
    if ($script_real && $project_root && strpos($script_real, $project_root) === 0) {
        $relative_path = ltrim(str_replace('\\', '/', substr($script_real, strlen($project_root))), '/');
    } else {
        $relative_path = ltrim(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
    }
}

if ($relative_path === '') {
    return;
}

$access_map = include __DIR__ . '/../config/table_access_map.php';

if (!is_array($access_map) || empty($access_map)) {
    return;
}

usort($access_map, static function ($a, $b) {
    $lenA = isset($a['pattern']) ? strlen($a['pattern']) : 0;
    $lenB = isset($b['pattern']) ? strlen($b['pattern']) : 0;
    return $lenB <=> $lenA;
});

foreach ($access_map as $entry) {
    if (empty($entry['pattern'])) {
        continue;
    }

    $pattern = str_replace('\\', '/', $entry['pattern']);

    if (strpos($relative_path, $pattern) !== 0) {
        continue;
    }

    $table = $entry['table'] ?? null;
    $routes = $entry['routes'] ?? [];
    $default_permission = $entry['default'] ?? null;
    $file_name = basename($relative_path);

    $route_spec = $routes[$file_name] ?? $default_permission;

    if (is_array($route_spec) && !empty($route_spec['skip'])) {
        return;
    }
    if ($route_spec === null && $default_permission === null) {
        return;
    }

    if (is_array($route_spec)) {
        if (!empty($route_spec['table'])) {
            $table = $route_spec['table'];
        }
        if (!empty($route_spec['requires_any'])) {
            if (!authz_user_can_any($conn, $route_spec['requires_any'])) {
                $fallback_perm = $route_spec['permission'] ?? ($default_permission ?? 'view_all');
                if ($table) {
                    authz_require_permission($conn, $table, $fallback_perm);
                }
            }
            return;
        }
        $perm = $route_spec['permission'] ?? ($default_permission ?? 'view_all');
        if ($table) {
            authz_require_permission($conn, $table, $perm);
        }
        return;
    }

    if (is_string($route_spec)) {
        if ($route_spec === 'skip') {
            return;
        }
        if ($table) {
            authz_require_permission($conn, $table, $route_spec);
        }
        return;
    }

    if ($table && $default_permission) {
        authz_require_permission($conn, $table, $default_permission);
    }

    return;
}
?>
