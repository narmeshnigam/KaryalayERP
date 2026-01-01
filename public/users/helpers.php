<?php
/**
 * Users Management Module - Helper Functions
 * Core functions for user CRUD operations and access control
 */

/**
 * Check if user management tables exist
 * @param mysqli $conn Database connection
 * @return bool True if all required tables exist
 */
function users_tables_exist($conn) {
    $required_tables = ['users', 'user_activity_log', 'roles'];
    
    foreach ($required_tables as $table) {
        $result = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (!$result || mysqli_num_rows($result) === 0) {
            if ($result) @mysqli_free_result($result);
            return false;
        }
        @mysqli_free_result($result);
    }
    
    return true;
}

/**
 * Get all users with optional filters
 * @param mysqli $conn Database connection
 * @param array $filters Associative array of filters (status, role_id, entity_type, search)
 * @return array Array of user records
 */
function get_all_users($conn, $filters = []) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Build WHERE clause based on filters
    if (!empty($filters['status'])) {
        $where_clauses[] = "u.status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    
    if (!empty($filters['role_id'])) {
        $where_clauses[] = "EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = ?)";
        $params[] = $filters['role_id'];
        $types .= 'i';
    }
    
    if (!empty($filters['entity_type'])) {
        $where_clauses[] = "u.entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= 's';
    }
    
    if (!empty($filters['search'])) {
        $where_clauses[] = "(u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'sss';
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    $sql = "
        SELECT 
            u.*,
            GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') as role_name,
            GROUP_CONCAT(r.description ORDER BY r.name SEPARATOR ' | ') as role_description,
            e.first_name as employee_first_name,
            e.last_name as employee_last_name,
            e.employee_code,
            creator.username as created_by_username,
            (SELECT COUNT(*) FROM user_activity_log WHERE user_id = u.id AND status = 'Success') as login_count
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN employees e ON u.entity_id = e.id AND u.entity_type = 'Employee'
        LEFT JOIN users creator ON u.created_by = creator.id
        $where_sql
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $users = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        return $users;
    }
    
    return [];
}

/**
 * Get user by ID
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return array|null User record or null if not found
 */
function get_user_by_id($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "
        SELECT 
            u.*,
            GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') as role_name,
            GROUP_CONCAT(r.description ORDER BY r.name SEPARATOR ' | ') as role_description,
            e.first_name as employee_first_name,
            e.last_name as employee_last_name,
            e.employee_code,
            e.department,
            e.designation,
            creator.username as created_by_username,
            (SELECT login_time FROM user_activity_log WHERE user_id = u.id AND status = 'Success' ORDER BY login_time DESC LIMIT 1) as last_successful_login,
            (SELECT COUNT(*) FROM user_activity_log WHERE user_id = u.id AND status = 'Success') as login_count,
            (SELECT COUNT(*) FROM user_activity_log WHERE user_id = u.id AND status = 'Failed') as failed_login_count
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN employees e ON u.entity_id = e.id AND u.entity_type = 'Employee'
        LEFT JOIN users creator ON u.created_by = creator.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $user ?: null;
    }
    
    return null;
}

/**
 * Get user by username
 * @param mysqli $conn Database connection
 * @param string $username Username
 * @return array|null User record or null if not found
 */
function get_user_by_username($conn, $username) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $user ?: null;
    }
    
    return null;
}

/**
 * Check if username exists
 * @param mysqli $conn Database connection
 * @param string $username Username to check
 * @param int|null $exclude_user_id User ID to exclude from check (for updates)
 * @return bool True if username exists
 */
function username_exists($conn, $username, $exclude_user_id = null) {
    $sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
    $params = [$username];
    $types = 's';
    
    if ($exclude_user_id !== null) {
        $sql .= " AND id != ?";
        $params[] = $exclude_user_id;
        $types .= 'i';
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row['count'] > 0;
    }
    
    return false;
}

/**
 * Check if email exists
 * @param mysqli $conn Database connection
 * @param string $email Email to check
 * @param int|null $exclude_user_id User ID to exclude from check (for updates)
 * @return bool True if email exists
 */
function email_exists($conn, $email, $exclude_user_id = null) {
    $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
    $params = [$email];
    $types = 's';
    
    if ($exclude_user_id !== null) {
        $sql .= " AND id != ?";
        $params[] = $exclude_user_id;
        $types .= 'i';
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row['count'] > 0;
    }
    
    return false;
}

