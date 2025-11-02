<?php
/**
 * Payments Module Helper Functions
 * All business logic for payment management, allocations, and reporting
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

// ============================================================================
// TABLE EXISTENCE & PREREQUISITES
// ============================================================================

if (!function_exists('payments_tables_exist')) {
    function payments_tables_exist($conn): bool {
        $required_tables = ['payments', 'payment_invoice_map', 'payment_activity_log'];
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
// PAYMENT NUMBER GENERATION
// ============================================================================

if (!function_exists('generate_payment_no')) {
    function generate_payment_no($conn): string {
        $year = date('Y');
        $prefix = "PAY-{$year}-";
        
        // Get the last payment number for this year
        $stmt = $conn->prepare("SELECT payment_no FROM payments WHERE payment_no LIKE ? ORDER BY id DESC LIMIT 1");
        $pattern = $prefix . '%';
        $stmt->bind_param('s', $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Extract number and increment
            $last_no = (int)substr($row['payment_no'], strlen($prefix));
            $new_no = $last_no + 1;
        } else {
            $new_no = 1;
        }
        
        $stmt->close();
        return $prefix . str_pad($new_no, 4, '0', STR_PAD_LEFT);
    }
}

// ============================================================================
// GET PAYMENTS - LIST WITH FILTERS
// ============================================================================

if (!function_exists('get_all_payments')) {
    function get_all_payments($conn, $filters = []): array {
        $where = ['1=1'];
        $params = [];
        $types = '';
        
        // Search filter
        if (!empty($filters['search'])) {
            $where[] = "(p.payment_no LIKE ? OR c.name LIKE ? OR p.reference_no LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= 'sss';
        }
        
        // Client filter
        if (!empty($filters['client_id'])) {
            $where[] = "p.client_id = ?";
            $params[] = (int)$filters['client_id'];
            $types .= 'i';
        }
        
        // Project filter
        if (!empty($filters['project_id'])) {
            $where[] = "p.project_id = ?";
            $params[] = (int)$filters['project_id'];
            $types .= 'i';
        }
        
        // Payment mode filter
        if (!empty($filters['payment_mode'])) {
            $where[] = "p.payment_mode = ?";
            $params[] = $filters['payment_mode'];
            $types .= 's';
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $where[] = "p.payment_date >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "p.payment_date <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT p.*, 
                       c.name as client_name,
                       c.email as client_email,
                       c.phone as client_phone,
                       COALESCE(SUM(pim.allocated_amount), 0) as total_allocated,
                       (p.amount_received - COALESCE(SUM(pim.allocated_amount), 0)) as unallocated_amount,
                       COUNT(DISTINCT pim.invoice_id) as invoice_count
                FROM payments p
                LEFT JOIN clients c ON p.client_id = c.id
                LEFT JOIN payment_invoice_map pim ON p.id = pim.payment_id
                WHERE $whereClause
                GROUP BY p.id
                ORDER BY p.payment_date DESC, p.created_at DESC";
        
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
        
        $payments = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            $result->free();
        }
        
        if (isset($stmt)) {
            $stmt->close();
        }
        
        return $payments;
    }
}

// ============================================================================
// GET SINGLE PAYMENT BY ID
// ============================================================================

if (!function_exists('get_payment_by_id')) {
    function get_payment_by_id($conn, int $payment_id): ?array {
        $stmt = $conn->prepare("
            SELECT p.*, 
                   c.name as client_name,
                   c.email as client_email,
                   c.phone as client_phone,
                   u.username as created_by_name,
                   COALESCE(SUM(pim.allocated_amount), 0) as total_allocated,
                   (p.amount_received - COALESCE(SUM(pim.allocated_amount), 0)) as unallocated_amount
            FROM payments p
            LEFT JOIN clients c ON p.client_id = c.id
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN payment_invoice_map pim ON p.id = pim.payment_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        $result->free();
        $stmt->close();
        
        return $payment;
    }
}

// ============================================================================
// GET ALLOCATED INVOICES FOR A PAYMENT
// ============================================================================

if (!function_exists('get_payment_allocations')) {
    function get_payment_allocations($conn, int $payment_id): array {
        $stmt = $conn->prepare("
            SELECT pim.*, 
                   i.invoice_no,
                   i.issue_date,
                   i.total_amount,
                   i.amount_paid,
                   i.status,
                   (i.total_amount - i.amount_paid) as balance
            FROM payment_invoice_map pim
            INNER JOIN invoices i ON pim.invoice_id = i.id
            WHERE pim.payment_id = ?
            ORDER BY pim.created_at ASC
        ");
        
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $allocations = [];
        while ($row = $result->fetch_assoc()) {
            $allocations[] = $row;
        }
        
        $result->free();
        $stmt->close();
        
        return $allocations;
    }
}

// ============================================================================
// CREATE PAYMENT
// ============================================================================

if (!function_exists('create_payment')) {
    function create_payment($conn, array $data, int $user_id): array {
        // Validate required fields
        if (empty($data['client_id']) || empty($data['payment_date']) || empty($data['amount_received'])) {
            return ['success' => false, 'message' => 'Client, date, and amount are required.'];
        }
        
        if ($data['amount_received'] <= 0) {
            return ['success' => false, 'message' => 'Amount must be greater than zero.'];
        }
        
        // Generate payment number
        $payment_no = generate_payment_no($conn);
        
        // Extract data with defaults
        $client_id = (int)$data['client_id'];
        $project_id = !empty($data['project_id']) ? (int)$data['project_id'] : null;
        $payment_date = $data['payment_date'];
        $payment_mode = $data['payment_mode'] ?? 'Cash';
        $reference_no = $data['reference_no'] ?? null;
        $amount_received = (float)$data['amount_received'];
        $remarks = $data['remarks'] ?? null;
        $attachment = $data['attachment'] ?? null;
        
        // Validate date not in future
        if (strtotime($payment_date) > time()) {
            return ['success' => false, 'message' => 'Payment date cannot be in the future.'];
        }
        
        // Insert payment
        $stmt = $conn->prepare("
            INSERT INTO payments (
                payment_no, client_id, project_id, payment_date, payment_mode, 
                reference_no, amount_received, remarks, attachment, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            'siisssdssi',
            $payment_no, $client_id, $project_id, $payment_date, $payment_mode,
            $reference_no, $amount_received, $remarks, $attachment, $user_id
        );
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to create payment: ' . $error];
        }
        
        $payment_id = $stmt->insert_id;
        $stmt->close();
        
        // Log activity
        log_payment_activity($conn, $payment_id, $user_id, 'Create', "Payment $payment_no created");
        
        return ['success' => true, 'payment_id' => $payment_id, 'payment_no' => $payment_no];
    }
}

// ============================================================================
// ALLOCATE PAYMENT TO INVOICES
// ============================================================================

if (!function_exists('allocate_payment_to_invoices')) {
    function allocate_payment_to_invoices($conn, int $payment_id, array $allocations, int $user_id): array {
        // Get payment details
        $payment = get_payment_by_id($conn, $payment_id);
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found.'];
        }
        
        // Calculate total allocation
        $total_allocation = 0;
        foreach ($allocations as $allocation) {
            $total_allocation += (float)$allocation['amount'];
        }
        
        // Check if total allocation exceeds available amount
        $available_amount = (float)$payment['amount_received'] - (float)$payment['total_allocated'];
        if ($total_allocation > $available_amount) {
            return ['success' => false, 'message' => 'Total allocation exceeds available payment amount.'];
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            foreach ($allocations as $allocation) {
                $invoice_id = (int)$allocation['invoice_id'];
                $allocated_amount = (float)$allocation['amount'];
                
                if ($allocated_amount <= 0) {
                    continue;
                }
                
                // Check if invoice exists and get current balance
                $inv_stmt = $conn->prepare("SELECT id, invoice_no, total_amount, amount_paid FROM invoices WHERE id = ?");
                $inv_stmt->bind_param('i', $invoice_id);
                $inv_stmt->execute();
                $inv_result = $inv_stmt->get_result();
                $invoice = $inv_result->fetch_assoc();
                $inv_stmt->close();
                
                if (!$invoice) {
                    throw new Exception("Invoice ID $invoice_id not found.");
                }
                
                // Check if allocation already exists
                $check_stmt = $conn->prepare("SELECT id FROM payment_invoice_map WHERE payment_id = ? AND invoice_id = ?");
                $check_stmt->bind_param('ii', $payment_id, $invoice_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $exists = $check_result->num_rows > 0;
                $check_stmt->close();
                
                if ($exists) {
                    // Update existing allocation
                    $upd_stmt = $conn->prepare("UPDATE payment_invoice_map SET allocated_amount = allocated_amount + ? WHERE payment_id = ? AND invoice_id = ?");
                    $upd_stmt->bind_param('dii', $allocated_amount, $payment_id, $invoice_id);
                    $upd_stmt->execute();
                    $upd_stmt->close();
                } else {
                    // Insert new allocation
                    $ins_stmt = $conn->prepare("INSERT INTO payment_invoice_map (payment_id, invoice_id, allocated_amount) VALUES (?, ?, ?)");
                    $ins_stmt->bind_param('iid', $payment_id, $invoice_id, $allocated_amount);
                    $ins_stmt->execute();
                    $ins_stmt->close();
                }
                
                // Update invoice amount_paid
                $new_amount_paid = (float)$invoice['amount_paid'] + $allocated_amount;
                $new_balance = (float)$invoice['total_amount'] - $new_amount_paid;
                
                // Determine new status
                if ($new_balance <= 0.01) {
                    $new_status = 'Paid';
                } elseif ($new_amount_paid > 0) {
                    $new_status = 'Partially Paid';
                } else {
                    $new_status = $invoice['status'];
                }
                
                // Update invoice
                $upd_inv_stmt = $conn->prepare("UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?");
                $upd_inv_stmt->bind_param('dsi', $new_amount_paid, $new_status, $invoice_id);
                $upd_inv_stmt->execute();
                $upd_inv_stmt->close();
                
                // Log invoice activity
                require_once __DIR__ . '/../invoices/helpers.php';
                log_invoice_activity($conn, $invoice_id, $user_id, 'PaymentLinked', "Payment {$payment['payment_no']} applied: ₹" . number_format($allocated_amount, 2));
                
                // Log payment activity
                log_payment_activity($conn, $payment_id, $user_id, 'AttachInvoice', "Allocated ₹" . number_format($allocated_amount, 2) . " to Invoice {$invoice['invoice_no']}");
            }
            
            $conn->commit();
            return ['success' => true, 'message' => 'Payment allocated successfully.'];
            
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Allocation failed: ' . $e->getMessage()];
        }
    }
}

// ============================================================================
// UPDATE PAYMENT
// ============================================================================

if (!function_exists('update_payment')) {
    function update_payment($conn, int $payment_id, array $data, int $user_id): array {
        // Check if payment exists
        $payment = get_payment_by_id($conn, $payment_id);
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found.'];
        }
        
        // Extract updatable fields
        $fields = [];
        $params = [];
        $types = '';
        
        $allowed_fields = [
            'payment_date' => 's', 'payment_mode' => 's', 'reference_no' => 's',
            'remarks' => 's', 'attachment' => 's'
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
        
        $params[] = $payment_id;
        $types .= 'i';
        
        $sql = "UPDATE payments SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to update payment: ' . $error];
        }
        
        $stmt->close();
        
        // Log activity
        log_payment_activity($conn, $payment_id, $user_id, 'Update', "Payment details updated");
        
        return ['success' => true, 'message' => 'Payment updated successfully.'];
    }
}

// ============================================================================
// DELETE PAYMENT
// ============================================================================

if (!function_exists('delete_payment')) {
    function delete_payment($conn, int $payment_id, int $user_id): array {
        // Check if payment exists
        $payment = get_payment_by_id($conn, $payment_id);
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found.'];
        }
        
        // Check if payment has allocations
        if ((float)$payment['total_allocated'] > 0) {
            return ['success' => false, 'message' => 'Cannot delete payment with allocated invoices. Please detach all invoices first.'];
        }
        
        // Delete payment
        $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->bind_param('i', $payment_id);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to delete payment: ' . $error];
        }
        
        $stmt->close();
        
        return ['success' => true, 'message' => 'Payment deleted successfully.'];
    }
}

// ============================================================================
// ACTIVITY LOG
// ============================================================================

if (!function_exists('log_payment_activity')) {
    function log_payment_activity($conn, int $payment_id, int $user_id, string $action, string $description = null): bool {
        $stmt = $conn->prepare("INSERT INTO payment_activity_log (payment_id, user_id, action, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $payment_id, $user_id, $action, $description);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

if (!function_exists('get_payment_activity_log')) {
    function get_payment_activity_log($conn, int $payment_id): array {
        $stmt = $conn->prepare("
            SELECT pal.*, u.username as user_name
            FROM payment_activity_log pal
            LEFT JOIN users u ON pal.user_id = u.id
            WHERE pal.payment_id = ?
            ORDER BY pal.created_at DESC
        ");
        
        $stmt->bind_param('i', $payment_id);
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

if (!function_exists('get_payment_statistics')) {
    function get_payment_statistics($conn): array {
        $stats = [
            'total_payments' => 0,
            'total_received' => 0,
            'total_allocated' => 0,
            'unallocated_amount' => 0,
            'this_month_received' => 0,
            'pending_receivables' => 0
        ];
        
        // Get payment totals
        $result = $conn->query("
            SELECT 
                COUNT(*) as total_payments,
                SUM(amount_received) as total_received
            FROM payments
        ");
        
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_payments'] = (int)$row['total_payments'];
            $stats['total_received'] = (float)($row['total_received'] ?? 0);
            $result->free();
        }
        
        // Get allocated total
        $result = $conn->query("SELECT SUM(allocated_amount) as total_allocated FROM payment_invoice_map");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_allocated'] = (float)($row['total_allocated'] ?? 0);
            $result->free();
        }
        
        $stats['unallocated_amount'] = $stats['total_received'] - $stats['total_allocated'];
        
        // This month received
        $result = $conn->query("
            SELECT SUM(amount_received) as this_month_received 
            FROM payments 
            WHERE MONTH(payment_date) = MONTH(CURDATE()) 
            AND YEAR(payment_date) = YEAR(CURDATE())
        ");
        
        if ($result && $row = $result->fetch_assoc()) {
            $stats['this_month_received'] = (float)($row['this_month_received'] ?? 0);
            $result->free();
        }
        
        // Pending receivables
        $result = $conn->query("
            SELECT SUM(total_amount - amount_paid) as pending_receivables 
            FROM invoices 
            WHERE status NOT IN ('Paid', 'Cancelled')
        ");
        
        if ($result && $row = $result->fetch_assoc()) {
            $stats['pending_receivables'] = (float)($row['pending_receivables'] ?? 0);
            $result->free();
        }
        
        return $stats;
    }
}

// ============================================================================
// HELPER: GET PENDING INVOICES FOR CLIENT
// ============================================================================

if (!function_exists('get_pending_invoices_for_client')) {
    function get_pending_invoices_for_client($conn, int $client_id): array {
        $stmt = $conn->prepare("
            SELECT id, invoice_no, issue_date, total_amount, amount_paid, 
                   (total_amount - amount_paid) as balance, status
            FROM invoices
            WHERE client_id = ? 
            AND status NOT IN ('Paid', 'Cancelled')
            ORDER BY issue_date ASC
        ");
        
        $stmt->bind_param('i', $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $invoices = [];
        while ($row = $result->fetch_assoc()) {
            $invoices[] = $row;
        }
        
        $result->free();
        $stmt->close();
        
        return $invoices;
    }
}

// ============================================================================
// HELPER: GET CLIENTS WITH ACTIVE INVOICES
// ============================================================================

if (!function_exists('get_active_clients')) {
    function get_active_clients($conn): array {
        $result = $conn->query("
            SELECT id, name, email, phone 
            FROM clients 
            WHERE status = 'Active' 
            ORDER BY name ASC
        ");
        
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
