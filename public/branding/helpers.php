<?php
/**
 * Branding Module Helper Functions
 * Provides core utilities for managing organization branding and settings
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

/**
 * Check if branding settings table exists and has data
 */
function branding_table_exists(mysqli $conn): bool {
    $res = @mysqli_query($conn, "SHOW TABLES LIKE 'branding_settings'");
    $exists = ($res && mysqli_num_rows($res) > 0);
    if ($res) mysqli_free_result($res);
    return $exists;
}

/**
 * Check if branding is configured (at least org_name set)
 */
function branding_is_configured(mysqli $conn): bool {
    if (!branding_table_exists($conn)) return false;
    $res = mysqli_query($conn, "SELECT id FROM branding_settings WHERE org_name IS NOT NULL AND org_name != '' LIMIT 1");
    $configured = ($res && mysqli_num_rows($res) > 0);
    if ($res) mysqli_free_result($res);
    return $configured;
}

/**
 * Get branding settings (singleton record)
 */
function branding_get_settings(mysqli $conn): ?array {
    $res = mysqli_query($conn, "SELECT * FROM branding_settings ORDER BY id ASC LIMIT 1");
    if (!$res) return null;
    $data = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    return $data ?: null;
}

/**
 * Update branding settings
 */
function branding_update_settings(mysqli $conn, array $data, int $user_id): bool {
    // Get existing record or create new
    $existing = branding_get_settings($conn);
    
    $fields = [
        'org_name', 'legal_name', 'tagline', 
        'address_line1', 'address_line2', 'city', 'state', 'zip', 'country',
        'email', 'phone', 'website', 'gstin', 'footer_text'
    ];
    
    $updates = [];
    $params = [];
    $types = '';
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= 's';
        }
    }
    
    if (empty($updates)) return false;
    
    if ($existing) {
        // Update existing record
        $sql = "UPDATE branding_settings SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $params[] = $existing['id'];
        $types .= 'i';
    } else {
        // Insert new record
        $updates[] = 'created_by = ?';
        $params[] = $user_id;
        $types .= 'i';
        $sql = "INSERT INTO branding_settings SET " . implode(', ', $updates);
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $success;
}

/**
 * Upload and save logo file
 */
function branding_upload_logo(mysqli $conn, array $file, string $type): array {
    // Validate type
    if (!in_array($type, ['login_page_logo', 'sidebar_header_full_logo', 'favicon', 'sidebar_square_logo'])) {
        return ['success' => false, 'error' => 'Invalid logo type'];
    }
    
    // Validate file
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size exceeds 2MB limit'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only PNG, JPG, and SVG allowed'];
    }
    
    // Prepare upload directory
    $upload_dir = __DIR__ . '/../../uploads/branding/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . $type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file'];
    }
    
    // Update database
    // Column names are semantic (e.g. login_page_logo, sidebar_header_full_logo, favicon, sidebar_square_logo)
    $column = $type;
    $relative_path = 'uploads/branding/' . $filename;
    
    $settings = branding_get_settings($conn);
    if ($settings) {
        // Delete old logo file if exists
        if (!empty($settings[$column])) {
            $old_file = __DIR__ . '/../../' . $settings[$column];
            if (file_exists($old_file)) {
                @unlink($old_file);
            }
        }
        
        $stmt = mysqli_prepare($conn, "UPDATE branding_settings SET $column = ?, updated_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $relative_path, $settings['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        // Create new record with just this logo
        $stmt = mysqli_prepare($conn, "INSERT INTO branding_settings (org_name, $column) VALUES (?, ?)");
        $default_name = 'Karyalay ERP';
        mysqli_stmt_bind_param($stmt, 'ss', $default_name, $relative_path);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    return ['success' => true, 'path' => $relative_path, 'filename' => $filename];
}

/**
 * Delete logo file
 */
function branding_delete_logo(mysqli $conn, string $type): bool {
    if (!in_array($type, ['login_page_logo', 'sidebar_header_full_logo', 'favicon', 'sidebar_square_logo'])) return false;
    
    $settings = branding_get_settings($conn);
    if (!$settings) return false;
    
    // Column names are semantic (no 'logo_' prefix)
    $column = $type;
    if (empty($settings[$column])) return true; // Already empty
    
    // Delete physical file
    $filepath = __DIR__ . '/../../' . $settings[$column];
    if (file_exists($filepath)) {
        @unlink($filepath);
    }
    
    // Clear database reference
    $stmt = mysqli_prepare($conn, "UPDATE branding_settings SET $column = NULL, updated_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $settings['id']);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $success;
}

/**
 * Get logo URL by semantic type
 * Types: login_page_logo, sidebar_header_full_logo, favicon, sidebar_square_logo
 */
function branding_get_logo_url(mysqli $conn, string $type = 'favicon'): ?string {
    $valid_types = ['login_page_logo', 'sidebar_header_full_logo', 'favicon', 'sidebar_square_logo'];
    if (!in_array($type, $valid_types)) return null;
    
    $settings = branding_get_settings($conn);
    if (!$settings) return null;
    
    if (!empty($settings[$type])) {
        return '../../' . $settings[$type];
    }
    
    return null;
}

/**
 * Get logo URL by semantic type (returns absolute path relative to website root)
 */
function branding_get_logo_path(mysqli $conn, string $type = 'favicon'): ?string {
    $valid_types = ['login_page_logo', 'sidebar_header_full_logo', 'favicon', 'sidebar_square_logo'];
    if (!in_array($type, $valid_types)) return null;
    
    $settings = branding_get_settings($conn);
    if (!$settings) return null;
    
    if (!empty($settings[$type])) {
        return $settings[$type];
    }
    
    return null;
}

/**
 * Validate email format
 */
function branding_validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL format
 */
function branding_validate_url(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false 
        && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
}

/**
 * Check if user has admin access
 */
function branding_user_can_edit(): bool {
    if (!isset($_SESSION['role'])) return false;
    return in_array(strtolower($_SESSION['role']), ['admin']);
}
?>
