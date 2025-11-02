<?php
/**
 * Catalog Module Helper Functions
 * All business logic for Products & Services (Catalog + Inventory)
 */

if (!function_exists('catalog_tables_exist')) {
    function catalog_tables_exist($conn): bool {
        $required_tables = ['items_master', 'item_inventory_log', 'item_files', 'item_change_log'];
        foreach ($required_tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if (!$result || $result->num_rows === 0) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('generate_item_sku')) {
    function generate_item_sku($conn, $type = 'Product'): string {
        $prefix = ($type === 'Product') ? 'PRD' : 'SRV';
        $timestamp = date('ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $sku = "{$prefix}-{$timestamp}-{$random}";
        
        // Ensure uniqueness
        $check = $conn->prepare("SELECT id FROM items_master WHERE sku = ?");
        $check->bind_param('s', $sku);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $check->close();
            return generate_item_sku($conn, $type); // Retry
        }
        $check->close();
        return $sku;
    }
}

if (!function_exists('get_all_catalog_items')) {
    function get_all_catalog_items($conn, $user_id, $filters = []): array {
        $where = ['1=1'];
        $params = [];
        $types = '';
        
        // Search filter
        if (!empty($filters['search'])) {
            $where[] = "(i.name LIKE ? OR i.sku LIKE ? OR i.category LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= 'sss';
        }
        
        // Type filter
        if (!empty($filters['type'])) {
            $where[] = "i.type = ?";
            $params[] = $filters['type'];
            $types .= 's';
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $where[] = "i.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Category filter
        if (!empty($filters['category'])) {
            $where[] = "i.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        // Low stock filter
        if (!empty($filters['low_stock']) && $filters['low_stock'] === '1') {
            $where[] = "i.type = 'Product' AND i.current_stock <= COALESCE(i.low_stock_threshold, 10)";
        }
        
        // Expiring soon filter
        if (!empty($filters['expiring_days'])) {
            $days = (int)$filters['expiring_days'];
            $where[] = "i.expiry_date IS NOT NULL AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)";
            $params[] = $days;
            $types .= 'i';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT i.*, u.username as created_by_name,
                       CASE WHEN i.expiry_date IS NOT NULL AND i.expiry_date < CURDATE() THEN 1 ELSE 0 END as is_expired,
                       CASE WHEN i.type = 'Product' AND i.current_stock <= COALESCE(i.low_stock_threshold, 10) THEN 1 ELSE 0 END as is_low_stock
                FROM items_master i
                LEFT JOIN users u ON i.created_by = u.id
                WHERE {$whereClause}
                ORDER BY i.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $items;
    }
}

if (!function_exists('get_item_by_id')) {
    function get_item_by_id($conn, $item_id): ?array {
        $stmt = $conn->prepare("SELECT i.*, u.username as created_by_name,
                                       CASE WHEN i.expiry_date IS NOT NULL AND i.expiry_date < CURDATE() THEN 1 ELSE 0 END as is_expired,
                                       CASE WHEN i.type = 'Product' AND i.current_stock <= COALESCE(i.low_stock_threshold, 10) THEN 1 ELSE 0 END as is_low_stock
                                FROM items_master i
                                LEFT JOIN users u ON i.created_by = u.id
                                WHERE i.id = ?");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        return $item ?: null;
    }
}

if (!function_exists('create_catalog_item')) {
    function create_catalog_item($conn, $data, $user_id): array {
        // Validate required fields
        if (empty($data['name']) || empty($data['type'])) {
            return ['success' => false, 'message' => 'Name and Type are required.'];
        }
        
        // Generate SKU if not provided
        $sku = !empty($data['sku']) ? $data['sku'] : generate_item_sku($conn, $data['type']);

        // Validate price
        if (isset($data['base_price']) && $data['base_price'] < 0) {
            return ['success' => false, 'message' => 'Base price cannot be negative.'];
        }

        // Validate expiry date
        if (!empty($data['expiry_date'])) {
            $expiry = strtotime($data['expiry_date']);
            if ($expiry < strtotime('today')) {
                return ['success' => false, 'message' => 'Expiry date must be today or in the future.'];
            }
        }

        // Services cannot have stock
        if ($data['type'] === 'Service') {
            $data['current_stock'] = 0;
            $data['low_stock_threshold'] = null;
        }

        // Assign all fields to variables (for bind_param)
        $name = $data['name'];
        $type = $data['type'];
        $category = $data['category'] ?? null;
        $description_html = $data['description_html'] ?? null;
        $base_price = $data['base_price'] ?? 0.00;
        $tax_percent = $data['tax_percent'] ?? 0.00;
        $default_discount = $data['default_discount'] ?? 0.00;
        $primary_image = $data['primary_image'] ?? null;
        $brochure_pdf = $data['brochure_pdf'] ?? null;
        $expiry_date = $data['expiry_date'] ?? null;
        $current_stock = $data['current_stock'] ?? 0;
        $low_stock_threshold = $data['low_stock_threshold'] ?? null;
        $status = $data['status'] ?? 'Active';

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO items_master 
                                   (sku, name, type, category, description_html, base_price, tax_percent, default_discount, 
                                    primary_image, brochure_pdf, expiry_date, current_stock, low_stock_threshold, status, created_by)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Type string: s=string, d=double, i=integer
            $stmt->bind_param(
                'sssssdddsssiisi',
                $sku,
                $name,
                $type,
                $category,
                $description_html,
                $base_price,
                $tax_percent,
                $default_discount,
                $primary_image,
                $brochure_pdf,
                $expiry_date,
                $current_stock,
                $low_stock_threshold,
                $status,
                $user_id
            );

            if (!$stmt->execute()) {
                throw new Exception('Failed to create item: ' . $stmt->error);
            }

            $item_id = $stmt->insert_id;
            $stmt->close();

            // Log creation
            log_item_change($conn, $item_id, 'Create', json_encode($data), $user_id);

            $conn->commit();
            return ['success' => true, 'message' => 'Item created successfully.', 'item_id' => $item_id];

        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('update_catalog_item')) {
    function update_catalog_item($conn, $item_id, $data, $user_id): array {
        $item = get_item_by_id($conn, $item_id);
        if (!$item) {
            return ['success' => false, 'message' => 'Item not found.'];
        }
        
        // Track changes for audit
        $changes = [];
        $change_type = 'Update';
        
        // Check price change
        if (isset($data['base_price']) && $data['base_price'] != $item['base_price']) {
            $changes['base_price'] = ['from' => $item['base_price'], 'to' => $data['base_price']];
            $change_type = 'PriceChange';
        }
        
        // Services cannot have stock
        if ($item['type'] === 'Service') {
            $data['current_stock'] = 0;
            $data['low_stock_threshold'] = null;
        }
        
        $conn->begin_transaction();
        
        try {
            $fields = [];
            $params = [];
            $types = '';
            
            foreach (['name', 'category', 'description_html', 'base_price', 'tax_percent', 'default_discount',
                      'primary_image', 'brochure_pdf', 'expiry_date', 'low_stock_threshold', 'status'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = ?";
                    $params[] = $data[$field];
                    
                    if (in_array($field, ['base_price', 'tax_percent', 'default_discount'])) {
                        $types .= 'd';
                    } elseif ($field === 'low_stock_threshold') {
                        $types .= 'i';
                    } else {
                        $types .= 's';
                    }
                }
            }
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'No fields to update.'];
            }
            
            $params[] = $item_id;
            $types .= 'i';
            
            $sql = "UPDATE items_master SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update item: ' . $stmt->error);
            }
            $stmt->close();
            
            // Log change
            if (!empty($changes)) {
                log_item_change($conn, $item_id, $change_type, json_encode($changes), $user_id);
            }
            
            $conn->commit();
            return ['success' => true, 'message' => 'Item updated successfully.'];
            
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('adjust_item_stock')) {
    function adjust_item_stock($conn, $item_id, $action, $quantity, $reason, $reference_type, $reference_id, $user_id): array {
        $item = get_item_by_id($conn, $item_id);
        
        if (!$item) {
            return ['success' => false, 'message' => 'Item not found.'];
        }
        
        if ($item['type'] === 'Service') {
            return ['success' => false, 'message' => 'Cannot adjust stock for services.'];
        }
        
        $quantity = (int)$quantity;
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Quantity must be greater than zero.'];
        }
        
        $qty_before = (int)$item['current_stock'];
        $quantity_delta = 0;
        
        switch ($action) {
            case 'Add':
                $quantity_delta = $quantity;
                break;
            case 'Reduce':
            case 'InvoiceDeduct':
                $quantity_delta = -$quantity;
                break;
            case 'Correction':
                // Correction sets absolute value
                $quantity_delta = $quantity - $qty_before;
                break;
            default:
                return ['success' => false, 'message' => 'Invalid action.'];
        }
        
        $qty_after = $qty_before + $quantity_delta;
        
        // Prevent negative stock (unless Correction with override)
        if ($qty_after < 0 && $action !== 'Correction') {
            return ['success' => false, 'message' => 'Insufficient stock. Current: ' . $qty_before];
        }
        
        $conn->begin_transaction();
        
        try {
            // Update stock
            $stmt = $conn->prepare("UPDATE items_master SET current_stock = ? WHERE id = ?");
            $stmt->bind_param('ii', $qty_after, $item_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to update stock: ' . $stmt->error);
            }
            $stmt->close();
            
            // Log movement
            $log_stmt = $conn->prepare("INSERT INTO item_inventory_log 
                                       (item_id, action, quantity_delta, qty_before, qty_after, reason, reference_type, reference_id, created_by)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $log_stmt->bind_param('isiiissii', $item_id, $action, $quantity_delta, $qty_before, $qty_after, 
                                   $reason, $reference_type, $reference_id, $user_id);
            if (!$log_stmt->execute()) {
                throw new Exception('Failed to log stock movement: ' . $log_stmt->error);
            }
            $log_stmt->close();
            
            $conn->commit();
            return ['success' => true, 'message' => 'Stock adjusted successfully.', 'new_stock' => $qty_after];
            
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('get_item_inventory_log')) {
    function get_item_inventory_log($conn, $item_id): array {
        $stmt = $conn->prepare("SELECT l.*, u.username as created_by_name
                                FROM item_inventory_log l
                                LEFT JOIN users u ON l.created_by = u.id
                                WHERE l.item_id = ?
                                ORDER BY l.created_at DESC");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $logs;
    }
}

if (!function_exists('get_item_files')) {
    function get_item_files($conn, $item_id): array {
        $stmt = $conn->prepare("SELECT f.*, u.username as uploaded_by_name
                                FROM item_files f
                                LEFT JOIN users u ON f.uploaded_by = u.id
                                WHERE f.item_id = ?
                                ORDER BY f.uploaded_at DESC");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $files = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $files;
    }
}

if (!function_exists('get_item_change_log')) {
    function get_item_change_log($conn, $item_id): array {
        $stmt = $conn->prepare("SELECT l.*, u.username as changed_by_name
                                FROM item_change_log l
                                LEFT JOIN users u ON l.changed_by = u.id
                                WHERE l.item_id = ?
                                ORDER BY l.created_at DESC");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $logs;
    }
}

if (!function_exists('log_item_change')) {
    function log_item_change($conn, $item_id, $change_type, $changed_fields, $user_id): bool {
        $stmt = $conn->prepare("INSERT INTO item_change_log (item_id, change_type, changed_fields, changed_by)
                                VALUES (?, ?, ?, ?)");
        $stmt->bind_param('issi', $item_id, $change_type, $changed_fields, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}

if (!function_exists('upload_item_file')) {
    function upload_item_file($conn, $item_id, $file_type, $file, $user_id): array {
        $allowed_types = [
            'PrimaryImage' => ['image/jpeg', 'image/png', 'image/jpg'],
            'Brochure' => ['application/pdf']
        ];
        
        $max_sizes = [
            'PrimaryImage' => 2 * 1024 * 1024, // 2MB
            'Brochure' => 10 * 1024 * 1024      // 10MB
        ];
        
        if (!isset($allowed_types[$file_type])) {
            return ['success' => false, 'message' => 'Invalid file type.'];
        }
        
        if (!in_array($file['type'], $allowed_types[$file_type])) {
            return ['success' => false, 'message' => 'Invalid file format.'];
        }
        
        if ($file['size'] > $max_sizes[$file_type]) {
            $max_mb = $max_sizes[$file_type] / (1024 * 1024);
            return ['success' => false, 'message' => "File size exceeds {$max_mb}MB limit."];
        }
        
        $upload_dir = __DIR__ . '/../../uploads/catalog/' . ($file_type === 'PrimaryImage' ? 'images' : 'brochures');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'item_' . $item_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . '/' . $filename;
        $relative_path = '/uploads/catalog/' . ($file_type === 'PrimaryImage' ? 'images' : 'brochures') . '/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to upload file.'];
        }
        
        $conn->begin_transaction();
        
        try {
            // Update items_master
            $field = ($file_type === 'PrimaryImage') ? 'primary_image' : 'brochure_pdf';
            $stmt = $conn->prepare("UPDATE items_master SET $field = ? WHERE id = ?");
            $stmt->bind_param('si', $relative_path, $item_id);
            $stmt->execute();
            $stmt->close();
            
            // Log in item_files
            $file_stmt = $conn->prepare("INSERT INTO item_files (item_id, file_type, file_path, uploaded_by)
                                         VALUES (?, ?, ?, ?)");
            $file_stmt->bind_param('issi', $item_id, $file_type, $relative_path, $user_id);
            $file_stmt->execute();
            $file_stmt->close();
            
            // Log change
            log_item_change($conn, $item_id, 'FileChange', json_encode([$file_type => $relative_path]), $user_id);
            
            $conn->commit();
            return ['success' => true, 'message' => 'File uploaded successfully.', 'path' => $relative_path];
            
        } catch (Exception $e) {
            $conn->rollback();
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('activate_item')) {
    function activate_item($conn, $item_id, $user_id): array {
        $stmt = $conn->prepare("UPDATE items_master SET status = 'Active' WHERE id = ?");
        $stmt->bind_param('i', $item_id);
        if ($stmt->execute()) {
            $stmt->close();
            log_item_change($conn, $item_id, 'Activate', null, $user_id);
            return ['success' => true, 'message' => 'Item activated.'];
        }
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to activate item.'];
    }
}

if (!function_exists('deactivate_item')) {
    function deactivate_item($conn, $item_id, $user_id): array {
        $stmt = $conn->prepare("UPDATE items_master SET status = 'Inactive' WHERE id = ?");
        $stmt->bind_param('i', $item_id);
        if ($stmt->execute()) {
            $stmt->close();
            log_item_change($conn, $item_id, 'Deactivate', null, $user_id);
            return ['success' => true, 'message' => 'Item deactivated.'];
        }
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to deactivate item.'];
    }
}

if (!function_exists('get_catalog_statistics')) {
    function get_catalog_statistics($conn): array {
        $stats = [
            'total_items' => 0,
            'products' => 0,
            'services' => 0,
            'active_items' => 0,
            'low_stock_items' => 0,
            'expiring_soon' => 0,
            'total_stock_value' => 0
        ];
        
        // Total counts
        $result = $conn->query("SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN type = 'Product' THEN 1 ELSE 0 END) as products,
                                SUM(CASE WHEN type = 'Service' THEN 1 ELSE 0 END) as services,
                                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                                SUM(CASE WHEN type = 'Product' AND current_stock <= COALESCE(low_stock_threshold, 10) THEN 1 ELSE 0 END) as low_stock,
                                SUM(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon,
                                SUM(CASE WHEN type = 'Product' THEN current_stock * base_price ELSE 0 END) as stock_value
                                FROM items_master");
        
        if ($row = $result->fetch_assoc()) {
            $stats['total_items'] = (int)$row['total'];
            $stats['products'] = (int)$row['products'];
            $stats['services'] = (int)$row['services'];
            $stats['active_items'] = (int)$row['active'];
            $stats['low_stock_items'] = (int)$row['low_stock'];
            $stats['expiring_soon'] = (int)$row['expiring_soon'];
            $stats['total_stock_value'] = (float)$row['stock_value'];
        }
        
        return $stats;
    }
}

if (!function_exists('get_all_categories')) {
    function get_all_categories($conn): array {
        $result = $conn->query("SELECT DISTINCT category FROM items_master WHERE category IS NOT NULL ORDER BY category ASC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('export_catalog_csv')) {
    function export_catalog_csv($conn, $filters = []): void {
        $items = get_all_catalog_items($conn, null, $filters);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=catalog_export_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['SKU', 'Name', 'Type', 'Category', 'Base Price', 'Tax %', 'Discount', 'Current Stock', 'Status', 'Expiry Date', 'Created At']);
        
        // Data
        foreach ($items as $item) {
            fputcsv($output, [
                $item['sku'],
                $item['name'],
                $item['type'],
                $item['category'] ?? '',
                $item['base_price'],
                $item['tax_percent'],
                $item['default_discount'],
                $item['current_stock'],
                $item['status'],
                $item['expiry_date'] ?? '',
                $item['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
}
