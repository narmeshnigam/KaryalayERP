<?php
/**
 * Contacts Module - Helper Functions
 * Core business logic and database operations for Contacts Management
 */

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/authz.php';

/**
 * Check if contacts tables exist
 */
function contacts_tables_exist($conn) {
    $tables = ['contacts', 'contact_groups', 'contact_group_map'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            return false;
        }
    }
    return true;
}

/**
 * Get all contacts with filters and pagination
 */
function get_all_contacts($conn, $user_id, $filters = []) {
    // Get user's role for permission checking (join user_roles table)
    $user_role_query = "SELECT r.name as role_name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ? LIMIT 1";
    $stmt = $conn->prepare($user_role_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_role = null;
    if ($row = $result->fetch_assoc()) {
        $user_role = $row['role_name'];
    }
    $stmt->close();
    
    $sql = "SELECT c.*, u.username as created_by_username
        FROM contacts c
        LEFT JOIN users u ON c.created_by = u.id
        WHERE 1=1";

    $params = [];
    $types = "";
    
    // Share scope filtering based on role
    if ($user_role !== 'Admin') {
        // Use a simple single-line subquery and concatenate SQL
        $teamSubquery = "SELECT DISTINCT u2.id FROM users u2 JOIN user_roles ur2 ON u2.id = ur2.user_id JOIN roles r2 ON ur2.role_id = r2.id WHERE r2.name = ?";
        $sql .= " AND (c.share_scope = 'Organization' OR (c.share_scope = 'Team' AND c.created_by IN ($teamSubquery)) OR c.created_by = ?)";
        $params[] = $user_role;
        $params[] = $user_id;
        $types .= 'si';
    }
    
    // Search filter
    if (!empty($filters['search'])) {
        $search = "%" . $filters['search'] . "%";
        $sql .= " AND (c.name LIKE ? OR c.organization LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
        $params = array_merge($params, [$search, $search, $search, $search]);
        $types .= "ssss";
    }
    
    // Contact type filter
    if (!empty($filters['contact_type'])) {
        $sql .= " AND c.contact_type = ?";
        $params[] = $filters['contact_type'];
        $types .= "s";
    }
    
    // Tag filter
    if (!empty($filters['tag'])) {
        $tag = "%" . $filters['tag'] . "%";
        $sql .= " AND c.tags LIKE ?";
        $params[] = $tag;
        $types .= "s";
    }
    
    // Share scope filter
    if (!empty($filters['share_scope'])) {
        $sql .= " AND c.share_scope = ?";
        $params[] = $filters['share_scope'];
        $types .= "s";
    }
    
    // Created by filter (for "My Contacts")
    if (!empty($filters['created_by'])) {
        $sql .= " AND c.created_by = ?";
        $params[] = $filters['created_by'];
        $types .= "i";
    }
    
    $sql .= " ORDER BY c.updated_at DESC, c.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = $row;
    }
    
    $stmt->close();
    return $contacts;
}

/**
 * Get contact by ID with permission check
 */
function get_contact_by_id($conn, $contact_id, $user_id) {
    $sql = "SELECT c.*, u.username as created_by_username, GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') as creator_role
        FROM contacts c
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE c.id = ?
        GROUP BY c.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $contact_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $contact = $result->fetch_assoc();
    $stmt->close();
    
    if (!$contact) {
        return null;
    }
    
    // Check if user can access this contact
    if (!can_access_contact($conn, $contact_id, $user_id)) {
        return null;
    }
    
    return $contact;
}

/**
 * Check if user can access a contact
 */