/**
 * Create new user
 * @param mysqli $conn Database connection
 * @param array $data User data
 * @return int|false User ID on success, false on failure
 */
function create_user($conn, $data) {
    // Convert empty entity_type to NULL for ENUM compatibility
    if (empty($data['entity_type'])) {
        $data['entity_type'] = null;
    }
    
    // Convert empty entity_id to NULL
    if (empty($data['entity_id'])) {
        $data['entity_id'] = null;
    }
    
    // Ensure status has a valid value
    if (empty($data['status']) || !in_array($data['status'], ['Active', 'Inactive', 'Suspended'])) {
        $data['status'] = 'Active';
    }
    
    // Insert into users table (note: role is managed via user_roles table)
    $sql = "
        INSERT INTO users (
            entity_id, entity_type, username, full_name, email, phone,
            password_hash, status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            'isssssssi',
            $data['entity_id'],
            $data['entity_type'],
            $data['username'],
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $data['password_hash'],
            $data['status'],
            $data['created_by']
        );

        if (mysqli_stmt_execute($stmt)) {
            $user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // If a role was provided, assign it using the user_roles mapping table
            if (!empty($data['role_id'])) {
                assign_role_to_user($conn, $user_id, (int)$data['role_id'], $data['created_by'] ?? null);
            }

            return $user_id;
        }

        mysqli_stmt_close($stmt);
    }

    return false;
}

/**
 * Assign a role to a user by inserting into user_roles.
 * Uses INSERT IGNORE to avoid duplicate entries.
 * @param mysqli $conn
 * @param int $user_id
 * @param int $role_id
 * @param int|null $assigned_by
 * @return bool True on success
 */
function assign_role_to_user($conn, $user_id, $role_id, $assigned_by = null) {
    $sql = "INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    // assigned_by can be null
    $assigned_by_param = $assigned_by !== null ? (int)$assigned_by : null;
    mysqli_stmt_bind_param($stmt, 'iii', $user_id, $role_id, $assigned_by_param);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

/**
 * Update user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param array $data User data to update
 * @return bool True on success
 */
function update_user($conn, $user_id, $data) {
    // Convert empty entity_type to NULL for ENUM compatibility
    if (array_key_exists('entity_type', $data) && empty($data['entity_type'])) {
        $data['entity_type'] = null;
    }
    
    // Convert empty entity_id to NULL
    if (array_key_exists('entity_id', $data) && empty($data['entity_id'])) {
        $data['entity_id'] = null;
    }
    
    // Ensure status has a valid value if provided
    if (array_key_exists('status', $data)) {
        if (empty($data['status']) || !in_array($data['status'], ['Active', 'Inactive', 'Suspended'])) {
            $data['status'] = 'Active';
        }
    }
    
    $set_clauses = [];
    $params = [];
    $types = '';
    
    // Note: role(s) are stored in user_roles table. Do not attempt to update a non-existent
    // role_id column on users table. Role changes will be handled separately below.
    $allowed_fields = [
        'entity_id' => 'i',
        'entity_type' => 's',
        'username' => 's',
        'full_name' => 's',
        'email' => 's',
        'phone' => 's',
        'status' => 's'
    ];
    
    foreach ($allowed_fields as $field => $type) {
        if (array_key_exists($field, $data)) {
            $set_clauses[] = "$field = ?";
            $params[] = $data[$field];
            $types .= $type;
        }
    }
    
    if (empty($set_clauses)) {
        return false;
    }
    
    $params[] = $user_id;
    $types .= 'i';
    
    $sql = "UPDATE users SET " . implode(', ', $set_clauses) . " WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // If a role_id was provided, assign it in the user_roles table
        if (array_key_exists('role_id', $data) && !empty($data['role_id'])) {
            assign_role_to_user($conn, $user_id, (int)$data['role_id'], $data['updated_by'] ?? null);
        }

        return $success;
    }
    
    return false;
}

/**
 * Update user password
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $new_password_hash New password hash
 * @return bool True on success
 */
function update_user_password($conn, $user_id, $new_password_hash) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $new_password_hash, $user_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $success;
    }
    
    return false;
}

