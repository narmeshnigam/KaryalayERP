<?php
/**
 * Data Transfer Module - Helper Functions
 * Functions for import/export operations, validation, and logging
 */

/**
 * Check if data_transfer_logs table exists
 */
function data_transfer_tables_exist($conn): bool {
    $result = $conn->query("SHOW TABLES LIKE 'data_transfer_logs'");
    return $result && $result->num_rows > 0;
}

/**
 * Get list of accessible tables for import/export
 * Excludes system tables and returns whitelist
 */
function get_accessible_tables($conn): array {
    // System tables to exclude
    $excluded = ['users', 'roles', 'permissions', 'role_permissions', 'user_roles', 'sessions', 'data_transfer_logs'];
    
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    
    if ($result) {
        while ($row = $result->fetch_array()) {
            $table_name = $row[0];
            if (!in_array($table_name, $excluded)) {
                $tables[] = $table_name;
            }
        }
    }
    
    sort($tables);
    return $tables;
}

/**
 * Get table structure/schema
 */
function get_table_structure($conn, string $table): array {
    $structure = [];
    $result = $conn->query("DESCRIBE `$table`");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $structure[] = [
                'field' => $row['Field'],
                'type' => $row['Type'],
                'null' => $row['Null'] === 'YES',
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
    }
    
    return $structure;
}

/**
 * Generate sample CSV file for a table
 */
function generate_sample_csv($conn, string $table): array {
    $structure = get_table_structure($conn, $table);
    
    if (empty($structure)) {
        return ['success' => false, 'message' => 'Table not found or empty structure'];
    }
    
    // Create uploads/samples directory if not exists
    $sample_dir = __DIR__ . '/../../uploads/samples/';
    if (!is_dir($sample_dir)) {
        mkdir($sample_dir, 0755, true);
    }
    
    $filename = 'sample_' . $table . '_' . date('Ymd_His') . '.csv';
    $filepath = $sample_dir . $filename;
    
    $fp = fopen($filepath, 'w');
    if (!$fp) {
        return ['success' => false, 'message' => 'Could not create sample file'];
    }
    
    // Write headers
    $headers = array_column($structure, 'field');
    fputcsv($fp, $headers);
    
    // Write sample row with field hints
    $sample_row = [];
    foreach ($structure as $field) {
        $type = strtolower($field['type']);
        
        if (strpos($type, 'int') !== false) {
            $sample_row[] = '123';
        } elseif (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
            $sample_row[] = '99.99';
        } elseif (strpos($type, 'date') !== false) {
            $sample_row[] = '2025-01-01';
        } elseif (strpos($type, 'time') !== false) {
            $sample_row[] = date('Y-m-d H:i:s');
        } elseif (strpos($type, 'enum') !== false) {
            // Extract enum values
            preg_match("/enum\('(.*)'\)/i", $type, $matches);
            if (!empty($matches[1])) {
                $values = explode("','", $matches[1]);
                $sample_row[] = $values[0];
            } else {
                $sample_row[] = 'value';
            }
        } elseif (strpos($type, 'text') !== false) {
            $sample_row[] = 'Sample text content';
        } else {
            $sample_row[] = 'Sample ' . $field['field'];
        }
    }
    
    fputcsv($fp, $sample_row);
    fclose($fp);
    
    return [
        'success' => true,
        'filepath' => $filepath,
        'filename' => $filename,
        'url' => '../../uploads/samples/' . $filename
    ];
}

/**
 * Create automatic backup before import (does not log - backup is part of import workflow)
 */