function can_access_contact($conn, $contact_id, $user_id) {
    $sql = "SELECT c.created_by, c.share_scope, GROUP_CONCAT(DISTINCT ur.name ORDER BY ur.name SEPARATOR ', ') as user_role, GROUP_CONCAT(DISTINCT cr.name ORDER BY cr.name SEPARATOR ', ') as creator_role
        FROM contacts c
        LEFT JOIN users u ON u.id = ?
        LEFT JOIN user_roles uur ON u.id = uur.user_id
        LEFT JOIN roles ur ON uur.role_id = ur.id
        LEFT JOIN users creator ON creator.id = c.created_by
        LEFT JOIN user_roles cuur ON creator.id = cuur.user_id
        LEFT JOIN roles cr ON cuur.role_id = cr.id
        WHERE c.id = ?
        GROUP BY c.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $contact_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$data) {
        return false;
    }
    
    // Admin can access everything
    if (strpos($data['user_role'], 'Admin') !== false) {
        return true;
    }
    
    // Owner can access their own contacts
    if ($data['created_by'] == $user_id) {
        return true;
    }
    
    // Organization scope - everyone can access
    if ($data['share_scope'] === 'Organization') {
        return true;
    }
    
    // Team scope - same role can access
    if ($data['share_scope'] === 'Team') {
        // Check if user and creator share any common role
        $user_roles = explode(', ', $data['user_role']);
        $creator_roles = explode(', ', $data['creator_role']);
        if (array_intersect($user_roles, $creator_roles)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if user can edit a contact
 */
function can_edit_contact($conn, $contact_id, $user_id) {
    $sql = "SELECT c.created_by, GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') as role
        FROM contacts c
        LEFT JOIN users u ON u.id = ?
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE c.id = ?
        GROUP BY c.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $contact_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$data) {
        return false;
    }
    
    // Admin can edit everything
    if (strpos($data['role'], 'Admin') !== false) {
        return true;
    }
    
    // Owner can edit their own contacts
    if ($data['created_by'] == $user_id) {
        return true;
    }
    
    return false;
}

/**
 * Create a new contact
 */
function create_contact($conn, $data, $user_id) {
    $sql = "INSERT INTO contacts 
            (name, organization, designation, contact_type, phone, alt_phone, email, whatsapp, 
             linkedin, address, tags, notes, linked_entity_id, linked_entity_type, share_scope, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssssssssi",
        $data['name'],
        $data['organization'],
        $data['designation'],
        $data['contact_type'],
        $data['phone'],
        $data['alt_phone'],
        $data['email'],
        $data['whatsapp'],
        $data['linkedin'],
        $data['address'],
        $data['tags'],
        $data['notes'],
        $data['linked_entity_id'],
        $data['linked_entity_type'],
        $data['share_scope'],
        $user_id
    );
    
    $result = $stmt->execute();
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    return $result ? $insert_id : false;
}

/**
 * Update an existing contact
 */
function update_contact($conn, $contact_id, $data, $user_id) {
    // Check permission
    if (!can_edit_contact($conn, $contact_id, $user_id)) {
        return false;
    }
    
    $sql = "UPDATE contacts SET
            name = ?, organization = ?, designation = ?, contact_type = ?,
            phone = ?, alt_phone = ?, email = ?, whatsapp = ?,
            linkedin = ?, address = ?, tags = ?, notes = ?,
            linked_entity_id = ?, linked_entity_type = ?, share_scope = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssssssssi",
        $data['name'],
        $data['organization'],
        $data['designation'],
        $data['contact_type'],
        $data['phone'],
        $data['alt_phone'],
        $data['email'],
        $data['whatsapp'],
        $data['linkedin'],
        $data['address'],
        $data['tags'],
        $data['notes'],
        $data['linked_entity_id'],
        $data['linked_entity_type'],
        $data['share_scope'],
        $contact_id
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Delete a contact
 */
function delete_contact($conn, $contact_id, $user_id) {
    // Check permission
    if (!can_edit_contact($conn, $contact_id, $user_id)) {
        return false;
    }
    
    $sql = "DELETE FROM contacts WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $contact_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Validate contact data
 */
function validate_contact_data($data) {
    $errors = [];
    
    // Name is mandatory
    if (empty($data['name']) || trim($data['name']) === '') {
        $errors[] = "Contact name is required";
    }
    
    // At least one contact method required
    if (empty($data['phone']) && empty($data['email']) && empty($data['whatsapp'])) {
        $errors[] = "At least one contact method (phone, email, or WhatsApp) is required";
    }
    
    // Email validation
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Tags limit
    if (!empty($data['tags'])) {
        $tags = array_filter(array_map('trim', explode(',', $data['tags'])));
        if (count($tags) > 10) {
            $errors[] = "Maximum 10 tags allowed";
        }
    }
    
    return $errors;
}

/**
 * Check for duplicate contacts
 */
function find_duplicate_contacts($conn, $email = null, $phone = null, $exclude_id = null) {
    $sql = "SELECT id, name, email, phone, organization FROM contacts WHERE ";
    $conditions = [];
    $params = [];
    $types = "";
    
    if (!empty($email)) {
        $conditions[] = "email = ?";
        $params[] = $email;
        $types .= "s";
    }
    
    if (!empty($phone)) {
        $conditions[] = "phone = ?";
        $params[] = $phone;
        $types .= "s";
    }
    
    if (empty($conditions)) {
        return [];
    }
    
    $sql .= "(" . implode(" OR ", $conditions) . ")";
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $duplicates = [];
    while ($row = $result->fetch_assoc()) {
        $duplicates[] = $row;
    }
    
    $stmt->close();
    return $duplicates;
}

/**
 * Get all unique tags
 */
function get_all_contact_tags($conn) {
    $sql = "SELECT DISTINCT tags FROM contacts WHERE tags IS NOT NULL AND tags != ''";
    $result = $conn->query($sql);
    
    $all_tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags = array_filter(array_map('trim', explode(',', $row['tags'])));
        $all_tags = array_merge($all_tags, $tags);
    }
    
    return array_values(array_unique($all_tags));
}

/**
 * Get contacts statistics
 */
function get_contacts_statistics($conn, $user_id) {
    $stats = [
        'total' => 0,
        'my_contacts' => 0,
        'shared' => 0,
        'clients' => 0,
        'vendors' => 0
    ];
    
    // Total accessible contacts
    $contacts = get_all_contacts($conn, $user_id);
    $stats['total'] = count($contacts);
    
    // My contacts
    $my_contacts = get_all_contacts($conn, $user_id, ['created_by' => $user_id]);
    $stats['my_contacts'] = count($my_contacts);
    
    // Shared with me (not created by me)
    $stats['shared'] = $stats['total'] - $stats['my_contacts'];
    
    // By type
    foreach ($contacts as $contact) {
        if ($contact['contact_type'] === 'Client') {
            $stats['clients']++;
        } elseif ($contact['contact_type'] === 'Vendor') {
            $stats['vendors']++;
        }
    }
    
    return $stats;
}

/**
 * Get all contact groups
 */
function get_all_contact_groups($conn, $user_id = null) {
    $sql = "SELECT g.*, u.username as created_by_username, 
            (SELECT COUNT(*) FROM contact_group_map WHERE group_id = g.id) as contact_count
            FROM contact_groups g
            LEFT JOIN users u ON g.created_by = u.id";
    
    if ($user_id) {
        $sql .= " WHERE g.created_by = ?";
    }
    
    $sql .= " ORDER BY g.name ASC";
    
    if ($user_id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
    
    if ($user_id) {
        $stmt->close();
    }
    
    return $groups;
}

/**
 * Create contact group
 */
function create_contact_group($conn, $name, $description, $user_id) {
    $sql = "INSERT INTO contact_groups (name, description, created_by) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $name, $description, $user_id);
    $result = $stmt->execute();
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    return $result ? $insert_id : false;
}

/**
 * Add contact to group
 */
function add_contact_to_group($conn, $contact_id, $group_id) {
    $sql = "INSERT IGNORE INTO contact_group_map (group_id, contact_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $group_id, $contact_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Remove contact from group
 */
function remove_contact_from_group($conn, $contact_id, $group_id) {
    $sql = "DELETE FROM contact_group_map WHERE group_id = ? AND contact_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $group_id, $contact_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get contacts in a group
 */
function get_group_contacts($conn, $group_id, $user_id) {
    $sql = "SELECT c.*, u.username as created_by_username
            FROM contacts c
            INNER JOIN contact_group_map cgm ON c.id = cgm.contact_id
            LEFT JOIN users u ON c.created_by = u.id
            WHERE cgm.group_id = ?
            ORDER BY c.name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        if (can_access_contact($conn, $row['id'], $user_id)) {
            $contacts[] = $row;
        }
    }
    
    $stmt->close();
    return $contacts;
}

/**
 * Delete contact group
 */
function delete_contact_group($conn, $group_id, $user_id) {
    // Check if user created this group or is admin
    $sql = "SELECT created_by FROM contact_groups WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if (!$group) {
        return false;
    }
    
    // Check permission
    $user_role_query = "SELECT role FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_role_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_role = $stmt->get_result()->fetch_assoc()['role'];
    $stmt->close();
    
    if ($group['created_by'] != $user_id && $user_role !== 'Admin') {
        return false;
    }
    
    // Delete group (cascade will handle mappings)
    $sql = "DELETE FROM contact_groups WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $group_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Export contacts to CSV format
 */
function export_contacts_to_csv($contacts) {
    $filename = "contacts_export_" . date('Y-m-d_His') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Name', 'Organization', 'Designation', 'Contact Type', 
        'Phone', 'Alt Phone', 'Email', 'WhatsApp', 'LinkedIn',
        'Address', 'Tags', 'Notes', 'Share Scope', 'Created By', 'Created At'
    ]);
    
    // CSV Data
    foreach ($contacts as $contact) {
        fputcsv($output, [
            $contact['name'],
            $contact['organization'],
            $contact['designation'],
            $contact['contact_type'],
            $contact['phone'],
            $contact['alt_phone'],
            $contact['email'],
            $contact['whatsapp'],
            $contact['linkedin'],
            $contact['address'],
            $contact['tags'],
            $contact['notes'],
            $contact['share_scope'],
            $contact['created_by_username'],
            $contact['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

/**
 * Parse and validate CSV import data
 */
function parse_contact_csv($file_path) {
    $contacts = [];
    $errors = [];
    
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        $headers = fgetcsv($handle);
        $row_number = 1;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            
            // Map CSV columns to contact fields
            $contact = [
                'name' => $data[0] ?? '',
                'organization' => $data[1] ?? null,
                'designation' => $data[2] ?? null,
                'contact_type' => $data[3] ?? 'Personal',
                'phone' => $data[4] ?? null,
                'alt_phone' => $data[5] ?? null,
                'email' => $data[6] ?? null,
                'whatsapp' => $data[7] ?? null,
                'linkedin' => $data[8] ?? null,
                'address' => $data[9] ?? null,
                'tags' => $data[10] ?? null,
                'notes' => $data[11] ?? null,
                'linked_entity_id' => null,
                'linked_entity_type' => null,
                'share_scope' => $data[12] ?? 'Private'
            ];
            
            // Validate
            $validation_errors = validate_contact_data($contact);
            if (!empty($validation_errors)) {
                $errors[] = "Row $row_number: " . implode(", ", $validation_errors);
                continue;
            }
            
            $contacts[] = $contact;
            
            // Limit to 500 contacts per import
            if (count($contacts) >= 500) {
                break;
            }
        }
        
        fclose($handle);
    }
    
    return ['contacts' => $contacts, 'errors' => $errors];
}

/**
 * Get initials from name for avatar
 */
function get_contact_initials($name) {
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

/**
 * Get contact type icon
 */
function get_contact_type_icon($type) {
    $icons = [
        'Client' => '👤',
        'Vendor' => '🏢',
        'Partner' => '🤝',
        'Personal' => '📱',
        'Other' => '📇'
    ];
    return $icons[$type] ?? '📇';
}

/**
 * Get share scope icon
 */
function get_share_scope_icon($scope) {
    $icons = [
        'Private' => '🔒',
        'Team' => '👥',
        'Organization' => '🌐'
    ];
    return $icons[$scope] ?? '🔒';
}
?>