/**
 * Delete user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return bool True on success
 */
function delete_user($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $success;
    }
    
    return false;
}

/**
 * Update last login timestamp
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return bool True on success
 */
function update_last_login($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $success;
    }
    
    return false;
}

/**
 * Log user activity
 * @param mysqli $conn Database connection
 * @param array $data Activity data
 * @return int|false Activity log ID on success, false on failure
 */
function log_user_activity($conn, $data) {
    // Check the actual column size from the database
    static $max_device_length = null;
    
    if ($max_device_length === null) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM user_activity_log LIKE 'device'");
        if ($result && $row = mysqli_fetch_assoc($result)) {
            // Extract the number from VARCHAR(n)
            if (preg_match('/varchar\((\d+)\)/i', $row['Type'], $matches)) {
                $max_device_length = (int)$matches[1];
            } else {
                $max_device_length = 100; // Default fallback
            }
        } else {
            $max_device_length = 100; // Default fallback
        }
    }
    
    // Truncate device string to fit the actual column size
    if (!empty($data['device']) && strlen($data['device']) > $max_device_length) {
        $data['device'] = substr($data['device'], 0, $max_device_length - 3) . '...';
    }
    
    $sql = "
        INSERT INTO user_activity_log (
            user_id, ip_address, device, login_time, status, failure_reason
        ) VALUES (?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            'isssss',
            $data['user_id'],
            $data['ip_address'],
            $data['device'],
            $data['login_time'],
            $data['status'],
            $data['failure_reason']
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $log_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            return $log_id;
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return false;
}

/**
 * Mark the most recent login record for a user as logged out
 * @param mysqli $conn
 * @param int $user_id
 * @return bool
 */
function update_user_logout($conn, $user_id) {
    // Ensure the logout_time column exists (older installs may be missing it)
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM user_activity_log LIKE 'logout_time'");
    if ($colCheck === false) {
        // Table might not exist or permission issue
        return false;
    }

    if (mysqli_num_rows($colCheck) === 0) {
        // Try to add the column gracefully; ignore errors if it fails
        @mysqli_query($conn, "ALTER TABLE user_activity_log ADD COLUMN logout_time DATETIME NULL AFTER login_time");
    }

    // Find latest activity with null logout_time
    $sql = "SELECT id FROM user_activity_log WHERE user_id = ? AND (logout_time IS NULL) ORDER BY login_time DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$row) return false;

    $id = (int)$row['id'];
    $update = "UPDATE user_activity_log SET logout_time = NOW() WHERE id = ?";
    $ustmt = mysqli_prepare($conn, $update);
    if (!$ustmt) return false;
    mysqli_stmt_bind_param($ustmt, 'i', $id);
    $ok = mysqli_stmt_execute($ustmt);
    mysqli_stmt_close($ustmt);
    return (bool)$ok;
}

/**
 * Get user activity log
 * @param mysqli $conn Database connection
 * @param int $user_id User ID (optional, null for all users)
 * @param int $limit Limit number of records
 * @return array Array of activity records
 */
function get_user_activity_log($conn, $user_id = null, $limit = 50) {
    if ($user_id !== null) {
        $sql = "
            SELECT 
                ual.*,
                u.username,
                u.email
            FROM user_activity_log ual
            INNER JOIN users u ON ual.user_id = u.id
            WHERE ual.user_id = ?
            ORDER BY ual.login_time DESC
            LIMIT ?
        ";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $limit);
    } else {
        $sql = "
            SELECT 
                ual.*,
                u.username,
                u.email,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') as role_name
            FROM user_activity_log ual
            INNER JOIN users u ON ual.user_id = u.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            GROUP BY ual.id
            ORDER BY ual.login_time DESC
            LIMIT ?
        ";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $limit);
    }
    
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $activities = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $activities[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        return $activities;
    }
    
    return [];
}

/**
 * Get available employees for user linking
 * @param mysqli $conn Database connection
 * @return array Array of employees without linked users
 */
function get_available_employees($conn) {
    $sql = "
        SELECT 
            e.id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.official_email,
            e.personal_email,
            e.mobile_number,
            e.department,
            e.designation
        FROM employees e
        LEFT JOIN users u ON e.id = u.entity_id AND u.entity_type = 'Employee'
        WHERE u.id IS NULL
        ORDER BY e.first_name, e.last_name
    ";
    
    $result = mysqli_query($conn, $sql);
    $employees = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $employees[] = $row;
        }
        mysqli_free_result($result);
    }
    
    return $employees;
}

