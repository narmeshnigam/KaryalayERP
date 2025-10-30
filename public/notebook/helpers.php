<?php
/**
 * Notebook Module - Helper Functions
 * Core functions for notebook CRUD operations, permissions, sharing, and file handling
 */

/**
 * Check if notebook tables exist
 */
function notebook_tables_exist($conn) {
    $required_tables = ['notebook_notes', 'notebook_attachments', 'notebook_shares', 'notebook_versions'];
    
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
 * Get all notes accessible to the user
 */
function get_all_notes($conn, $user_id, $filters = []) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Build WHERE clause for access control
    $access_clause = "
        (n.created_by = ? 
        OR n.share_scope = 'Organization'
        OR (n.share_scope = 'Team' AND EXISTS (
            SELECT 1 FROM users u1 
            JOIN users u2 ON u1.role_id = u2.role_id 
            WHERE u1.id = ? AND u2.id = n.created_by
        ))
        OR EXISTS (
            SELECT 1 FROM notebook_shares ns 
            WHERE ns.note_id = n.id AND ns.shared_with_id = ?
        ))
    ";
    $where_clauses[] = $access_clause;
    $params[] = $user_id;
    $params[] = $user_id;
    $params[] = $user_id;
    $types .= 'iii';
    
    // Apply filters
    if (!empty($filters['search'])) {
        $where_clauses[] = "(n.title LIKE ? OR n.content LIKE ? OR n.tags LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'sss';
    }
    
    if (!empty($filters['tag'])) {
        $where_clauses[] = "n.tags LIKE ?";
        $params[] = '%' . $filters['tag'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['created_by'])) {
        $where_clauses[] = "n.created_by = ?";
        $params[] = $filters['created_by'];
        $types .= 'i';
    }
    
    if (!empty($filters['share_scope'])) {
        $where_clauses[] = "n.share_scope = ?";
        $params[] = $filters['share_scope'];
        $types .= 's';
    }
    
    if (isset($filters['is_pinned'])) {
        $where_clauses[] = "n.is_pinned = ?";
        $params[] = $filters['is_pinned'] ? 1 : 0;
        $types .= 'i';
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    $sql = "
        SELECT 
            n.*,
            u.username as creator_username,
            u.full_name as creator_full_name,
            (SELECT COUNT(*) FROM notebook_attachments WHERE note_id = n.id) as attachment_count,
            (SELECT COUNT(*) FROM notebook_shares WHERE note_id = n.id) as share_count
        FROM notebook_notes n
        LEFT JOIN users u ON n.created_by = u.id
        $where_sql
        ORDER BY n.is_pinned DESC, n.updated_at DESC, n.created_at DESC
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $notes = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $notes[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        return $notes;
    }
    
    return [];
}

/**
 * Get a specific note by ID
 */
function get_note_by_id($conn, $note_id, $user_id) {
    $sql = "
        SELECT 
            n.*,
            u.username as creator_username,
            u.full_name as creator_full_name
        FROM notebook_notes n
        LEFT JOIN users u ON n.created_by = u.id
        WHERE n.id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $note = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($note && can_access_note($conn, $note_id, $user_id)) {
        return $note;
    }
    
    return null;
}

/**
 * Check if user can access a note
 */
function can_access_note($conn, $note_id, $user_id) {
    $sql = "
        SELECT 1 FROM notebook_notes n
        WHERE n.id = ? AND (
            n.created_by = ?
            OR n.share_scope = 'Organization'
            OR (n.share_scope = 'Team' AND EXISTS (
                SELECT 1 FROM users u1 
                JOIN users u2 ON u1.role_id = u2.role_id 
                WHERE u1.id = ? AND u2.id = n.created_by
            ))
            OR EXISTS (
                SELECT 1 FROM notebook_shares ns 
                WHERE ns.note_id = n.id AND ns.shared_with_id = ?
            )
        )
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iiii', $note_id, $user_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $has_access = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    
    return $has_access;
}

/**
 * Check if user can edit a note
 */
function can_edit_note($conn, $note_id, $user_id) {
    $sql = "
        SELECT 1 FROM notebook_notes n
        WHERE n.id = ? AND (
            n.created_by = ?
            OR EXISTS (
                SELECT 1 FROM notebook_shares ns 
                WHERE ns.note_id = n.id AND ns.shared_with_id = ? AND ns.permission = 'Edit'
            )
        )
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $note_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $can_edit = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    
    return $can_edit;
}

/**
 * Create a new note
 */
function create_note($conn, $data) {
    $sql = "
        INSERT INTO notebook_notes (
            title, content, created_by, linked_entity_id, linked_entity_type,
            share_scope, tags, is_pinned
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'ssiisssi',
        $data['title'],
        $data['content'],
        $data['created_by'],
        $data['linked_entity_id'],
        $data['linked_entity_type'],
        $data['share_scope'],
        $data['tags'],
        $data['is_pinned']
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $note_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Create initial version
        create_note_version($conn, $note_id, 1, $data['content'], $data['created_by']);
        
        return $note_id;
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Update a note
 */
function update_note($conn, $note_id, $data, $user_id) {
    // Get current version number
    $version_sql = "SELECT version, content FROM notebook_notes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $version_sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$current) {
        return false;
    }
    
    $new_version = $current['version'] + 1;
    
    // Update note
    $sql = "
        UPDATE notebook_notes 
        SET title = ?, content = ?, linked_entity_id = ?, linked_entity_type = ?,
            share_scope = ?, tags = ?, is_pinned = ?, version = ?, updated_at = NOW()
        WHERE id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'ssiissiii',
        $data['title'],
        $data['content'],
        $data['linked_entity_id'],
        $data['linked_entity_type'],
        $data['share_scope'],
        $data['tags'],
        $data['is_pinned'],
        $new_version,
        $note_id
    );
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        // Create new version if content changed
        if ($current['content'] !== $data['content']) {
            create_note_version($conn, $note_id, $new_version, $data['content'], $user_id);
        }
        
        return true;
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Delete a note
 */
function delete_note($conn, $note_id) {
    // Get attachments to delete files
    $attachments = get_note_attachments($conn, $note_id);
    
    // Delete note (cascade will handle related records)
    $stmt = mysqli_prepare($conn, "DELETE FROM notebook_notes WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete physical files
    if ($success && !empty($attachments)) {
        foreach ($attachments as $attachment) {
            $file_path = __DIR__ . '/../../' . $attachment['file_path'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
    }
    
    return $success;
}

/**
 * Create a version snapshot
 */
function create_note_version($conn, $note_id, $version_number, $content, $user_id) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO notebook_versions (note_id, version_number, content_snapshot, updated_by)
        VALUES (?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, 'iisi', $note_id, $version_number, $content, $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * Get version history for a note
 */
function get_note_versions($conn, $note_id) {
    $sql = "
        SELECT v.*, u.username as updated_by_username, u.full_name as updated_by_full_name
        FROM notebook_versions v
        LEFT JOIN users u ON v.updated_by = u.id
        WHERE v.note_id = ?
        ORDER BY v.version_number DESC
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $versions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $versions[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $versions;
}

/**
 * Get attachments for a note
 */
function get_note_attachments($conn, $note_id) {
    $sql = "
        SELECT a.*, u.username as uploaded_by_username
        FROM notebook_attachments a
        LEFT JOIN users u ON a.uploaded_by = u.id
        WHERE a.note_id = ?
        ORDER BY a.uploaded_at DESC
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $attachments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $attachments[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $attachments;
}

/**
 * Add attachment to note
 */
function add_note_attachment($conn, $note_id, $file_data, $user_id) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO notebook_attachments (note_id, file_name, file_path, mime_type, file_size, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    mysqli_stmt_bind_param(
        $stmt,
        'isssii',
        $note_id,
        $file_data['file_name'],
        $file_data['file_path'],
        $file_data['mime_type'],
        $file_data['file_size'],
        $user_id
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $attachment_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return $attachment_id;
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Delete an attachment
 */
function delete_attachment($conn, $attachment_id, $note_id, $user_id) {
    // Check if user can edit the note
    if (!can_edit_note($conn, $note_id, $user_id)) {
        return false;
    }
    
    // Get attachment details
    $stmt = mysqli_prepare($conn, "SELECT file_path FROM notebook_attachments WHERE id = ? AND note_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $attachment_id, $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $attachment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$attachment) {
        return false;
    }
    
    // Delete from database
    $stmt = mysqli_prepare($conn, "DELETE FROM notebook_attachments WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $attachment_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete physical file
    if ($success) {
        $file_path = __DIR__ . '/../../' . $attachment['file_path'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
    
    return $success;
}

/**
 * Share note with users or roles
 */
function share_note($conn, $note_id, $shares) {
    // Delete existing shares
    $stmt = mysqli_prepare($conn, "DELETE FROM notebook_shares WHERE note_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Add new shares
    if (empty($shares)) {
        return true;
    }
    
    $stmt = mysqli_prepare($conn, "
        INSERT INTO notebook_shares (note_id, shared_with_id, shared_with_role, permission)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($shares as $share) {
        mysqli_stmt_bind_param(
            $stmt,
            'iiss',
            $note_id,
            $share['shared_with_id'],
            $share['shared_with_role'],
            $share['permission']
        );
        mysqli_stmt_execute($stmt);
    }
    
    mysqli_stmt_close($stmt);
    return true;
}

/**
 * Get shares for a note
 */
function get_note_shares($conn, $note_id) {
    $sql = "
        SELECT ns.*, u.username, u.full_name
        FROM notebook_shares ns
        LEFT JOIN users u ON ns.shared_with_id = u.id
        WHERE ns.note_id = ?
        ORDER BY ns.created_at DESC
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $shares = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $shares[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $shares;
}

/**
 * Get all unique tags
 */
function get_all_tags($conn) {
    $sql = "SELECT DISTINCT tags FROM notebook_notes WHERE tags IS NOT NULL AND tags != ''";
    $result = mysqli_query($conn, $sql);
    
    $all_tags = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['tags'])) {
            $tags = explode(',', $row['tags']);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag) && !in_array($tag, $all_tags)) {
                    $all_tags[] = $tag;
                }
            }
        }
    }
    
    mysqli_free_result($result);
    sort($all_tags);
    return $all_tags;
}

/**
 * Get notebook statistics
 */
function get_notebook_statistics($conn, $user_id) {
    $stats = [
        'total_notes' => 0,
        'my_notes' => 0,
        'shared_with_me' => 0,
        'pinned_notes' => 0,
        'total_attachments' => 0
    ];
    
    // Total accessible notes
    $sql = "
        SELECT COUNT(*) as count FROM notebook_notes n
        WHERE n.created_by = ? 
        OR n.share_scope = 'Organization'
        OR EXISTS (SELECT 1 FROM notebook_shares WHERE note_id = n.id AND shared_with_id = ?)
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['total_notes'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
    
    // My notes
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM notebook_notes WHERE created_by = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['my_notes'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
    
    // Shared with me
    $sql = "
        SELECT COUNT(DISTINCT n.id) as count 
        FROM notebook_notes n
        JOIN notebook_shares ns ON n.id = ns.note_id
        WHERE ns.shared_with_id = ? AND n.created_by != ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['shared_with_me'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
    
    // Pinned notes
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM notebook_notes WHERE created_by = ? AND is_pinned = 1");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['pinned_notes'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
    
    // Total attachments
    $sql = "
        SELECT COUNT(*) as count 
        FROM notebook_attachments a
        JOIN notebook_notes n ON a.note_id = n.id
        WHERE n.created_by = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['total_attachments'] = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);
    
    return $stats;
}

/**
 * Validate note data
 */
function validate_note_data($data, $is_update = false) {
    $errors = [];
    
    // Title validation
    if (empty($data['title'])) {
        $errors[] = "Title is required";
    } elseif (strlen($data['title']) > 200) {
        $errors[] = "Title must not exceed 200 characters";
    }
    
    // Content validation
    if (!$is_update && empty($data['content'])) {
        $errors[] = "Content cannot be empty";
    }
    
    // Share scope validation
    if (!empty($data['share_scope']) && !in_array($data['share_scope'], ['Private', 'Team', 'Organization'])) {
        $errors[] = "Invalid share scope";
    }
    
    // Entity type validation
    if (!empty($data['linked_entity_type']) && !in_array($data['linked_entity_type'], ['Client', 'Project', 'Lead', 'Other'])) {
        $errors[] = "Invalid entity type";
    }
    
    return $errors;
}

/**
 * Handle file upload for notebook attachments
 */
function handle_notebook_file_upload($file, $note_id) {
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                      'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                      'image/jpeg', 'image/png', 'image/gif', 'text/plain'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    $errors = [];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error";
        return ['success' => false, 'errors' => $errors];
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = "File size must not exceed 10MB";
        return ['success' => false, 'errors' => $errors];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $errors[] = "File type not allowed. Allowed: PDF, DOCX, XLSX, PNG, JPG, TXT";
        return ['success' => false, 'errors' => $errors];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $unique_name = 'note_' . $note_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $upload_dir = __DIR__ . '/../../uploads/notebook/';
    $file_path = 'uploads/notebook/' . $unique_name;
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $unique_name)) {
        return [
            'success' => true,
            'file_data' => [
                'file_name' => $file['name'],
                'file_path' => $file_path,
                'mime_type' => $mime_type,
                'file_size' => $file['size']
            ]
        ];
    }
    
    $errors[] = "Failed to move uploaded file";
    return ['success' => false, 'errors' => $errors];
}

/**
 * Format file size
 */
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}
?>
