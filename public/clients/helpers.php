<?php
/**
 * Clients Module - Helper Functions
 * Core business logic and database operations for Clients Management
 */

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/authz.php';

/**
 * Check if clients tables exist
 */
function clients_tables_exist($conn) {
    $tables = ['clients', 'client_addresses', 'client_contacts_map', 'client_documents', 'client_custom_fields'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            return false;
        }
    }
    return true;
}

/**
 * Generate unique client code
 */
function generate_client_code($conn, $name) {
    // Get initials or first 4 chars of name
    $parts = explode(' ', strtoupper(trim($name)));
    if (count($parts) >= 2) {
        $prefix = substr($parts[0], 0, 2) . substr($parts[count($parts)-1], 0, 2);
    } else {
        $prefix = substr(strtoupper(str_replace(' ', '', $name)), 0, 4);
    }
    
    // Find next available number
    $stmt = $conn->prepare("SELECT code FROM clients WHERE code LIKE ? ORDER BY code DESC LIMIT 1");
    $search = $prefix . '%';
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last = $result->fetch_assoc()['code'];
        // Extract number and increment
        if (preg_match('/(\d+)$/', $last, $matches)) {
            $num = (int)$matches[1] + 1;
        } else {
            $num = 1;
        }
    } else {
        $num = 1;
    }
    
    $stmt->close();
    return $prefix . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

/**
 * Get all clients with filters
 */