function create_backup($conn, string $table, int $user_id): array {
    $backup_dir = __DIR__ . '/../../backups/' . date('Ymd-Hi') . '/';
    if (!is_dir($backup_dir)) {
        if (!@mkdir($backup_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Could not create backup directory'];
        }
    }
    
    $filename = 'backup_' . $table . '_' . date('Ymd_His') . '.csv';
    $filepath = $backup_dir . $filename;
    
    // Export without logging - backup is internal to import process
    $result = export_table_to_csv($conn, $table, $filepath, false);
    
    return $result;
}

/**
 * Export table data to CSV
 * @param bool $is_user_export Whether this is a user-initiated export (for logging purposes)
 */
function export_table_to_csv($conn, string $table, string $filepath = null, bool $is_user_export = true): array {
    // If no filepath provided, create in exports folder
    if (!$filepath) {
        $export_dir = __DIR__ . '/../../uploads/exports/';
        if (!is_dir($export_dir)) {
            mkdir($export_dir, 0755, true);
        }
        $filename = 'export_' . $table . '_' . date('Ymd_His') . '.csv';
        $filepath = $export_dir . $filename;
    }
    
    $fp = @fopen($filepath, 'w');
    if (!$fp) {
        return ['success' => false, 'message' => 'Could not create export file: ' . $filepath];
    }
    
    // Get data
    $result = $conn->query("SELECT * FROM `$table`");
    if (!$result) {
        fclose($fp);
        return ['success' => false, 'message' => 'Error reading table: ' . $conn->error];
    }
    
    $record_count = 0;
    
    // Write headers
    if ($result->num_rows > 0) {
        $first_row = $result->fetch_assoc();
        fputcsv($fp, array_keys($first_row));
        fputcsv($fp, $first_row);
        $record_count++;
        
        // Write remaining rows
        while ($row = $result->fetch_assoc()) {
            fputcsv($fp, $row);
            $record_count++;
        }
    } else {
        // No data, just write headers from structure
        $structure = get_table_structure($conn, $table);
        $headers = array_column($structure, 'field');
        fputcsv($fp, $headers);
    }
    
    fclose($fp);
    
    return [
        'success' => true,
        'filepath' => $filepath,
        'filename' => basename($filepath),
        'record_count' => $record_count
    ];
}

/**
 * Import CSV data into table
 */
function import_csv_to_table($conn, string $table, string $csv_path, int $user_id): array {
    $fp = fopen($csv_path, 'r');
    if (!$fp) {
        return ['success' => false, 'message' => 'Could not open CSV file'];
    }
    
    // Create backup first
    $backup_result = create_backup($conn, $table, $user_id);
    if (!$backup_result['success']) {
        fclose($fp);
        return ['success' => false, 'message' => 'Backup creation failed: ' . $backup_result['message']];
    }
    
    // Get table structure
    $structure = get_table_structure($conn, $table);
    $table_fields = array_column($structure, 'field');
    
    // Read headers
    $csv_headers = fgetcsv($fp);
    if (!$csv_headers) {
        fclose($fp);
        return ['success' => false, 'message' => 'CSV file is empty or invalid'];
    }
    
    // Clean BOM and whitespace from headers
    $csv_headers = array_map(function($h) {
        // Remove BOM if present
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
        return trim($h);
    }, $csv_headers);
    
    // Validate headers
    $invalid_headers = array_diff($csv_headers, $table_fields);
    if (!empty($invalid_headers)) {
        fclose($fp);
        return ['success' => false, 'message' => 'Invalid CSV headers: ' . implode(', ', $invalid_headers) . '. Expected: ' . implode(', ', $table_fields)];
    }
    
    $success_count = 0;
    $failed_count = 0;
    $errors = [];
    $row_number = 1;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        while (($row_data = fgetcsv($fp)) !== false) {
            $row_number++;
            
            if (count($row_data) !== count($csv_headers)) {
                $errors[] = ['row' => $row_number, 'error' => 'Column count mismatch'];
                $failed_count++;
                continue;
            }
            
            // Build associative array
            $data = array_combine($csv_headers, $row_data);
            
            // Clean and prepare data
            foreach ($data as $key => $value) {
                $value = trim($value);
                if ($value === '' || $value === 'NULL' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                    $data[$key] = null;
                } else {
                    $data[$key] = $value;
                }
            }
            
            // Handle foreign key fields that reference users table
            // These fields should use current user ID if the referenced user doesn't exist
            $user_fk_fields = ['created_by', 'updated_by', 'owner_id', 'assigned_to', 'user_id', 'reporting_manager_id'];
            foreach ($user_fk_fields as $fk_field) {
                if (isset($data[$fk_field]) && $data[$fk_field] !== null) {
                    // Check if this user exists
                    $fk_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
                    if ($fk_check) {
                        $fk_id = (int)$data[$fk_field];
                        $fk_check->bind_param('i', $fk_id);
                        $fk_check->execute();
                        $fk_check->store_result();
                        if ($fk_check->num_rows === 0) {
                            // User doesn't exist - set to current user or null
                            $data[$fk_field] = ($fk_field === 'created_by' || $fk_field === 'owner_id') ? $user_id : null;
                        }
                        $fk_check->close();
                    }
                }
            }
            
            // Auto-assign created_by if field exists and not provided
            if (in_array('created_by', $table_fields) && empty($data['created_by'])) {
                $data['created_by'] = $user_id;
            }
            
            // Check if ID provided for update - but first verify the record exists
            $has_id = !empty($data['id']) && is_numeric($data['id']);
            $is_update = false;
            
            if ($has_id) {
                // Check if record with this ID exists
                $check_stmt = $conn->prepare("SELECT id FROM `$table` WHERE id = ?");
                if ($check_stmt) {
                    $check_id = (int)$data['id'];
                    $check_stmt->bind_param('i', $check_id);
                    $check_stmt->execute();
                    $check_stmt->store_result();
                    $is_update = $check_stmt->num_rows > 0;
                    $check_stmt->close();
                }
            }
            
            if ($is_update) {
                // UPDATE existing record
                $id = (int)$data['id'];
                unset($data['id']); // Remove ID from update data
                
                // Filter out null values - only update non-null fields
                $update_data = array_filter($data, function($v) { return $v !== null; });
                
                $set_clause = [];
                $params = [];
                $types = '';
                
                foreach ($update_data as $field => $value) {
                    $set_clause[] = "`$field` = ?";
                    $params[] = $value;
                    $types .= 's';
                }
                
                if (empty($set_clause)) {
                    $errors[] = ['row' => $row_number, 'error' => 'No fields to update'];
                    $failed_count++;
                    continue;
                }
                
                $params[] = $id;
                $types .= 'i';
                
                $sql = "UPDATE `$table` SET " . implode(', ', $set_clause) . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    $errors[] = ['row' => $row_number, 'error' => 'Prepare failed: ' . $conn->error];
                    $failed_count++;
                    continue;
                }
                
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success_count++;
                    } else {
                        // No rows affected - data might be identical, count as success
                        $success_count++;
                    }
                } else {
                    $errors[] = ['row' => $row_number, 'error' => 'Update failed: ' . $stmt->error];
                    $failed_count++;
                }
                
                $stmt->close();
                
            } else {
                // INSERT new record
                unset($data['id']); // Remove ID field for insert
                
                // Get required fields (NOT NULL without default)
                $required_fields = [];
                foreach ($structure as $field_info) {
                    if ($field_info['null'] === false && 
                        $field_info['default'] === null && 
                        $field_info['extra'] !== 'auto_increment' &&
                        $field_info['field'] !== 'created_at' &&
                        $field_info['field'] !== 'updated_at') {
                        $required_fields[] = $field_info['field'];
                    }
                }
                
                // Check for missing required fields
                $missing_required = [];
                foreach ($required_fields as $req_field) {
                    if (!isset($data[$req_field]) || $data[$req_field] === null || $data[$req_field] === '') {
                        $missing_required[] = $req_field;
                    }
                }
                
                if (!empty($missing_required)) {
                    $errors[] = ['row' => $row_number, 'error' => 'Missing required fields: ' . implode(', ', $missing_required)];
                    $failed_count++;
                    continue;
                }
                
                // Filter out null values for optional fields - let DB handle defaults
                $insert_data = array_filter($data, function($v) { return $v !== null && $v !== ''; });
                
                if (empty($insert_data)) {
                    $errors[] = ['row' => $row_number, 'error' => 'No valid data to insert'];
                    $failed_count++;
                    continue;
                }
                
                $fields = array_keys($insert_data);
                $values = array_values($insert_data);
                $types = str_repeat('s', count($values));
                
                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $field_list = '`' . implode('`, `', $fields) . '`';
                
                $sql = "INSERT INTO `$table` ($field_list) VALUES ($placeholders)";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    $errors[] = ['row' => $row_number, 'error' => 'Prepare failed: ' . $conn->error];
                    $failed_count++;
                    continue;
                }
                
                if (!$stmt->bind_param($types, ...$values)) {
                    $errors[] = ['row' => $row_number, 'error' => 'Bind failed: ' . $stmt->error];
                    $failed_count++;
                    $stmt->close();
                    continue;
                }
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success_count++;
                    } else {
                        $errors[] = ['row' => $row_number, 'error' => 'Insert executed but no rows affected'];
                        $failed_count++;
                    }
                } else {
                    $errors[] = ['row' => $row_number, 'error' => 'Insert failed: ' . $stmt->error];
                    $failed_count++;
                }
                
                $stmt->close();
            }
        }
        
        // Commit transaction
        if (!$conn->commit()) {
            $errors[] = ['row' => 0, 'error' => 'Commit failed: ' . $conn->error];
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        fclose($fp);
        return ['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()];
    }
    
    fclose($fp);
    
    // Verify records were actually inserted
    $verify_result = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
    $verify_count = $verify_result ? $verify_result->fetch_assoc()['cnt'] : 0;
    
    // Generate error CSV if there are errors
    $error_file = null;
    if (!empty($errors)) {
        $error_dir = __DIR__ . '/../../uploads/errors/';
        if (!is_dir($error_dir)) {
            mkdir($error_dir, 0755, true);
        }
        
        $error_filename = 'errors_' . $table . '_' . date('Ymd_His') . '.csv';
        $error_filepath = $error_dir . $error_filename;
        
        $efp = fopen($error_filepath, 'w');
        if ($efp) {
            fputcsv($efp, ['Row Number', 'Error Description']);
            foreach ($errors as $error) {
                fputcsv($efp, [$error['row'], $error['error']]);
            }
            fclose($efp);
            $error_file = '../../uploads/errors/' . $error_filename;
        }
    }
    
    $total_count = $success_count + $failed_count;
    
    // Determine actual success - import fails if no rows were successfully imported
    $import_success = $success_count > 0;
    $status = $failed_count === 0 && $success_count > 0 ? 'Success' : ($success_count > 0 ? 'Partial' : 'Failed');
    
    // Log the import
    log_data_transfer($conn, $user_id, $table, 'Import', $csv_path, $total_count, $success_count, $failed_count, $status, json_encode($errors));
    
    // Return failure if no rows were imported
    if (!$import_success) {
        $error_message = 'No rows were imported.';
        if (!empty($errors)) {
            $error_message .= ' First error: ' . $errors[0]['error'];
        }
        return [
            'success' => false,
            'message' => $error_message,
            'total_rows' => $total_count,
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'status' => $status,
            'errors' => $errors,
            'error_file' => $error_file,
            'backup_path' => $backup_result['filepath'],
            'table_count_after' => $verify_count
        ];
    }
    
    return [
        'success' => true,
        'total_rows' => $total_count,
        'success_count' => $success_count,
        'failed_count' => $failed_count,
        'status' => $status,
        'errors' => $errors,
        'error_file' => $error_file,
        'backup_path' => $backup_result['filepath'],
        'table_count_after' => $verify_count
    ];
}

