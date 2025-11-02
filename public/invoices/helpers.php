<?php
/**
 * Invoices Module Helper Functions
 * All business logic for invoice management, calculations, and inventory integration
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../catalog/helpers.php';

// ============================================================================
// TABLE EXISTENCE & PREREQUISITES
// ============================================================================

if (!function_exists('invoices_tables_exist')) {
    function invoices_tables_exist($conn): bool {
        $required_tables = ['invoices', 'invoice_items', 'invoice_activity_log'];
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

// ============================================================================
// INVOICE NUMBER GENERATION
// ============================================================================

if (!function_exists('generate_invoice_no')) {
    function generate_invoice_no($conn): string {
        $year = date('Y');
        $prefix = "INV-{$year}-";
        
        // Get the last invoice number for this year
        $stmt = $conn->prepare("SELECT invoice_no FROM invoices WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1");
        $pattern = $prefix . '%';
        $stmt->bind_param('s', $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Extract number and increment
            $last_no = (int)substr($row['invoice_no'], strlen($prefix));
            $new_no = $last_no + 1;
        } else {
            $new_no = 1;
        }
        
        $stmt->close();
        return $prefix . str_pad($new_no, 4, '0', STR_PAD_LEFT);
    }
}

// ============================================================================
// GET INVOICES - LIST WITH FILTERS
// ============================================================================

if (!function_exists('get_all_invoices')) {
    function get_all_invoices($conn, $filters = []): array {
        $where = ['1=1'];
        $params = [];
        $types = '';
        
        // Search filter
        if (!empty($filters['search'])) {
            $where[] = "(i.invoice_no LIKE ? OR c.name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $types .= 'ss';
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $where[] = "i.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Client filter
        if (!empty($filters['client_id'])) {
            $where[] = "i.client_id = ?";
            $params[] = (int)$filters['client_id'];
            $types .= 'i';
        }
        
        // Project filter
        if (!empty($filters['project_id'])) {
            $where[] = "i.project_id = ?";
            $params[] = (int)$filters['project_id'];
            $types .= 'i';
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $where[] = "i.issue_date >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "i.issue_date <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Overdue filter
        if (!empty($filters['overdue_only'])) {
            $where[] = "i.due_date < CURDATE() AND i.status NOT IN ('Paid', 'Cancelled')";
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT i.*, 
                       c.name as client_name,
                       c.email as client_email,
                       c.phone as client_phone,
                       COUNT(DISTINCT ii.id) as item_count,
                       (i.total_amount - i.amount_paid) as balance,
                       CASE 
                           WHEN i.due_date < CURDATE() AND i.status NOT IN ('Paid', 'Cancelled') THEN 1
                           ELSE 0
                       END as is_overdue,
                       CASE
                           WHEN i.due_date IS NULL THEN NULL
                           WHEN i.due_date < CURDATE() THEN DATEDIFF(CURDATE(), i.due_date)
                           ELSE 0
                       END as days_overdue
                FROM invoices i
                LEFT JOIN clients c ON i.client_id = c.id
                LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
                WHERE $whereClause
                GROUP BY i.id
                ORDER BY i.created_at DESC";
        
        if (empty($params)) {
            $result = $conn->query($sql);
        } else {
            $stmt = $conn->prepare($sql);
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $invoices = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $invoices[] = $row;
            }
            $result->free();
        }
        
        if (isset($stmt)) {
            $stmt->close();
        }
        
        return $invoices;
    }
}

// ============================================================================
// GET SINGLE INVOICE BY ID
// ============================================================================

if (!function_exists('get_invoice_by_id')) {
    function get_invoice_by_id($conn, int $invoice_id): ?array {
        $stmt = $conn->prepare("
            SELECT i.*, 
                   c.name as client_name,
                   c.email as client_email,
                   c.phone as client_phone,
                   u.username as created_by_name,
                   (i.total_amount - i.amount_paid) as balance,
                   CASE 
                       WHEN i.due_date < CURDATE() AND i.status NOT IN ('Paid', 'Cancelled') THEN 1
                       ELSE 0
                   END as is_overdue,
                   CASE
                       WHEN i.due_date IS NULL THEN NULL
                       WHEN i.due_date < CURDATE() THEN DATEDIFF(CURDATE(), i.due_date)
                       ELSE 0
                   END as days_overdue
            FROM invoices i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN users u ON i.created_by = u.id
            WHERE i.id = ?
        ");
        
        $stmt->bind_param('i', $invoice_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $invoice = $result->fetch_assoc();
        $result->free();
        $stmt->close();
        
        // Get client address if available
        if ($invoice && !empty($invoice['client_id'])) {
            $invoice['client_address'] = get_client_primary_address_text($conn, (int)$invoice['client_id']);
        }
        
        return $invoice;
    }
}

// ============================================================================
// GET INVOICE ITEMS
// ============================================================================

if (!function_exists('get_invoice_items')) {
    function get_invoice_items($conn, int $invoice_id): array {
        $stmt = $conn->prepare("
         SELECT ii.*, 
             im.name AS item_name,
             im.sku AS item_code,
                   im.type AS item_type,
                   NULL AS hsn_sac,
                   NULL AS default_unit
            FROM invoice_items ii
            LEFT JOIN items_master im ON ii.item_id = im.id
            WHERE ii.invoice_id = ?
            ORDER BY ii.id ASC
        ");
        
        $stmt->bind_param('i', $invoice_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        $result->free();
        $stmt->close();
        
        return $items;
    }
}

// ============================================================================
// CREATE INVOICE
// ============================================================================

if (!function_exists('create_invoice')) {
    function create_invoice($conn, array $data, int $user_id): array {
        // Validate required fields
        if (empty($data['client_id']) || empty($data['issue_date'])) {
            return ['success' => false, 'message' => 'Client and Issue Date are required.'];
        }
        
        // Generate invoice number
        $invoice_no = generate_invoice_no($conn);
        
        // Extract data with defaults
        $client_id = (int)$data['client_id'];
        $quotation_id = !empty($data['quotation_id']) ? (int)$data['quotation_id'] : null;
        $project_id = !empty($data['project_id']) ? (int)$data['project_id'] : null;
        $issue_date = $data['issue_date'];
        $due_date = $data['due_date'] ?? null;
        $payment_terms = $data['payment_terms'] ?? null;
        $currency = $data['currency'] ?? 'INR';
        $status = $data['status'] ?? 'Draft';
        $notes = $data['notes'] ?? null;
        $terms = $data['terms'] ?? null;
        $attachment = $data['attachment'] ?? null;
        
        // Calculations
        $subtotal = (float)($data['subtotal'] ?? 0);
        $tax_amount = (float)($data['tax_amount'] ?? 0);
        $discount_amount = (float)($data['discount_amount'] ?? 0);
        $round_off = (float)($data['round_off'] ?? 0);
        $total_amount = (float)($data['total_amount'] ?? 0);
        
        // Insert invoice
        $stmt = $conn->prepare("
            INSERT INTO invoices (
                invoice_no, quotation_id, client_id, project_id, issue_date, due_date, 
                payment_terms, currency, subtotal, tax_amount, discount_amount, round_off, 
                total_amount, status, notes, terms, attachment, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            'siiissssddddssssis',
            $invoice_no, $quotation_id, $client_id, $project_id, $issue_date, $due_date,
            $payment_terms, $currency, $subtotal, $tax_amount, $discount_amount, $round_off,
            $total_amount, $status, $notes, $terms, $attachment, $user_id
        );
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to create invoice: ' . $error];
        }
        
        $invoice_id = $stmt->insert_id;
        $stmt->close();
        
        // Log activity
        log_invoice_activity($conn, $invoice_id, $user_id, 'Create', "Invoice $invoice_no created");
        
        return ['success' => true, 'invoice_id' => $invoice_id, 'invoice_no' => $invoice_no];
    }
}

// ============================================================================
// UPDATE INVOICE
// ============================================================================

if (!function_exists('update_invoice')) {
    function update_invoice($conn, int $invoice_id, array $data, int $user_id): array {
        // Check if invoice exists and is editable
        $invoice = get_invoice_by_id($conn, $invoice_id);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }
        
        if (!in_array($invoice['status'], ['Draft'])) {
            return ['success' => false, 'message' => 'Only Draft invoices can be edited.'];
        }
        
        // Extract updatable fields
        $fields = [];
        $params = [];
        $types = '';
        
        $allowed_fields = [
            'client_id' => 'i', 'project_id' => 'i', 'issue_date' => 's', 'due_date' => 's',
            'payment_terms' => 's', 'currency' => 's', 'subtotal' => 'd', 'tax_amount' => 'd',
            'discount_amount' => 'd', 'round_off' => 'd', 'total_amount' => 'd',
            'notes' => 's', 'terms' => 's', 'attachment' => 's'
        ];
        
        foreach ($allowed_fields as $field => $type) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
                $types .= $type;
            }
        }
        
        if (empty($fields)) {
            return ['success' => false, 'message' => 'No fields to update.'];
        }
        
        $params[] = $invoice_id;
        $types .= 'i';
        
        $sql = "UPDATE invoices SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to update invoice: ' . $error];
        }
        
        $stmt->close();
        
        // Log activity
        log_invoice_activity($conn, $invoice_id, $user_id, 'Update', "Invoice updated");
        
        return ['success' => true, 'message' => 'Invoice updated successfully.'];
    }
}

// ============================================================================
// ADD INVOICE ITEM
// ============================================================================

if (!function_exists('add_invoice_item')) {
    function add_invoice_item($conn, int $invoice_id, array $item): array {
        // Validate required fields
        if (empty($item['item_id']) || empty($item['quantity']) || !isset($item['unit_price'])) {
            return ['success' => false, 'message' => 'Item, quantity, and unit price are required.'];
        }
        
        $item_id = (int)$item['item_id'];
        $description = $item['description'] ?? null;
        $quantity = (float)$item['quantity'];
        $unit = $item['unit'] ?? null;
        $unit_price = (float)$item['unit_price'];
        $discount = (float)($item['discount'] ?? 0);
        $discount_type = $item['discount_type'] ?? 'Amount';
        $tax_percent = (float)($item['tax_percent'] ?? 0);
        $line_total = (float)($item['line_total'] ?? 0);
        
        $stmt = $conn->prepare("
            INSERT INTO invoice_items (
                invoice_id, item_id, description, quantity, unit, unit_price,
                discount, discount_type, tax_percent, line_total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            'iisdsddsdd',
            $invoice_id, $item_id, $description, $quantity, $unit, $unit_price,
            $discount, $discount_type, $tax_percent, $line_total
        );
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to add item: ' . $error];
        }
        
        $item_id_inserted = $stmt->insert_id;
        $stmt->close();
        
        return ['success' => true, 'item_id' => $item_id_inserted];
    }
}

// ============================================================================
// DELETE INVOICE ITEMS (for update operations)
// ============================================================================

if (!function_exists('delete_invoice_items')) {
    function delete_invoice_items($conn, int $invoice_id): bool {
        $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->bind_param('i', $invoice_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

// ============================================================================
// ISSUE INVOICE (Mark as Issued + Deduct Inventory)
// ============================================================================

if (!function_exists('issue_invoice')) {
    function issue_invoice($conn, int $invoice_id, int $user_id): array {
        // Get invoice
        $invoice = get_invoice_by_id($conn, $invoice_id);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }
        
        if ($invoice['status'] !== 'Draft') {
            return ['success' => false, 'message' => 'Only Draft invoices can be issued.'];
        }
        
        // Get items
        $items = get_invoice_items($conn, $invoice_id);
        if (empty($items)) {
            return ['success' => false, 'message' => 'Cannot issue invoice without items.'];
        }
        
        // Check stock availability for product items
        foreach ($items as $item) {
            if (strtolower($item['item_type']) === 'product') {
                $available = get_item_available_stock($conn, (int)$item['item_id']);
                if ($available < $item['quantity']) {
                    return [
                        'success' => false,
                        'message' => "Insufficient stock for item: {$item['item_name']}. Available: $available, Required: {$item['quantity']}"
                    ];
                }
            }
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update invoice status
            $stmt = $conn->prepare("UPDATE invoices SET status = 'Issued' WHERE id = ?");
            $stmt->bind_param('i', $invoice_id);
            $stmt->execute();
            $stmt->close();
            
            // Deduct inventory for product items
            foreach ($items as $item) {
                if (strtolower($item['item_type']) === 'product') {
                    $deduct_result = deduct_inventory_for_invoice($conn, $invoice_id, (int)$item['item_id'], (float)$item['quantity'], $user_id);
                    if (!$deduct_result['success']) {
                        throw new Exception($deduct_result['message']);
                    }
                }
            }
            
            // Log activity
            log_invoice_activity($conn, $invoice_id, $user_id, 'Issue', "Invoice issued - stock deducted");
            
            $conn->commit();
            return ['success' => true, 'message' => 'Invoice issued successfully.'];
            
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Failed to issue invoice: ' . $e->getMessage()];
        }
    }
}

// ============================================================================
// CANCEL INVOICE
// ============================================================================

if (!function_exists('cancel_invoice')) {
    function cancel_invoice($conn, int $invoice_id, int $user_id, bool $restore_inventory = true): array {
        // Get invoice
        $invoice = get_invoice_by_id($conn, $invoice_id);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }
        
        if ($invoice['status'] === 'Cancelled') {
            return ['success' => false, 'message' => 'Invoice is already cancelled.'];
        }
        
        if ($invoice['amount_paid'] > 0) {
            return ['success' => false, 'message' => 'Cannot cancel invoice with payments. Please refund payments first.'];
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update invoice status
            $stmt = $conn->prepare("UPDATE invoices SET status = 'Cancelled' WHERE id = ?");
            $stmt->bind_param('i', $invoice_id);
            $stmt->execute();
            $stmt->close();
            
            // Restore inventory if requested and invoice was issued
            if ($restore_inventory && $invoice['status'] === 'Issued') {
                $items = get_invoice_items($conn, $invoice_id);
                foreach ($items as $item) {
                    if (strtolower($item['item_type']) === 'product') {
                        $restore_result = restore_inventory_for_invoice($conn, $invoice_id, (int)$item['item_id'], (float)$item['quantity'], $user_id);
                        if (!$restore_result['success']) {
                            throw new Exception($restore_result['message']);
                        }
                    }
                }
            }
            
            // Log activity
            $log_msg = $restore_inventory ? "Invoice cancelled - inventory restored" : "Invoice cancelled";
            log_invoice_activity($conn, $invoice_id, $user_id, 'Cancel', $log_msg);
            
            $conn->commit();
            return ['success' => true, 'message' => 'Invoice cancelled successfully.'];
            
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Failed to cancel invoice: ' . $e->getMessage()];
        }
    }
}

// ============================================================================
// INVENTORY INTEGRATION
// ============================================================================

if (!function_exists('get_item_available_stock')) {
    function get_item_available_stock($conn, int $item_id): float {
        if (!function_exists('catalog_tables_exist') || !catalog_tables_exist($conn)) {
            return 999999; // Catalog module not installed; skip stock enforcement
        }

        $item = get_item_by_id($conn, $item_id);
        if (!$item) {
            return 0;
        }

        if (strtolower($item['type'] ?? '') !== 'product') {
            return 999999; // Services do not enforce stock
        }

        return (float)($item['current_stock'] ?? 0);
    }
}

if (!function_exists('deduct_inventory_for_invoice')) {
    function deduct_inventory_for_invoice($conn, int $invoice_id, int $item_id, float $quantity, int $user_id): array {
        if (!function_exists('catalog_tables_exist') || !catalog_tables_exist($conn)) {
            return ['success' => true, 'message' => 'Catalog module not installed'];
        }

        $reason = 'Stock deducted for invoice #' . $invoice_id;
        return adjust_item_stock($conn, $item_id, 'InvoiceDeduct', $quantity, $reason, 'Invoice', $invoice_id, $user_id);
    }
}

if (!function_exists('restore_inventory_for_invoice')) {
    function restore_inventory_for_invoice($conn, int $invoice_id, int $item_id, float $quantity, int $user_id): array {
        if (!function_exists('catalog_tables_exist') || !catalog_tables_exist($conn)) {
            return ['success' => true, 'message' => 'Catalog module not installed'];
        }

        $reason = 'Invoice cancellation for #' . $invoice_id;
        return adjust_item_stock($conn, $item_id, 'Add', $quantity, $reason, 'Invoice', $invoice_id, $user_id);
    }
}

// ============================================================================
// ACTIVITY LOGGING
// ============================================================================

if (!function_exists('log_invoice_activity')) {
    function log_invoice_activity($conn, int $invoice_id, int $user_id, string $action, string $description = null): bool {
        $stmt = $conn->prepare("INSERT INTO invoice_activity_log (invoice_id, user_id, action, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $invoice_id, $user_id, $action, $description);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

if (!function_exists('get_invoice_activity_log')) {
    function get_invoice_activity_log($conn, int $invoice_id): array {
        $stmt = $conn->prepare("
            SELECT ial.*, u.username as user_name
            FROM invoice_activity_log ial
            LEFT JOIN users u ON ial.user_id = u.id
            WHERE ial.invoice_id = ?
            ORDER BY ial.created_at DESC
        ");
        
        $stmt->bind_param('i', $invoice_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        $result->free();
        $stmt->close();
        
        return $logs;
    }
}

// ============================================================================
// STATISTICS & DASHBOARD
// ============================================================================

if (!function_exists('get_invoice_statistics')) {
    function get_invoice_statistics($conn): array {
        $stats = [
            'total_invoices' => 0,
            'draft_invoices' => 0,
            'issued_invoices' => 0,
            'overdue_invoices' => 0,
            'paid_invoices' => 0,
            'total_revenue' => 0,
            'outstanding_amount' => 0,
            'collected_amount' => 0
        ];
        
        // Get counts and totals
        $result = $conn->query("
            SELECT 
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft_invoices,
                SUM(CASE WHEN status = 'Issued' THEN 1 ELSE 0 END) as issued_invoices,
                SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_invoices,
                SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled') THEN 1 ELSE 0 END) as overdue_invoices,
                SUM(CASE WHEN status NOT IN ('Cancelled') THEN total_amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status NOT IN ('Cancelled') THEN amount_paid ELSE 0 END) as collected_amount,
                SUM(CASE WHEN status NOT IN ('Paid', 'Cancelled') THEN (total_amount - amount_paid) ELSE 0 END) as outstanding_amount
            FROM invoices
        ");
        
        if ($result && $row = $result->fetch_assoc()) {
            $stats = array_merge($stats, $row);
            $result->free();
        }
        
        return $stats;
    }
}

// ============================================================================
// HELPER: GET CLIENT PRIMARY ADDRESS
// ============================================================================

if (!function_exists('get_client_primary_address_text')) {
    function get_client_primary_address_text($conn, int $client_id): ?string {
        // Check if client_addresses table exists
        $result = $conn->query("SHOW TABLES LIKE 'client_addresses'");
        if (!$result || $result->num_rows === 0) {
            return null;
        }
        $result->free();
        
        $stmt = $conn->prepare("SELECT line1, line2, city, state, zip, country FROM client_addresses WHERE client_id = ? ORDER BY is_default DESC, id ASC LIMIT 1");
        $stmt->bind_param('i', $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $address = $result->fetch_assoc();
        $result->free();
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
        ]);
        
        return !empty($parts) ? implode(', ', $parts) : null;
    }
}

// ============================================================================
// HELPER: GET ACTIVE CLIENTS
// ============================================================================

if (!function_exists('get_active_clients')) {
    function get_active_clients($conn): array {
        $result = $conn->query("SELECT id, name, email FROM clients WHERE status = 'Active' ORDER BY name ASC");
        $clients = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $clients[] = $row;
            }
            $result->free();
        }
        return $clients;
    }
}

// ============================================================================
// HELPER: CALCULATE DUE DATE FROM PAYMENT TERMS
// ============================================================================

if (!function_exists('calculate_due_date')) {
    function calculate_due_date(string $issue_date, string $payment_terms): ?string {
        $terms_map = [
            'NET 7' => 7,
            'NET 15' => 15,
            'NET 30' => 30,
            'NET 45' => 45,
            'NET 60' => 60,
            'NET 90' => 90
        ];
        
        $days = $terms_map[$payment_terms] ?? 0;
        if ($days > 0) {
            return date('Y-m-d', strtotime($issue_date . ' + ' . $days . ' days'));
        }
        
        return null;
    }
}
