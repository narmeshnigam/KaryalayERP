<?php
/**
 * Quotations Module Helper Functions
 * All business logic for quotation management
 */

if (!function_exists('quotations_tables_exist')) {
    function quotations_tables_exist($conn): bool {
        $required_tables = ['quotations', 'quotation_items', 'quotation_activity_log'];
        foreach ($required_tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
            if (!$result || $result->num_rows === 0) {
                if ($result) {
                    $result->free();
                }
                return false;
            }
            $result->free();
        }
        return true;
    }
}

if (!function_exists('generate_quotation_no')) {
    function generate_quotation_no($conn): string {
        $year = date('Y');
        $prefix = "QT-{$year}-";
        
        // Get the last quotation number for this year
        $stmt = $conn->prepare("SELECT quotation_no FROM quotations WHERE quotation_no LIKE ? ORDER BY id DESC LIMIT 1");
        $pattern = $prefix . '%';
        $stmt->bind_param('s', $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Extract number and increment
            $last_no = (int)substr($row['quotation_no'], strlen($prefix));
            $new_no = $last_no + 1;
        } else {
            $new_no = 1;
        }
        
        $stmt->close();
        return $prefix . str_pad($new_no, 3, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('get_all_quotations')) {
    function get_all_quotations($conn, $user_id, $filters = []): array {
        $where = ['1=1'];
        $params = [];
        $types = '';
        
        // Search filter
        if (!empty($filters['search'])) {
            $where[] = "(q.quotation_no LIKE ? OR q.title LIKE ? OR c.name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= 'sss';
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $where[] = "q.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Client filter
        if (!empty($filters['client_id'])) {
            $where[] = "q.client_id = ?";
            $params[] = $filters['client_id'];
            $types .= 'i';
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $where[] = "q.quotation_date >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "q.quotation_date <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Expiring soon filter
        if (!empty($filters['expiring_days'])) {
            $days = (int)$filters['expiring_days'];
            $where[] = "q.validity_date IS NOT NULL AND q.validity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)";
            $params[] = $days;
            $types .= 'i';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT q.*, 
                       c.name as client_name,
                       c.email as client_email,
                       u.username as created_by_name,
                       CASE WHEN q.validity_date IS NOT NULL AND q.validity_date < CURDATE() AND q.status NOT IN ('Accepted','Rejected') THEN 1 ELSE 0 END as is_expired,
                       (SELECT COUNT(*) FROM quotation_items WHERE quotation_id = q.id) as item_count
                FROM quotations q
                LEFT JOIN clients c ON q.client_id = c.id
                LEFT JOIN users u ON q.created_by = u.id
                WHERE {$whereClause}
                ORDER BY q.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $refs = [];
            foreach ($params as $key => $value) {
                $refs[$key] = &$params[$key];
            }
            array_unshift($refs, $types);
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $quotations = [];
        
        while ($row = $result->fetch_assoc()) {
            // Auto-update expired quotations
            if ($row['is_expired'] && $row['status'] === 'Sent') {
                update_quotation_status($conn, $row['id'], 'Expired', $user_id);
                $row['status'] = 'Expired';
            }
            $quotations[] = $row;
        }
        
        $stmt->close();
        return $quotations;
    }
}

if (!function_exists('get_quotation_by_id')) {
    function get_quotation_by_id($conn, int $id): ?array {
        $stmt = $conn->prepare("
            SELECT q.*, 
                   c.name as client_name,
                   c.email as client_email,
                   c.phone as client_phone,
                   u.username as created_by_name,
                   u.full_name as created_by_full_name
            FROM quotations q
            LEFT JOIN clients c ON q.client_id = c.id
            LEFT JOIN users u ON q.created_by = u.id
            WHERE q.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $quotation = $result->fetch_assoc();
        $stmt->close();
        if ($quotation && !empty($quotation['client_id'])) {
            $quotation['client_address'] = get_client_primary_address_text($conn, (int)$quotation['client_id']);
        }
        return $quotation ?: null;
    }
}

if (!function_exists('get_quotation_items')) {
    function get_quotation_items($conn, int $quotation_id): array {
        $stmt = $conn->prepare("
            SELECT qi.*, 
                   im.name as item_name,
                   im.sku as item_sku,
                   im.type as item_type
            FROM quotation_items qi
            LEFT JOIN items_master im ON qi.item_id = im.id
            WHERE qi.quotation_id = ?
            ORDER BY qi.sort_order ASC, qi.id ASC
        ");
        $stmt->bind_param('i', $quotation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }
}

if (!function_exists('get_quotation_activity_log')) {
    function get_quotation_activity_log($conn, int $quotation_id): array {
        $stmt = $conn->prepare("
            SELECT qal.*, 
                   u.username as user_name,
                   u.full_name as user_full_name
            FROM quotation_activity_log qal
            LEFT JOIN users u ON qal.user_id = u.id
            WHERE qal.quotation_id = ?
            ORDER BY qal.created_at DESC
        ");
        $stmt->bind_param('i', $quotation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
        return $logs;
    }
}

if (!function_exists('create_quotation')) {
    function create_quotation($conn, array $data, int $user_id): array {
        // Validate required fields
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required'];
        }
        if (empty($data['client_id']) && empty($data['lead_id'])) {
            return ['success' => false, 'message' => 'Client or Lead is required'];
        }
        if (empty($data['quotation_date'])) {
            return ['success' => false, 'message' => 'Quotation date is required'];
        }
        
        // Generate quotation number
        $quotation_no = generate_quotation_no($conn);
        
        // Prepare data
        $client_id = !empty($data['client_id']) ? (int)$data['client_id'] : null;
        $lead_id = !empty($data['lead_id']) ? (int)$data['lead_id'] : null;
        $project_id = !empty($data['project_id']) ? (int)$data['project_id'] : null;
        $title = trim($data['title']);
        $quotation_date = $data['quotation_date'];
        $validity_date = !empty($data['validity_date']) ? $data['validity_date'] : null;
        $currency = $data['currency'] ?? 'INR';
        $status = $data['status'] ?? 'Draft';
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;
        $terms = !empty($data['terms']) ? trim($data['terms']) : null;
        $brochure_pdf = $data['brochure_pdf'] ?? null;
        
        // Insert quotation
        $stmt = $conn->prepare("
            INSERT INTO quotations (
                quotation_no, client_id, lead_id, project_id, title, quotation_date, 
                validity_date, currency, status, notes, terms, brochure_pdf, 
                subtotal, tax_amount, discount_amount, total_amount, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?)
        ");
        
        $stmt->bind_param('siiissssssssi', 
            $quotation_no, $client_id, $lead_id, $project_id, $title, $quotation_date,
            $validity_date, $currency, $status, $notes, $terms, $brochure_pdf, $user_id
        );
        
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to create quotation: ' . $stmt->error];
        }
        
        $quotation_id = $stmt->insert_id;
        $stmt->close();
        
        // Log activity
        log_quotation_activity($conn, $quotation_id, $user_id, 'Create', "Quotation $quotation_no created");
        
        return ['success' => true, 'message' => 'Quotation created successfully', 'quotation_id' => $quotation_id];
    }
}

if (!function_exists('update_quotation')) {
    function update_quotation($conn, int $quotation_id, array $data, int $user_id): array {
        // Validate
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required'];
        }
        
        // Prepare data
        $title = trim($data['title']);
        $quotation_date = $data['quotation_date'];
        $validity_date = !empty($data['validity_date']) ? $data['validity_date'] : null;
        $status = $data['status'] ?? 'Draft';
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;
        $terms = !empty($data['terms']) ? trim($data['terms']) : null;
        
        $stmt = $conn->prepare("
            UPDATE quotations 
            SET title = ?, quotation_date = ?, validity_date = ?, 
                status = ?, notes = ?, terms = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param('ssssssi', $title, $quotation_date, $validity_date, $status, $notes, $terms, $quotation_id);
        
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to update quotation: ' . $stmt->error];
        }
        
        $stmt->close();
        
        // Log activity
        log_quotation_activity($conn, $quotation_id, $user_id, 'Update', 'Quotation details updated');
        
        return ['success' => true, 'message' => 'Quotation updated successfully'];
    }
}

if (!function_exists('add_quotation_item')) {
    function add_quotation_item($conn, int $quotation_id, array $item_data, int $user_id): array {
        // Validate
        if (empty($item_data['item_id'])) {
            return ['success' => false, 'message' => 'Item is required'];
        }
        if (empty($item_data['quantity']) || $item_data['quantity'] <= 0) {
            return ['success' => false, 'message' => 'Quantity must be greater than 0'];
        }
        
        $item_id = (int)$item_data['item_id'];
        $description = !empty($item_data['description']) ? trim($item_data['description']) : null;
        $quantity = (float)$item_data['quantity'];
        $unit_price = (float)($item_data['unit_price'] ?? 0);
        $tax_percent = (float)($item_data['tax_percent'] ?? 0);
        $discount = (float)($item_data['discount'] ?? 0);
        
        // Calculate total
        $line_subtotal = $quantity * $unit_price;
        $line_discount = $discount;
        $line_after_discount = $line_subtotal - $line_discount;
        $line_tax = ($line_after_discount * $tax_percent) / 100;
        $total = $line_after_discount + $line_tax;
        
        // Get sort order
        $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM quotation_items WHERE quotation_id = ?");
        $stmt->bind_param('i', $quotation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $sort_order = $row['next_order'];
        $stmt->close();
        
        // Insert item
        $stmt = $conn->prepare("
            INSERT INTO quotation_items (
                quotation_id, item_id, description, quantity, unit_price, 
                tax_percent, discount, total, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('iisdddddi', 
            $quotation_id, $item_id, $description, $quantity, $unit_price, 
            $tax_percent, $discount, $total, $sort_order
        );
        
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to add item: ' . $stmt->error];
        }
        
        $stmt->close();
        
        // Recalculate quotation totals
        recalculate_quotation_totals($conn, $quotation_id);
        
        return ['success' => true, 'message' => 'Item added successfully'];
    }
}

if (!function_exists('update_quotation_item')) {
    function update_quotation_item($conn, int $item_id, array $item_data, int $user_id): array {
        // Validate
        if (empty($item_data['quantity']) || $item_data['quantity'] <= 0) {
            return ['success' => false, 'message' => 'Quantity must be greater than 0'];
        }
        
        $description = !empty($item_data['description']) ? trim($item_data['description']) : null;
        $quantity = (float)$item_data['quantity'];
        $unit_price = (float)($item_data['unit_price'] ?? 0);
        $tax_percent = (float)($item_data['tax_percent'] ?? 0);
        $discount = (float)($item_data['discount'] ?? 0);
        
        // Calculate total
        $line_subtotal = $quantity * $unit_price;
        $line_discount = $discount;
        $line_after_discount = $line_subtotal - $line_discount;
        $line_tax = ($line_after_discount * $tax_percent) / 100;
        $total = $line_after_discount + $line_tax;
        
        // Get quotation_id for recalculation
        $stmt = $conn->prepare("SELECT quotation_id FROM quotation_items WHERE id = ?");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $quotation_id = $row['quotation_id'];
        $stmt->close();
        
        // Update item
        $stmt = $conn->prepare("
            UPDATE quotation_items 
            SET description = ?, quantity = ?, unit_price = ?, 
                tax_percent = ?, discount = ?, total = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param('sdddddi', $description, $quantity, $unit_price, $tax_percent, $discount, $total, $item_id);
        
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to update item: ' . $stmt->error];
        }
        
        $stmt->close();
        
        // Recalculate quotation totals
        recalculate_quotation_totals($conn, $quotation_id);
        
        return ['success' => true, 'message' => 'Item updated successfully'];
    }
}

if (!function_exists('delete_quotation_item')) {
    function delete_quotation_item($conn, int $item_id, int $user_id): array {
        // Get quotation_id for recalculation
        $stmt = $conn->prepare("SELECT quotation_id FROM quotation_items WHERE id = ?");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $quotation_id = $row['quotation_id'];
        $stmt->close();
        
        // Delete item
        $stmt = $conn->prepare("DELETE FROM quotation_items WHERE id = ?");
        $stmt->bind_param('i', $item_id);
        
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to delete item: ' . $stmt->error];
        }
        
        $stmt->close();
        
        // Recalculate quotation totals
        recalculate_quotation_totals($conn, $quotation_id);
        
        return ['success' => true, 'message' => 'Item deleted successfully'];
    }
}

if (!function_exists('recalculate_quotation_totals')) {
    function recalculate_quotation_totals($conn, int $quotation_id): void {
        // Get all items for this quotation
        $stmt = $conn->prepare("
            SELECT SUM(quantity * unit_price) as subtotal,
                   SUM(discount) as discount_amount,
                   SUM(total) as total_amount,
                   SUM((quantity * unit_price - discount) * tax_percent / 100) as tax_amount
            FROM quotation_items
            WHERE quotation_id = ?
        ");
        $stmt->bind_param('i', $quotation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $totals = $result->fetch_assoc();
        $stmt->close();
        
        $subtotal = $totals['subtotal'] ?? 0;
        $discount_amount = $totals['discount_amount'] ?? 0;
        $tax_amount = $totals['tax_amount'] ?? 0;
        $total_amount = $totals['total_amount'] ?? 0;
        
        // Update quotation
        $stmt = $conn->prepare("
            UPDATE quotations 
            SET subtotal = ?, tax_amount = ?, discount_amount = ?, total_amount = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ddddi', $subtotal, $tax_amount, $discount_amount, $total_amount, $quotation_id);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('update_quotation_status')) {
    function update_quotation_status($conn, int $quotation_id, string $status, int $user_id): array {
        $valid_statuses = ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'];
        if (!in_array($status, $valid_statuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }
        
        $stmt = $conn->prepare("UPDATE quotations SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $quotation_id);
        
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to update status: ' . $stmt->error];
        }
        
        $stmt->close();
        
        // Log activity
        log_quotation_activity($conn, $quotation_id, $user_id, 'StatusChange', "Status changed to: $status");
        
        return ['success' => true, 'message' => 'Status updated successfully'];
    }
}

if (!function_exists('delete_quotation')) {
    function delete_quotation($conn, int $quotation_id, int $user_id): array {
        // Log before deletion
        $quotation = get_quotation_by_id($conn, $quotation_id);
        if (!$quotation) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }
        
        log_quotation_activity($conn, $quotation_id, $user_id, 'Delete', "Quotation {$quotation['quotation_no']} deleted");
        
        // Delete quotation (cascade will handle items and logs)
        $stmt = $conn->prepare("DELETE FROM quotations WHERE id = ?");
        $stmt->bind_param('i', $quotation_id);
        
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to delete quotation: ' . $stmt->error];
        }
        
        $stmt->close();
        
        return ['success' => true, 'message' => 'Quotation deleted successfully'];
    }
}

if (!function_exists('log_quotation_activity')) {
    function log_quotation_activity($conn, int $quotation_id, int $user_id, string $action, string $description = null): void {
        $stmt = $conn->prepare("
            INSERT INTO quotation_activity_log (quotation_id, user_id, action, description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('iiss', $quotation_id, $user_id, $action, $description);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('get_clients_for_dropdown')) {
    function get_clients_for_dropdown($conn): array {
        $result = $conn->query("SELECT id, name, email FROM clients WHERE status = 'Active' ORDER BY name ASC");
        $clients = [];
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
        return $clients;
    }
}

if (!function_exists('get_client_primary_address_text')) {
    function get_client_primary_address_text($conn, int $client_id): ?string {
        $stmt = $conn->prepare("SELECT line1, line2, city, state, zip, country FROM client_addresses WHERE client_id = ? ORDER BY is_default DESC, id ASC LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $address = $result->fetch_assoc();
        $stmt->close();
        if (!$address) {
            return null;
        }
        $parts = array_filter([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['zip'] ?? null,
            $address['country'] ?? null,
        ], fn($value) => !empty($value));
        return empty($parts) ? null : implode(', ', $parts);
    }
}

if (!function_exists('get_items_for_dropdown')) {
    function get_items_for_dropdown($conn): array {
        $result = $conn->query("SELECT id, name, sku, type, base_price, tax_percent FROM items_master WHERE status = 'Active' ORDER BY name ASC");
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        return $items;
    }
}

if (!function_exists('upload_quotation_brochure')) {
    function upload_quotation_brochure($conn, int $quotation_id, array $file, int $user_id): array {
        $upload_dir = __DIR__ . '/../../uploads/quotations/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Validate file
        $allowed_types = ['application/pdf'];
        if (!in_array($file['type'], $allowed_types)) {
            return ['success' => false, 'message' => 'Only PDF files are allowed'];
        }
        
        if ($file['size'] > 10 * 1024 * 1024) { // 10MB
            return ['success' => false, 'message' => 'File size must be less than 10MB'];
        }
        
        // Get quotation number for filename
        $quotation = get_quotation_by_id($conn, $quotation_id);
        if (!$quotation) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }
        
        $filename = $quotation['quotation_no'] . '_brochure_' . time() . '.pdf';
        $filepath = $upload_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to upload file'];
        }
        
        // Update quotation with file path
        $relative_path = 'uploads/quotations/' . $filename;
        $stmt = $conn->prepare("UPDATE quotations SET brochure_pdf = ? WHERE id = ?");
        $stmt->bind_param('si', $relative_path, $quotation_id);
        $stmt->execute();
        $stmt->close();
        
        log_quotation_activity($conn, $quotation_id, $user_id, 'Update', 'Brochure uploaded');
        
        return ['success' => true, 'message' => 'Brochure uploaded successfully', 'filepath' => $relative_path];
    }
}