function get_all_clients($conn, $user_id, $filters = []) {
    $sql = "SELECT c.*, 
            u1.username as owner_username,
            u2.username as created_by_username,
            (SELECT COUNT(*) FROM client_contacts_map WHERE client_id = c.id) as contact_count,
            (SELECT COUNT(*) FROM client_addresses WHERE client_id = c.id) as address_count,
            (SELECT COUNT(*) FROM client_documents WHERE client_id = c.id) as document_count
            FROM clients c
            LEFT JOIN users u1 ON c.owner_id = u1.id
            LEFT JOIN users u2 ON c.created_by = u2.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Search filter
    if (!empty($filters['search'])) {
        $search = "%" . $filters['search'] . "%";
        $sql .= " AND (c.name LIKE ? OR c.legal_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.code LIKE ?)";
        $params = array_merge($params, [$search, $search, $search, $search, $search]);
        $types .= "sssss";
    }
    
    // Status filter
    if (!empty($filters['status'])) {
        $sql .= " AND c.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    // Owner filter
    if (!empty($filters['owner_id'])) {
        $sql .= " AND c.owner_id = ?";
        $params[] = $filters['owner_id'];
        $types .= "i";
    }
    
    // Industry filter
    if (!empty($filters['industry'])) {
        $sql .= " AND c.industry = ?";
        $params[] = $filters['industry'];
        $types .= "s";
    }
    
    // Tag filter
    if (!empty($filters['tag'])) {
        $tag = "%" . $filters['tag'] . "%";
        $sql .= " AND c.tags LIKE ?";
        $params[] = $tag;
        $types .= "s";
    }
    
    $sql .= " ORDER BY c.updated_at DESC, c.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clients = [];
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
    
    $stmt->close();
    return $clients;
}

/**
 * Get client by ID
 */
function get_client_by_id($conn, $client_id) {
    $sql = "SELECT c.*, 
        u1.username as owner_username,
        u2.username as created_by_username
        FROM clients c
        LEFT JOIN users u1 ON c.owner_id = u1.id
        LEFT JOIN users u2 ON c.created_by = u2.id
        WHERE c.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    $stmt->close();
    
    return $client;
}

/**
 * Create a new client
 */
function create_client($conn, $data, $user_id) {
    // Generate unique code
    $code = generate_client_code($conn, $data['name']);
    
    $sql = "INSERT INTO clients 
            (code, name, legal_name, industry, website, email, phone, gstin, status, owner_id, lead_id, tags, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssiissi",
        $code,
        $data['name'],
        $data['legal_name'],
        $data['industry'],
        $data['website'],
        $data['email'],
        $data['phone'],
        $data['gstin'],
        $data['status'],
        $data['owner_id'],
        $data['lead_id'],
        $data['tags'],
        $data['notes'],
        $user_id
    );
    
    $result = $stmt->execute();
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    return $result ? $insert_id : false;
}

/**
 * Update client
 */
function update_client($conn, $client_id, $data) {
    $sql = "UPDATE clients SET
            name = ?, legal_name = ?, industry = ?, website = ?,
            email = ?, phone = ?, gstin = ?, status = ?,
            owner_id = ?, tags = ?, notes = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssssi",
        $data['name'],
        $data['legal_name'],
        $data['industry'],
        $data['website'],
        $data['email'],
        $data['phone'],
        $data['gstin'],
        $data['status'],
        $data['owner_id'],
        $data['tags'],
        $data['notes'],
        $client_id
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Validate client data
 */
function validate_client_data($data) {
    $errors = [];
    
    // Name is mandatory
    if (empty($data['name']) || trim($data['name']) === '') {
        $errors[] = "Client name is required";
    }
    
    // Owner is mandatory
    if (empty($data['owner_id']) || (int)$data['owner_id'] <= 0) {
        $errors[] = "Client owner is required";
    }
    
    // Email validation
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Website validation
    if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
        $errors[] = "Invalid website URL format";
    }
    
    return $errors;
}

/**
 * Find duplicate clients
 */
function find_duplicate_clients($conn, $email = null, $phone = null, $exclude_id = null) {
    $sql = "SELECT id, code, name, email, phone FROM clients WHERE ";
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
 * Get client addresses
 */
function get_client_addresses($conn, $client_id) {
    $sql = "SELECT * FROM client_addresses WHERE client_id = ? ORDER BY is_default DESC, id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $addresses = [];
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }
    
    $stmt->close();
    return $addresses;
}

/**
 * Add client address
 */
function add_client_address($conn, $client_id, $data) {
    // If this is default, unset others
    if ($data['is_default']) {
        $stmt = $conn->prepare("UPDATE client_addresses SET is_default = 0 WHERE client_id = ?");
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $stmt->close();
    }
    
    $sql = "INSERT INTO client_addresses 
            (client_id, label, line1, line2, city, state, zip, country, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssssi",
        $client_id,
        $data['label'],
        $data['line1'],
        $data['line2'],
        $data['city'],
        $data['state'],
        $data['zip'],
        $data['country'],
        $data['is_default']
    );
    
    $result = $stmt->execute();
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    return $result ? $insert_id : false;
}

/**
 * Update client address
 */
function update_client_address($conn, $address_id, $data) {
    // If this is default, unset others
    if ($data['is_default']) {
        $sql_get_client = "SELECT client_id FROM client_addresses WHERE id = ?";
        $stmt = $conn->prepare($sql_get_client);
        $stmt->bind_param("i", $address_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $addr = $result->fetch_assoc();
        $stmt->close();
        
        if ($addr) {
            $stmt = $conn->prepare("UPDATE client_addresses SET is_default = 0 WHERE client_id = ?");
            $stmt->bind_param("i", $addr['client_id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $sql = "UPDATE client_addresses SET
            label = ?, line1 = ?, line2 = ?, city = ?,
            state = ?, zip = ?, country = ?, is_default = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssii",
        $data['label'],
        $data['line1'],
        $data['line2'],
        $data['city'],
        $data['state'],
        $data['zip'],
        $data['country'],
        $data['is_default'],
        $address_id
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Delete client address
 */
function delete_client_address($conn, $address_id) {
    $sql = "DELETE FROM client_addresses WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $address_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get client contacts
 */
function get_client_contacts($conn, $client_id) {
    $sql = "SELECT ccm.*, c.name, c.designation, c.organization, c.phone, c.email, c.whatsapp
            FROM client_contacts_map ccm
            INNER JOIN contacts c ON ccm.contact_id = c.id
            WHERE ccm.client_id = ?
            ORDER BY ccm.id ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $client_id);
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
 * Link contact to client
 */
function link_contact_to_client($conn, $client_id, $contact_id, $role = null) {
    $sql = "INSERT IGNORE INTO client_contacts_map (client_id, contact_id, role_at_client) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $client_id, $contact_id, $role);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Unlink contact from client
 */
function unlink_contact_from_client($conn, $map_id) {
    $sql = "DELETE FROM client_contacts_map WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $map_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get client documents
 */
function get_client_documents($conn, $client_id) {
    $sql = "SELECT cd.*, u.username as uploaded_by_username
            FROM client_documents cd
            LEFT JOIN users u ON cd.uploaded_by = u.id
            WHERE cd.client_id = ?
            ORDER BY cd.uploaded_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    $stmt->close();
    return $documents;
}

/**
 * Upload client document
 */
function upload_client_document($conn, $client_id, $file, $doc_type, $user_id) {
    $upload_dir = __DIR__ . '/../../uploads/clients/';
    
    // Validate file
    $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    if ($file['size'] > 10 * 1024 * 1024) { // 10MB
        return ['success' => false, 'error' => 'File size exceeds 10MB'];
    }
    
    // Generate unique filename
    $filename = 'client_' . $client_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Save to database
        $sql = "INSERT INTO client_documents (client_id, file_name, file_path, doc_type, uploaded_by)
                VALUES (?, ?, ?, ?, ?)";
        
        $relative_path = '/uploads/clients/' . $filename;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssi", $client_id, $file['name'], $relative_path, $doc_type, $user_id);
        
        if ($stmt->execute()) {
            $insert_id = $stmt->insert_id;
            $stmt->close();
            return ['success' => true, 'id' => $insert_id];
        } else {
            $stmt->close();
            unlink($filepath);
            return ['success' => false, 'error' => 'Database error'];
        }
    } else {
        return ['success' => false, 'error' => 'File upload failed'];
    }
}

/**
 * Delete client document
 */
function delete_client_document($conn, $doc_id) {
    // Get file path
    $sql = "SELECT file_path FROM client_documents WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();
    $stmt->close();
    
    if (!$doc) {
        return false;
    }
    
    // Delete from database
    $sql = "DELETE FROM client_documents WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doc_id);
    $result = $stmt->execute();
    $stmt->close();
    
    // Delete physical file
    if ($result) {
        $filepath = __DIR__ . '/../../' . ltrim($doc['file_path'], '/');
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    
    return $result;
}

/**
 * Get client custom fields
 */
function get_client_custom_fields($conn, $client_id) {
    $sql = "SELECT * FROM client_custom_fields WHERE client_id = ? ORDER BY field_key ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $fields = [];
    while ($row = $result->fetch_assoc()) {
        $fields[$row['field_key']] = $row['field_value'];
    }
    
    $stmt->close();
    return $fields;
}

/**
 * Set client custom field
 */
function set_client_custom_field($conn, $client_id, $field_key, $field_value) {
    $sql = "INSERT INTO client_custom_fields (client_id, field_key, field_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $client_id, $field_key, $field_value);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Delete client custom field
 */
function delete_client_custom_field($conn, $field_id) {
    $sql = "DELETE FROM client_custom_fields WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $field_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Convert CRM lead to client
 */
function convert_lead_to_client($conn, $lead_id, $user_id) {
    // Check if lead already converted
    $check_sql = "SELECT id FROM clients WHERE lead_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $lead_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'error' => 'Lead already converted to client'];
    }
    $stmt->close();
    
    // Get lead details
    $lead_sql = "SELECT * FROM crm_leads WHERE id = ?";
    $stmt = $conn->prepare($lead_sql);
    $stmt->bind_param("i", $lead_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lead = $result->fetch_assoc();
    $stmt->close();
    
    if (!$lead) {
        return ['success' => false, 'error' => 'Lead not found'];
    }
    
    // Create client
    $client_data = [
        'name' => $lead['name'] ?? $lead['company'],
        'legal_name' => $lead['company'] ?? null,
        'industry' => $lead['industry'] ?? null,
        'website' => null,
        'email' => $lead['email'] ?? null,
        'phone' => $lead['phone'] ?? null,
        'gstin' => null,
        'status' => 'Active',
        'owner_id' => $lead['assigned_to'] ?? $user_id,
        'lead_id' => $lead_id,
        'tags' => $lead['interests'] ?? null,
        'notes' => "Converted from Lead: " . $lead['name'] . "\nSource: " . ($lead['source'] ?? 'Unknown')
    ];
    
    $client_id = create_client($conn, $client_data, $user_id);
    
    if ($client_id) {
        // Update lead status to Converted
        $update_lead = "UPDATE crm_leads SET status = 'Converted' WHERE id = ?";
        $stmt = $conn->prepare($update_lead);
        $stmt->bind_param("i", $lead_id);
        $stmt->execute();
        $stmt->close();
        
        return ['success' => true, 'client_id' => $client_id];
    } else {
        return ['success' => false, 'error' => 'Failed to create client'];
    }
}

/**
 * Get client statistics
 */
function get_clients_statistics($conn, $user_id = null) {
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'my_clients' => 0,
        'with_projects' => 0
    ];
    
    // Total clients
    $result = $conn->query("SELECT COUNT(*) as cnt FROM clients");
    $stats['total'] = $result->fetch_assoc()['cnt'];
    
    // Active clients
    $result = $conn->query("SELECT COUNT(*) as cnt FROM clients WHERE status = 'Active'");
    $stats['active'] = $result->fetch_assoc()['cnt'];
    
    // Inactive clients
    $result = $conn->query("SELECT COUNT(*) as cnt FROM clients WHERE status = 'Inactive'");
    $stats['inactive'] = $result->fetch_assoc()['cnt'];
    
    // My clients (if user_id provided)
    if ($user_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM clients WHERE owner_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['my_clients'] = $result->fetch_assoc()['cnt'];
        $stmt->close();
    }
    
    // Clients with projects (if projects table exists)
    $table_check = $conn->query("SHOW TABLES LIKE 'projects'");
    if ($table_check && $table_check->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(DISTINCT client_id) as cnt FROM projects WHERE client_id IS NOT NULL");
        if ($result) {
            $stats['with_projects'] = $result->fetch_assoc()['cnt'] ?? 0;
        }
    }
    
    return $stats;
}

/**
 * Get all unique industries
 */
function get_all_industries($conn) {
    $sql = "SELECT DISTINCT industry FROM clients WHERE industry IS NOT NULL AND industry != '' ORDER BY industry ASC";
    $result = $conn->query($sql);
    
    $industries = [];
    while ($row = $result->fetch_assoc()) {
        $industries[] = $row['industry'];
    }
    
    return $industries;
}

/**
 * Get all unique tags
 */
function get_all_client_tags($conn) {
    $sql = "SELECT DISTINCT tags FROM clients WHERE tags IS NOT NULL AND tags != ''";
    $result = $conn->query($sql);
    
    $all_tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags = array_filter(array_map('trim', explode(',', $row['tags'])));
        $all_tags = array_merge($all_tags, $tags);
    }
    
    return array_values(array_unique($all_tags));
}

/**
 * Get client initials for avatar
 */
function get_client_initials($name) {
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

/**
 * Get status badge color
 */
function get_status_color($status) {
    return $status === 'Active' ? '#28a745' : '#6c757d';
}

/**
 * Get status icon
 */
function get_status_icon($status) {
    return $status === 'Active' ? '✓' : '○';
}
?>