/**
 * Log data transfer operation
 */
function log_data_transfer($conn, int $user_id, string $table_name, string $operation, string $file_path, int $record_count, int $success_count, int $failed_count, string $status, string $error_log = null): bool {
    $stmt = $conn->prepare("INSERT INTO data_transfer_logs (user_id, table_name, operation, file_path, record_count, success_count, failed_count, status, error_log) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('isssiisss', $user_id, $table_name, $operation, $file_path, $record_count, $success_count, $failed_count, $status, $error_log);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get recent activity logs
 */
function get_recent_activities($conn, int $limit = 10): array {
    $sql = "SELECT dt.*, u.username as performed_by 
            FROM data_transfer_logs dt 
            LEFT JOIN users u ON dt.user_id = u.id 
            ORDER BY dt.created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    $stmt->close();
    return $activities;
}

/**
 * Get statistics for dashboard
 */
function get_data_transfer_stats($conn): array {
    $stats = [
        'imports_this_month' => 0,
        'exports_this_month' => 0,
        'rows_imported' => 0,
        'rows_exported' => 0,
        'last_action' => null
    ];
    
    // Get this month's imports
    $result = $conn->query("SELECT COUNT(*) as count, SUM(record_count) as total_rows 
                            FROM data_transfer_logs 
                            WHERE operation = 'Import' 
                            AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                            AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['imports_this_month'] = (int)$row['count'];
        $stats['rows_imported'] = (int)$row['total_rows'];
    }
    
    // Get this month's exports
    $result = $conn->query("SELECT COUNT(*) as count, SUM(record_count) as total_rows 
                            FROM data_transfer_logs 
                            WHERE operation = 'Export' 
                            AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                            AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['exports_this_month'] = (int)$row['count'];
        $stats['rows_exported'] = (int)$row['total_rows'];
    }
    
    // Get last action
    $result = $conn->query("SELECT * FROM data_transfer_logs ORDER BY created_at DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['last_action'] = $row;
    }
    
    return $stats;
}

/**
 * Get paginated activity logs with filters
 */
function get_activity_logs($conn, array $filters = [], int $page = 1, int $per_page = 20): array {
    $offset = ($page - 1) * $per_page;
    
    $where_clauses = [];
    $params = [];
    $types = '';
    
    if (!empty($filters['table_name'])) {
        $where_clauses[] = "dt.table_name = ?";
        $params[] = $filters['table_name'];
        $types .= 's';
    }
    
    if (!empty($filters['operation'])) {
        $where_clauses[] = "dt.operation = ?";
        $params[] = $filters['operation'];
        $types .= 's';
    }
    
    if (!empty($filters['user_id'])) {
        $where_clauses[] = "dt.user_id = ?";
        $params[] = (int)$filters['user_id'];
        $types .= 'i';
    }
    
    if (!empty($filters['date_from'])) {
        $where_clauses[] = "DATE(dt.created_at) >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $where_clauses[] = "DATE(dt.created_at) <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    $where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM data_transfer_logs dt $where_sql";
    $stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Get paginated results
    $sql = "SELECT dt.*, u.username as performed_by 
            FROM data_transfer_logs dt 
            LEFT JOIN users u ON dt.user_id = u.id 
            $where_sql 
            ORDER BY dt.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    $stmt->close();
    
    return [
        'logs' => $logs,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ];
}