/**
 * Get all active roles
 * @param mysqli $conn Database connection
 * @return array Array of role records
 */
function get_active_roles($conn) {
    $sql = "SELECT id, name, description FROM roles WHERE status = 'Active' ORDER BY name";
    $result = mysqli_query($conn, $sql);
    $roles = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $roles[] = $row;
        }
        mysqli_free_result($result);
    }
    
    return $roles;
}

/**
 * Get user statistics
 * @param mysqli $conn Database connection
 * @return array Statistics array
 */
function get_user_statistics($conn) {
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'suspended' => 0,
        'employees' => 0,
        'clients' => 0,
        'recent_logins' => 0
    ];
    
    // Get status counts
    $result = mysqli_query($conn, "
        SELECT 
            status,
            COUNT(*) as count
        FROM users
        GROUP BY status
    ");
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stats['total'] += $row['count'];
            $stats[strtolower($row['status'])] = $row['count'];
        }
        mysqli_free_result($result);
    }
    
    // Get entity type counts
    $result = mysqli_query($conn, "
        SELECT 
            entity_type,
            COUNT(*) as count
        FROM users
        WHERE entity_type IS NOT NULL
        GROUP BY entity_type
    ");
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stats[strtolower($row['entity_type']) . 's'] = $row['count'];
        }
        mysqli_free_result($result);
    }
    
    // Get recent logins (last 7 days)
    $result = mysqli_query($conn, "
        SELECT COUNT(DISTINCT user_id) as count
        FROM user_activity_log
        WHERE login_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND status = 'Success'
    ");
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['recent_logins'] = $row['count'];
        mysqli_free_result($result);
    }
    
    return $stats;
}

/**
 * Verify password
 * @param string $password Plain text password
 * @param string $hash Password hash
 * @return bool True if password matches
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Hash password
 * @param string $password Plain text password
 * @return string Password hash
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Validate user data
 * @param array $data User data
 * @param bool $is_update True if updating existing user
 * @return array Array of errors (empty if valid)
 */
function validate_user_data($data, $is_update = false) {
    $errors = [];
    
    // Username validation
    if (!$is_update || isset($data['username'])) {
        if (empty($data['username'])) {
            $errors[] = "Username is required";
        } elseif (strlen($data['username']) < 3) {
            $errors[] = "Username must be at least 3 characters";
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $data['username'])) {
            $errors[] = "Username can only contain letters, numbers, dots, hyphens, and underscores";
        }
    }
    
    // Full name validation
    if (!$is_update || isset($data['full_name'])) {
        if (empty($data['full_name'])) {
            $errors[] = "Full name is required";
        } elseif (strlen($data['full_name']) < 2) {
            $errors[] = "Full name must be at least 2 characters";
        }
    }
    
    // Email validation - NOW REQUIRED
    if (!$is_update || isset($data['email'])) {
        if (empty($data['email'])) {
            $errors[] = "Email is required";
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
    }
    
    // Phone validation - NOW REQUIRED
    if (!$is_update || isset($data['phone'])) {
        if (empty($data['phone'])) {
            $errors[] = "Phone number is required";
        } elseif (!preg_match('/^[0-9+\-\s()]+$/', $data['phone'])) {
            $errors[] = "Invalid phone number format";
        }
    }
    
    // Password validation (only for new users or password changes)
    if (!$is_update && isset($data['password'])) {
        if (empty($data['password'])) {
            $errors[] = "Password is required";
        } elseif (strlen($data['password']) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
    }
    
    // Role validation
    if (!$is_update || isset($data['role_id'])) {
        if (empty($data['role_id'])) {
            $errors[] = "Role is required";
        }
    }
    
    // Status validation
    if (isset($data['status']) && !in_array($data['status'], ['Active', 'Inactive', 'Suspended'])) {
        $errors[] = "Invalid status value";
    }
    
    // Entity type validation
    if (isset($data['entity_type']) && !in_array($data['entity_type'], ['Employee', 'Client', 'Other', null])) {
        $errors[] = "Invalid entity type";
    }
    
    return $errors;
}
?>
