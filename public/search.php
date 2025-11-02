<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}

$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all, employees, attendance, reimbursements, leads, contacts, clients, projects, catalog, quotations, invoices, payments, payroll, documents, visitors, notebook, assets
$page_title = 'Search - ' . APP_NAME;
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

$conn = createConnection(true);

// Helper function to check if table exists
function tableExists($conn, $tableName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableName'");
    $exists = $result && mysqli_num_rows($result) > 0;
    if ($result) mysqli_free_result($result);
    return $exists;
}

// Initialize results array
$results = [
    'employees' => [],
    'attendance' => [],
    'reimbursements' => [],
    'leads' => [],
    'contacts' => [],
    'clients' => [],
    'projects' => [],
    'catalog' => [],
    'quotations' => [],
    'invoices' => [],
    'payments' => [],
    'payroll' => [],
    'documents' => [],
    'visitors' => [],
    'notebook' => [],
    'assets' => []
];

$totalResults = 0;
$searchTime = 0;

// Perform search if query is not empty
if ($q !== '' && strlen($q) >= 2 && $conn) {
    $startTime = microtime(true);
    $searchTerm = mysqli_real_escape_string($conn, $q);
    $like = '%' . $searchTerm . '%';
    
    // Search Employees (using actual column names from employees table)
    if (($filter === 'all' || $filter === 'employees') && tableExists($conn, 'employees')) {
        $sql = "SELECT id, employee_code, first_name, last_name, department, designation, 
                       official_email, mobile_number, alternate_mobile 
                FROM employees 
                WHERE (CONCAT(first_name, ' ', last_name) LIKE '$like' 
                   OR employee_code LIKE '$like' 
                   OR official_email LIKE '$like' 
                   OR personal_email LIKE '$like'
                   OR mobile_number LIKE '$like'
                   OR alternate_mobile LIKE '$like'
                   OR department LIKE '$like'
                   OR designation LIKE '$like')
                ORDER BY 
                    CASE 
                        WHEN employee_code = '$searchTerm' THEN 1
                        WHEN CONCAT(first_name, ' ', last_name) = '$searchTerm' THEN 2
                        WHEN employee_code LIKE '$searchTerm%' THEN 3
                        WHEN CONCAT(first_name, ' ', last_name) LIKE '$searchTerm%' THEN 4
                        ELSE 5
                    END
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['employees'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Attendance
    if (($filter === 'all' || $filter === 'attendance') && tableExists($conn, 'attendance')) {
          $sql = "SELECT a.id, a.attendance_date, a.check_in_time, a.check_out_time, a.status,
                              CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code
                     FROM attendance a
                     LEFT JOIN employees e ON a.employee_id = e.id
                     WHERE (CONCAT(e.first_name, ' ', e.last_name) LIKE '$like' 
                         OR e.employee_code LIKE '$like'
                         OR a.status LIKE '$like'
                         OR a.attendance_date LIKE '$like')
                     ORDER BY a.attendance_date DESC
                     LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['attendance'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Reimbursements
    if (($filter === 'all' || $filter === 'reimbursements') && tableExists($conn, 'reimbursements')) {
        $sql = "SELECT r.id, r.expense_date, r.amount, r.category, r.description, r.status,
                       CONCAT(e.first_name, ' ', e.last_name) as employee_name
                FROM reimbursements r
                LEFT JOIN employees e ON r.employee_id = e.id
                WHERE (r.description LIKE '$like' 
                   OR r.category LIKE '$like'
                   OR r.status LIKE '$like'
                   OR CONCAT(e.first_name, ' ', e.last_name) LIKE '$like')
                ORDER BY r.expense_date DESC
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['reimbursements'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search CRM Leads
    if (($filter === 'all' || $filter === 'leads') && tableExists($conn, 'crm_leads')) {
        $sql = "SELECT id, name, company_name, phone, email, status, source 
                FROM crm_leads 
                WHERE (name LIKE '$like' 
                   OR company_name LIKE '$like' 
                   OR phone LIKE '$like' 
                   OR email LIKE '$like'
                   OR source LIKE '$like')
                  AND deleted_at IS NULL
                ORDER BY 
                    CASE 
                        WHEN name = '$searchTerm' THEN 1
                        WHEN company_name = '$searchTerm' THEN 2
                        WHEN name LIKE '$searchTerm%' THEN 3
                        ELSE 4
                    END
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['leads'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Contacts
    if (($filter === 'all' || $filter === 'contacts') && tableExists($conn, 'contacts')) {
        $sql = "SELECT id, name, email, phone, designation
                FROM contacts 
                WHERE (name LIKE '$like' 
                   OR email LIKE '$like' 
                   OR phone LIKE '$like'
                   OR designation LIKE '$like')
                ORDER BY 
                    CASE 
                        WHEN name = '$searchTerm' THEN 1
                        WHEN name LIKE '$searchTerm%' THEN 2
                        ELSE 3
                    END
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['contacts'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Clients
    if (($filter === 'all' || $filter === 'clients') && tableExists($conn, 'clients')) {
        $sql = "SELECT id, name, email, phone, status
                FROM clients 
                WHERE (name LIKE '$like' 
                   OR email LIKE '$like'
                   OR phone LIKE '$like')
                ORDER BY 
                    CASE 
                        WHEN name = '$searchTerm' THEN 1
                        WHEN name LIKE '$searchTerm%' THEN 2
                        ELSE 3
                    END
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['clients'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Projects
    if (($filter === 'all' || $filter === 'projects') && tableExists($conn, 'projects')) {
        $sql = "SELECT p.id, p.title, p.project_code, p.status, p.start_date, p.end_date,
                       c.name as client_name
                FROM projects p
                LEFT JOIN clients c ON p.client_id = c.id
                WHERE (p.title LIKE '$like' 
                   OR p.project_code LIKE '$like'
                   OR p.description LIKE '$like'
                   OR c.name LIKE '$like')
                ORDER BY 
                    CASE 
                        WHEN p.project_code = '$searchTerm' THEN 1
                        WHEN p.title = '$searchTerm' THEN 2
                        WHEN p.title LIKE '$searchTerm%' THEN 3
                        ELSE 4
                    END
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['projects'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Catalog Items
    if (($filter === 'all' || $filter === 'catalog') && tableExists($conn, 'items_master')) {
        $sql = "SELECT id, sku, name, category, base_price, current_stock, status
                FROM items_master 
                WHERE (sku LIKE '$like' 
                   OR name LIKE '$like'
                   OR category LIKE '$like')
                ORDER BY 
                    CASE 
                        WHEN sku = '$searchTerm' THEN 1
                        WHEN name = '$searchTerm' THEN 2
                        WHEN sku LIKE '$searchTerm%' THEN 3
                        WHEN name LIKE '$searchTerm%' THEN 4
                        ELSE 5
                    END
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['catalog'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Quotations
    if (($filter === 'all' || $filter === 'quotations') && tableExists($conn, 'quotations')) {
          $sql = "SELECT q.id, q.quotation_no, q.title, q.quotation_date, q.total_amount, q.status,
                              c.name as client_name
                     FROM quotations q
                     LEFT JOIN clients c ON q.client_id = c.id
                     WHERE (q.quotation_no LIKE '$like' 
                         OR q.title LIKE '$like'
                         OR c.name LIKE '$like')
                     ORDER BY q.quotation_date DESC
                     LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['quotations'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Invoices
    if (($filter === 'all' || $filter === 'invoices') && tableExists($conn, 'invoices')) {
          $sql = "SELECT i.id, i.invoice_no, i.issue_date, i.total_amount, i.status, i.due_date,
                              c.name as client_name
                     FROM invoices i
                     LEFT JOIN clients c ON i.client_id = c.id
                     WHERE (i.invoice_no LIKE '$like' 
                         OR c.name LIKE '$like')
                     ORDER BY i.issue_date DESC
                     LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['invoices'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Payments
    if (($filter === 'all' || $filter === 'payments') && tableExists($conn, 'payments')) {
          $sql = "SELECT p.id, p.payment_date, p.amount_received, p.payment_mode, p.reference_no,
                              c.name as client_name
                     FROM payments p
                     LEFT JOIN clients c ON p.client_id = c.id
                     WHERE (p.reference_no LIKE '$like' 
                         OR c.name LIKE '$like')
                     ORDER BY p.payment_date DESC
                     LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['payments'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Payroll
    if (($filter === 'all' || $filter === 'payroll') && tableExists($conn, 'payroll_records') && tableExists($conn, 'payroll_master')) {
    $sql = "SELECT pr.id, pm.month, pr.base_salary, pr.allowances, pr.reimbursements, pr.deductions, pr.bonus, pr.penalties, pr.net_pay, pm.status,
                              CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code
                     FROM payroll_records pr
                     INNER JOIN payroll_master pm ON pr.payroll_id = pm.id
                     LEFT JOIN employees e ON pr.employee_id = e.id
                     WHERE (CONCAT(e.first_name, ' ', e.last_name) LIKE '$like' 
                         OR e.employee_code LIKE '$like'
                         OR pm.month LIKE '$like')
                     ORDER BY pm.month DESC
                     LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['payroll'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Documents
    if (($filter === 'all' || $filter === 'documents') && tableExists($conn, 'documents')) {
        $sql = "SELECT d.id, d.title, d.file_path, d.doc_type, d.tags, d.created_at,
                       CONCAT(e.first_name, ' ', e.last_name) as uploaded_by_name
                FROM documents d
                LEFT JOIN employees e ON d.uploaded_by = e.id
                WHERE (d.title LIKE '$like' OR d.doc_type LIKE '$like' OR d.tags LIKE '$like')
                  AND d.deleted_at IS NULL
                ORDER BY 
                    CASE 
                        WHEN d.title LIKE '$searchTerm%' THEN 1
                        ELSE 2
                    END,
                    d.created_at DESC
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['documents'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Visitors
    if (($filter === 'all' || $filter === 'visitors') && tableExists($conn, 'visitor_logs')) {
        $sql = "SELECT v.id, v.visitor_name, v.phone, v.purpose, v.check_in_time, v.check_out_time,
                       CONCAT(e.first_name, ' ', e.last_name) as host_name
                FROM visitor_logs v
                LEFT JOIN employees e ON v.employee_id = e.id
                WHERE (v.visitor_name LIKE '$like' OR v.phone LIKE '$like' OR v.purpose LIKE '$like')
                  AND v.deleted_at IS NULL
                ORDER BY v.check_in_time DESC
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['visitors'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Notebook
    if (($filter === 'all' || $filter === 'notebook') && tableExists($conn, 'notebook_notes')) {
        $sql = "SELECT n.id, n.title, n.content, n.tags, n.created_at,
                       CONCAT(e.first_name, ' ', e.last_name) as author_name
                FROM notebook_notes n
                LEFT JOIN employees e ON n.created_by = e.id
                     WHERE (n.title LIKE '$like' 
                         OR n.content LIKE '$like'
                         OR n.tags LIKE '$like')
                ORDER BY 
                    CASE 
                        WHEN n.title LIKE '$searchTerm%' THEN 1
                        ELSE 2
                    END,
                    n.created_at DESC
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['notebook'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search Assets
    if (($filter === 'all' || $filter === 'assets') && tableExists($conn, 'assets_master')) {
        $sql = "SELECT a.id, a.asset_code, a.name, a.category, a.status, a.purchase_date, a.purchase_cost
                FROM assets_master a
                WHERE (a.asset_code LIKE '$like' 
                   OR a.name LIKE '$like'
                   OR a.category LIKE '$like'
                   OR a.serial_no LIKE '$like')
                ORDER BY 
                    CASE 
                        WHEN a.asset_code = '$searchTerm' THEN 1
                        WHEN a.name = '$searchTerm' THEN 2
                        WHEN a.asset_code LIKE '$searchTerm%' THEN 3
                        ELSE 4
                    END
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['assets'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    $searchTime = round((microtime(true) - $startTime) * 1000, 2);
}

if ($conn) closeConnection($conn);
?>

<style>
:root {
    --brand-blue: #0b5ed7;
    --brand-blue-dark: #003581;
    --brand-blue-darker: #002861;
    --card-shadow: 0 8px 22px rgba(11,35,74,0.08);
    --muted-text: #6c757d;
}

.search-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header - matching dashboard style */
.page-header {
    margin-bottom: 24px;
}

.page-header h1 {
    color: #0b3a75;
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 6px 0;
}

.page-header p {
    color: var(--muted-text);
    font-size: 14px;
    margin: 0;
}

/* Search Form */
.search-form-wrapper {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--card-shadow);
}

.search-input-wrapper {
    position: relative;
    max-width: 100%;
}

.search-input {
    width: 100%;
    padding: 14px 120px 14px 18px;
    font-size: 15px;
    border: 2px solid #e6edf5;
    border-radius: 10px;
    background: #f7f9fc;
    transition: all 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: var(--brand-blue);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(11,94,215,0.1);
}

.search-btn {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    padding: 10px 24px;
    background: var(--brand-blue);
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
}

.search-btn:hover {
    background: var(--brand-blue-dark);
    transform: translateY(-50%) scale(1.02);
}

/* Filter Tabs */
.filter-tabs {
    display: flex;
    gap: 8px;
    margin: 20px 0 0 0;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 8px 16px;
    background: #f7f9fc;
    border: 1px solid #e6edf5;
    border-radius: 8px;
    color: #0d2d66;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s;
}

.filter-tab:hover {
    background: #edf2ff;
    border-color: var(--brand-blue);
    color: var(--brand-blue-dark);
}

.filter-tab.active {
    background: var(--brand-blue);
    color: #fff;
    border-color: var(--brand-blue);
}

/* Search Stats */
.search-stats {
    text-align: center;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 14px;
    color: var(--muted-text);
}

.search-stats strong {
    color: var(--brand-blue-dark);
    font-weight: 700;
}

/* Result Sections */
.result-section {
    margin-bottom: 24px;
}

.result-section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    background: #f8f9fa;
    border-radius: 10px 10px 0 0;
    border-bottom: 2px solid #e1e8ed;
}

.result-section-title {
    font-size: 17px;
    font-weight: 700;
    margin: 0;
    color: #0b3a75;
}

.result-count {
    background: var(--brand-blue);
    color: #fff;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

/* Result Items */
.result-items {
    background: #fff;
    border-radius: 0 0 10px 10px;
    overflow: hidden;
    box-shadow: var(--card-shadow);
}

.result-item {
    padding: 18px 20px;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.2s;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 16px;
    align-items: center;
}

.result-item:last-child {
    border-bottom: none;
}

.result-item:hover {
    background: #f8f9fa;
}

.result-item-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.result-item-title {
    font-weight: 600;
    color: #0b3a75;
    font-size: 15px;
}

.result-item-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 13px;
    color: var(--muted-text);
}

.result-item-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* View Button - matching site button style */
.result-item-actions {
    display: flex;
    gap: 8px;
}

.view-btn {
    padding: 10px 20px;
    background: var(--brand-blue);
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s;
    white-space: nowrap;
}

.view-btn:hover {
    background: var(--brand-blue-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(11,94,215,0.25);
}

/* Status Badges - matching dashboard style */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.status-new { background: #e3f2fd; color: #1976d2; }
.status-contacted { background: #f3e5f5; color: #7b1fa2; }
.status-qualified { background: #e8f5e9; color: #388e3c; }
.status-pending { background: #fff3e0; color: #f57c00; }
.status-completed { background: #e8f5e9; color: #2e7d32; }
.status-scheduled { background: #e3f2fd; color: #1565c0; }
.status-high { background: #ffebee; color: #c62828; }
.status-medium { background: #fff3e0; color: #ef6c00; }
.status-low { background: #e8f5e9; color: #2e7d32; }

/* Empty States */
.no-results {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
}

.no-results-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.no-results h3 {
    color: #0b3a75;
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.no-results p {
    color: var(--muted-text);
    font-size: 14px;
    margin: 0;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
}

.empty-state-icon {
    font-size: 80px;
    margin-bottom: 20px;
    opacity: 0.4;
}

.empty-state-title {
    font-size: 24px;
    font-weight: 700;
    color: #0b3a75;
    margin-bottom: 12px;
}

.empty-state-text {
    color: var(--muted-text);
    font-size: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .result-item {
        grid-template-columns: 1fr;
    }
    
    .result-item-actions {
        justify-content: flex-start;
    }
    
    .filter-tabs {
        justify-content: flex-start;
    }
    
    .search-input {
        padding-right: 18px;
    }
    
    .search-btn {
        position: static;
        transform: none;
        width: 100%;
        margin-top: 12px;
    }
    
    .search-input-wrapper {
        display: flex;
        flex-direction: column;
    }
}
</style>

<div class="main-wrapper">
    <div class="main-content search-container">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1>🔍 Universal Search</h1>
                    <p>Search across all modules: employees, attendance, CRM, clients, projects, invoices, payroll, and more</p>
                </div>
            </div>
        </div>

        <!-- Search Form -->
        <div class="search-form-wrapper">
            <form method="get" action="<?php echo APP_URL; ?>/public/search.php">
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        name="q" 
                        value="<?php echo htmlspecialchars($q); ?>" 
                        placeholder="🔎 Search across all modules: employees, clients, invoices, projects, payroll..." 
                        class="search-input"
                        autofocus
                        autocomplete="off"
                    />
                    <button type="submit" class="search-btn">Search</button>
                </div>
                
                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <a href="?q=<?php echo urlencode($q); ?>&filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        🌐 All Results
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=employees" class="filter-tab <?php echo $filter === 'employees' ? 'active' : ''; ?>">
                        👥 Employees
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=attendance" class="filter-tab <?php echo $filter === 'attendance' ? 'active' : ''; ?>">
                        📅 Attendance
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=reimbursements" class="filter-tab <?php echo $filter === 'reimbursements' ? 'active' : ''; ?>">
                        💰 Reimbursements
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=leads" class="filter-tab <?php echo $filter === 'leads' ? 'active' : ''; ?>">
                        📇 Leads
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=contacts" class="filter-tab <?php echo $filter === 'contacts' ? 'active' : ''; ?>">
                        📇 Contacts
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=clients" class="filter-tab <?php echo $filter === 'clients' ? 'active' : ''; ?>">
                        🏢 Clients
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=projects" class="filter-tab <?php echo $filter === 'projects' ? 'active' : ''; ?>">
                        📊 Projects
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=catalog" class="filter-tab <?php echo $filter === 'catalog' ? 'active' : ''; ?>">
                        📦 Catalog
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=quotations" class="filter-tab <?php echo $filter === 'quotations' ? 'active' : ''; ?>">
                        � Quotations
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=invoices" class="filter-tab <?php echo $filter === 'invoices' ? 'active' : ''; ?>">
                        🧾 Invoices
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=payments" class="filter-tab <?php echo $filter === 'payments' ? 'active' : ''; ?>">
                        � Payments
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=payroll" class="filter-tab <?php echo $filter === 'payroll' ? 'active' : ''; ?>">
                        💵 Payroll
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=documents" class="filter-tab <?php echo $filter === 'documents' ? 'active' : ''; ?>">
                        📄 Documents
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=visitors" class="filter-tab <?php echo $filter === 'visitors' ? 'active' : ''; ?>">
                        🚶 Visitors
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=notebook" class="filter-tab <?php echo $filter === 'notebook' ? 'active' : ''; ?>">
                        📓 Notebook
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=assets" class="filter-tab <?php echo $filter === 'assets' ? 'active' : ''; ?>">
                        🏷️ Assets
                    </a>
                </div>
            </form>
        </div>

        <!-- Search Stats -->
        <?php if ($q !== '' && strlen($q) >= 2): ?>
            <div class="search-stats">
                Found <strong><?php echo number_format($totalResults); ?></strong> result<?php echo $totalResults !== 1 ? 's' : ''; ?> 
                for "<strong><?php echo htmlspecialchars($q); ?></strong>" 
                in <strong><?php echo $searchTime; ?>ms</strong>
            </div>
        <?php endif; ?>

        <!-- Search Results -->
        <?php if ($q === '' || strlen($q) < 2): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔎</div>
                <div class="empty-state-title">Start Searching</div>
                <div class="empty-state-text">
                    Enter at least 2 characters to search across all 16+ modules including employees, clients, projects, invoices, payroll, and more
                </div>
            </div>
        
        <?php elseif ($totalResults === 0): ?>
            <div class="no-results">
                <div class="no-results-icon">😕</div>
                <h3>No results found for "<?php echo htmlspecialchars($q); ?>"</h3>
                <p>Try different keywords or check your spelling</p>
            </div>
        
        <?php else: ?>
            
            <!-- Employees Results -->
            <?php if (!empty($results['employees'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">👥 Employees</h2>
                        <span class="result-count"><?php echo count($results['employees']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['employees'] as $employee): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <span>📋 <?php echo htmlspecialchars($employee['employee_code']); ?></span>
                                        <?php if (!empty($employee['department'])): ?>
                                            <span>🏢 <?php echo htmlspecialchars($employee['department']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($employee['designation'])): ?>
                                            <span>💼 <?php echo htmlspecialchars($employee['designation']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($employee['mobile_number'])): ?>
                                            <span>📞 <?php echo htmlspecialchars($employee['mobile_number']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($employee['official_email'])): ?>
                                            <span>✉️ <?php echo htmlspecialchars($employee['official_email']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/employee/view_employee.php?id=<?php echo $employee['id']; ?>" class="view-btn">
                                        👁️ View Profile
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Attendance Results -->
            <?php if (!empty($results['attendance'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">📅 Attendance Records</h2>
                        <span class="result-count"><?php echo count($results['attendance']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['attendance'] as $att): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($att['employee_name'] ?? 'N/A'); ?> 
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($att['employee_code'])): ?>
                                            <span>📋 <?php echo htmlspecialchars($att['employee_code']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($att['attendance_date'])): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($att['attendance_date'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($att['check_in_time'])): ?>
                                            <span>⏰ In: <?php echo date('g:i A', strtotime($att['check_in_time'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($att['check_out_time'])): ?>
                                            <span>⏰ Out: <?php echo date('g:i A', strtotime($att['check_out_time'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($att['status'])): ?>
                                            <span class="status-badge status-<?php echo strtolower($att['status']); ?>">
                                                <?php echo htmlspecialchars($att['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/attendance/view.php?id=<?php echo $att['id']; ?>" class="view-btn">
                                        👁️ View Record
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Reimbursements Results -->
            <?php if (!empty($results['reimbursements'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">💰 Reimbursements</h2>
                        <span class="result-count"><?php echo count($results['reimbursements']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['reimbursements'] as $reimb): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($reimb['description']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($reimb['employee_name'])): ?>
                                            <span>👤 <?php echo htmlspecialchars($reimb['employee_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($reimb['category'])): ?>
                                            <span>📁 <?php echo htmlspecialchars($reimb['category']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($reimb['amount'])): ?>
                                            <span>💵 ₹<?php echo number_format($reimb['amount'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($reimb['expense_date'])): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($reimb['expense_date'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($reimb['status'])): ?>
                                            <span class="status-badge status-<?php echo strtolower($reimb['status']); ?>">
                                                <?php echo htmlspecialchars($reimb['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/reimbursements/view.php?id=<?php echo $reimb['id']; ?>" class="view-btn">
                                        👁️ View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- CRM Leads Results -->
            <?php if (!empty($results['leads'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">📇 CRM Leads</h2>
                        <span class="result-count"><?php echo count($results['leads']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['leads'] as $lead): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($lead['name']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if ($lead['company_name']): ?>
                                            <span>🏢 <?php echo htmlspecialchars($lead['company_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($lead['phone']): ?>
                                            <span>📞 <?php echo htmlspecialchars($lead['phone']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($lead['email']): ?>
                                            <span>✉️ <?php echo htmlspecialchars($lead['email']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($lead['status']): ?>
                                            <span class="status-badge status-<?php echo strtolower($lead['status']); ?>">
                                                <?php echo htmlspecialchars($lead['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/crm/leads/view.php?id=<?php echo $lead['id']; ?>" class="view-btn">
                                        👁️ View Lead
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Contacts Results -->
            <?php if (!empty($results['contacts'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">📇 Contacts</h2>
                        <span class="result-count"><?php echo count($results['contacts']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['contacts'] as $contact): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($contact['name']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($contact['company'])): ?>
                                            <span>🏢 <?php echo htmlspecialchars($contact['company']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($contact['designation'])): ?>
                                            <span>💼 <?php echo htmlspecialchars($contact['designation']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($contact['phone'])): ?>
                                            <span>📞 <?php echo htmlspecialchars($contact['phone']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($contact['email'])): ?>
                                            <span>✉️ <?php echo htmlspecialchars($contact['email']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($contact['category'])): ?>
                                            <span>📁 <?php echo htmlspecialchars($contact['category']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/contacts/view.php?id=<?php echo $contact['id']; ?>" class="view-btn">
                                        👁️ View Contact
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Clients Results -->
            <?php if (!empty($results['clients'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">🏢 Clients</h2>
                        <span class="result-count"><?php echo count($results['clients']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['clients'] as $client): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars(($client['client_name'] ?? $client['name'] ?? '')); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($client['contact_person'])): ?>
                                            <span>👤 <?php echo htmlspecialchars($client['contact_person']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($client['phone'])): ?>
                                            <span>📞 <?php echo htmlspecialchars($client['phone']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($client['email'])): ?>
                                            <span>✉️ <?php echo htmlspecialchars($client['email']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($client['address'])): ?>
                                            <span>📍 <?php echo htmlspecialchars(substr($client['address'], 0, 50)) . (strlen($client['address']) > 50 ? '...' : ''); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($client['status'])): ?>
                                            <span class="status-badge status-<?php echo strtolower($client['status']); ?>">
                                                <?php echo htmlspecialchars($client['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/clients/view.php?id=<?php echo $client['id']; ?>" class="view-btn">
                                        👁️ View Client
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Projects Results -->
            <?php if (!empty($results['projects'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">📊 Projects</h2>
                        <span class="result-count"><?php echo count($results['projects']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['projects'] as $project): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($project['title'] ?? ''); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($project['project_code'] ?? '')): ?>
                                            <span>📋 <?php echo htmlspecialchars($project['project_code'] ?? ''); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($project['client_name'] ?? '')): ?>
                                            <span>🏢 <?php echo htmlspecialchars($project['client_name'] ?? ''); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($project['status'] ?? '')): ?>
                                            <span class="status-badge status-<?php echo strtolower($project['status'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($project['status'] ?? ''); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($project['start_date'] ?? '')): ?>
                                            <span>📅 Start: <?php echo date('M j, Y', strtotime($project['start_date'] ?? '')); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($project['end_date'] ?? '')): ?>
                                            <span>📅 End: <?php echo date('M j, Y', strtotime($project['end_date'] ?? '')); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/projects/view.php?id=<?php echo $project['id']; ?>" class="view-btn">
                                        👁️ View Project
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Catalog Items Results -->
            <?php if (!empty($results['catalog'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">📦 Catalog Items</h2>
                        <span class="result-count"><?php echo count($results['catalog']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['catalog'] as $item): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($item['name'] ?? ''); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($item['sku'])): ?>
                                            <span>📋 <?php echo htmlspecialchars($item['sku']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['category'])): ?>
                                            <span>📁 <?php echo htmlspecialchars($item['category']); ?></span>
                                        <?php endif; ?>
                                        <?php /* No 'unit' in items_master, skip or add if present in schema */ ?>
                                        <?php if (isset($item['base_price'])): ?>
                                            <span>💰 Price: ₹<?php echo number_format($item['base_price'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($item['current_stock'])): ?>
                                            <span>📦 Stock: <?php echo $item['current_stock']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/catalog/view.php?id=<?php echo $item['id']; ?>" class="view-btn">
                                        👁️ View Item
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quotations Results -->
            <?php if (!empty($results['quotations'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">📋 Quotations</h2>
                        <span class="result-count"><?php echo count($results['quotations']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['quotations'] as $quotation): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($quotation['quotation_no'] ?? ''); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($quotation['client_name'] ?? '')): ?>
                                            <span>🏢 <?php echo htmlspecialchars($quotation['client_name'] ?? ''); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($quotation['quotation_date'] ?? '')): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($quotation['quotation_date'] ?? '')); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($quotation['total_amount'])): ?>
                                            <span>💰 ₹<?php echo number_format($quotation['total_amount'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($quotation['status'] ?? '')): ?>
                                            <span class="status-badge status-<?php echo strtolower($quotation['status'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($quotation['status'] ?? ''); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/quotations/view.php?id=<?php echo $quotation['id']; ?>" class="view-btn">
                                        👁️ View Quotation
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Invoices Results -->
            <?php if (!empty($results['invoices'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">🧾 Invoices</h2>
                        <span class="result-count"><?php echo count($results['invoices']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['invoices'] as $invoice): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($invoice['invoice_no'] ?? ''); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($invoice['client_name'] ?? '')): ?>
                                            <span>🏢 <?php echo htmlspecialchars($invoice['client_name'] ?? ''); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($invoice['issue_date'] ?? '')): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($invoice['issue_date'] ?? '')); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($invoice['total_amount'])): ?>
                                            <span>💰 ₹<?php echo number_format($invoice['total_amount'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($invoice['due_date'] ?? '')): ?>
                                            <span>⏰ Due: <?php echo date('M j, Y', strtotime($invoice['due_date'] ?? '')); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($invoice['status'] ?? '')): ?>
                                            <span class="status-badge status-<?php echo strtolower($invoice['status'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($invoice['status'] ?? ''); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/invoices/view.php?id=<?php echo $invoice['id']; ?>" class="view-btn">
                                        👁️ View Invoice
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payments Results -->
            <?php if (!empty($results['payments'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">💳 Payments</h2>
                        <span class="result-count"><?php echo count($results['payments']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['payments'] as $payment): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        Payment <?php echo !empty($payment['reference_no'] ?? '') ? htmlspecialchars($payment['reference_no']) : '#' . ($payment['id'] ?? ''); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($payment['client_name'] ?? '')): ?>
                                            <span>🏢 <?php echo htmlspecialchars($payment['client_name'] ?? ''); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($payment['invoice_no'] ?? '')): ?>
                                            <span>🧾 <?php echo htmlspecialchars($payment['invoice_no'] ?? ''); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($payment['amount_received'])): ?>
                                            <span>💰 ₹<?php echo number_format($payment['amount_received'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($payment['payment_mode'] ?? '')): ?>
                                            <span>💳 <?php echo htmlspecialchars($payment['payment_mode'] ?? ''); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($payment['payment_date'] ?? '')): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($payment['payment_date'] ?? '')); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/payments/view.php?id=<?php echo $payment['id']; ?>" class="view-btn">
                                        👁️ View Payment
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payroll Results -->
            <?php if (!empty($results['payroll'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">💵 Payroll Records</h2>
                        <span class="result-count"><?php echo count($results['payroll']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['payroll'] as $payroll): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($payroll['employee_name'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($payroll['employee_code'])): ?>
                                            <span>📋 <?php echo htmlspecialchars($payroll['employee_code']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($payroll['month'])): ?>
                                            <span>📅 <?php echo date('M Y', strtotime($payroll['month'] . '-01')); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($payroll['base_salary'])): ?>
                                            <span>💼 Base: ₹<?php echo number_format($payroll['base_salary'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($payroll['allowances']) && $payroll['allowances'] > 0): ?>
                                            <span>➕ Allow: ₹<?php echo number_format($payroll['allowances'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($payroll['reimbursements']) && $payroll['reimbursements'] > 0): ?>
                                            <span>💸 Reimb: ₹<?php echo number_format($payroll['reimbursements'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($payroll['deductions']) && $payroll['deductions'] > 0): ?>
                                            <span>➖ Deduct: ₹<?php echo number_format($payroll['deductions'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($payroll['bonus']) && $payroll['bonus'] > 0): ?>
                                            <span>🎁 Bonus: ₹<?php echo number_format($payroll['bonus'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($payroll['penalties']) && $payroll['penalties'] > 0): ?>
                                            <span>⚠️ Penalty: ₹<?php echo number_format($payroll['penalties'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($payroll['net_pay'])): ?>
                                            <span>💵 Net: ₹<?php echo number_format($payroll['net_pay'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($payroll['status'])): ?>
                                            <span class="status-badge status-<?php echo strtolower($payroll['status']); ?>">
                                                <?php echo htmlspecialchars($payroll['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/payroll/view.php?id=<?php echo $payroll['id']; ?>" class="view-btn">
                                        👁️ View Record
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- CRM Tasks Results -->
            <?php if (!empty($results['tasks'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">✅ CRM Tasks</h2>
                        <span class="result-count"><?php echo count($results['tasks']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['tasks'] as $task): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($task['status'])): ?>
                                            <span class="status-badge status-<?php echo strtolower($task['status']); ?>">
                                                <?php echo htmlspecialchars($task['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($task['due_date'])): ?>
                                            <span>📅 Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($task['assigned_name'])): ?>
                                            <span>👤 <?php echo htmlspecialchars($task['assigned_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/crm/tasks/view.php?id=<?php echo $task['id']; ?>" class="view-btn">
                                        👁️ View Task
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- CRM Calls Results -->
            <?php if (!empty($results['calls'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">☎️ CRM Calls</h2>
                        <span class="result-count"><?php echo count($results['calls']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['calls'] as $call): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($call['title']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($call['lead_name'])): ?>
                                            <span>� <?php echo htmlspecialchars($call['lead_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($call['company_name'])): ?>
                                            <span>🏢 <?php echo htmlspecialchars($call['company_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($call['call_date'])): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($call['call_date'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($call['duration'])): ?>
                                            <span>⏱️ <?php echo $call['duration']; ?> min</span>
                                        <?php endif; ?>
                                        <?php if (!empty($call['outcome'])): ?>
                                            <span>📝 <?php echo htmlspecialchars($call['outcome']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/crm/calls/view.php?id=<?php echo $call['id']; ?>" class="view-btn">
                                        👁️ View Call
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- CRM Meetings Results -->
            <?php if (!empty($results['meetings'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">🗓️ CRM Meetings</h2>
                        <span class="result-count"><?php echo count($results['meetings']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['meetings'] as $meeting): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($meeting['title']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($meeting['lead_name'])): ?>
                                            <span>👤 <?php echo htmlspecialchars($meeting['lead_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($meeting['company_name'])): ?>
                                            <span>🏢 <?php echo htmlspecialchars($meeting['company_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($meeting['meeting_date'])): ?>
                                            <span>📅 <?php echo date('M j, Y g:i A', strtotime($meeting['meeting_date'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($meeting['location'])): ?>
                                            <span>📍 <?php echo htmlspecialchars($meeting['location']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($meeting['outcome'])): ?>
                                            <span>📝 <?php echo htmlspecialchars($meeting['outcome']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/crm/meetings/view.php?id=<?php echo $meeting['id']; ?>" class="view-btn">
                                        👁️ View Meeting
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- CRM Visits Results -->
            <?php if (!empty($results['visits'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">🚗 CRM Visits</h2>
                        <span class="result-count"><?php echo count($results['visits']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['visits'] as $visit): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($visit['title'] ?: 'Visit'); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($visit['lead_name'])): ?>
                                            <span>👤 <?php echo htmlspecialchars($visit['lead_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($visit['company_name'])): ?>
                                            <span>🏢 <?php echo htmlspecialchars($visit['company_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($visit['visit_date'])): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($visit['visit_date'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($visit['location'])): ?>
                                            <span>📍 <?php echo htmlspecialchars($visit['location']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($visit['notes'])): ?>
                                            <span>📝 <?php echo htmlspecialchars(substr($visit['notes'], 0, 50)) . (strlen($visit['notes']) > 50 ? '...' : ''); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/crm/visits/view.php?id=<?php echo $visit['id']; ?>" class="view-btn">
                                        👁️ View Visit
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Notebook Results -->
            <?php if (!empty($results['notebook'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">📓 Notebook</h2>
                        <span class="result-count"><?php echo count($results['notebook']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['notebook'] as $note): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($note['title']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($note['category'])): ?>
                                            <span>📁 <?php echo htmlspecialchars($note['category']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($note['author_name'])): ?>
                                            <span>👤 <?php echo htmlspecialchars($note['author_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($note['created_at'])): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($note['created_at'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($note['tags'])): ?>
                                            <span>🏷️ <?php echo htmlspecialchars($note['tags']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($note['content'])): ?>
                                            <span>📝 <?php echo htmlspecialchars(substr(strip_tags($note['content']), 0, 80)) . (strlen($note['content']) > 80 ? '...' : ''); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/notebook/view.php?id=<?php echo $note['id']; ?>" class="view-btn">
                                        👁️ View Note
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Assets Results -->
            <?php if (!empty($results['assets'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">🏷️ Assets</h2>
                        <span class="result-count"><?php echo count($results['assets']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['assets'] as $asset): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($asset['asset_name']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($asset['asset_code'])): ?>
                                            <span>📋 <?php echo htmlspecialchars($asset['asset_code']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($asset['category'])): ?>
                                            <span>📁 <?php echo htmlspecialchars($asset['category']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($asset['status'])): ?>
                                            <span class="status-badge status-<?php echo strtolower($asset['status']); ?>">
                                                <?php echo htmlspecialchars($asset['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($asset['assigned_to_name'])): ?>
                                            <span>👤 <?php echo htmlspecialchars($asset['assigned_to_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($asset['purchase_cost'])): ?>
                                            <span>💰 ₹<?php echo number_format($asset['purchase_cost'], 2); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($asset['purchase_date'])): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($asset['purchase_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/assets/view.php?id=<?php echo $asset['id']; ?>" class="view-btn">
                                        👁️ View Asset
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Documents Results -->
            <?php if (!empty($results['documents'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">📄 Documents</h2>
                        <span class="result-count"><?php echo count($results['documents']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['documents'] as $doc): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($doc['title']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($doc['doc_type'])): ?>
                                            <span>📁 <?php echo htmlspecialchars($doc['doc_type']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($doc['file_path'])): ?>
                                            <span>📎 <?php echo htmlspecialchars(basename($doc['file_path'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($doc['uploaded_by_name'])): ?>
                                            <span>👤 <?php echo htmlspecialchars($doc['uploaded_by_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($doc['created_at'])): ?>
                                            <span>📅 <?php echo date('M j, Y', strtotime($doc['created_at'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($doc['tags'])): ?>
                                            <span>🏷️ <?php echo htmlspecialchars($doc['tags']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/documents/view.php?id=<?php echo $doc['id']; ?>" class="view-btn">
                                        👁️ View Document
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Visitors Results -->
            <?php if (!empty($results['visitors'])): ?>
                <div class="result-section">
                    <div class="result-section-header">
                        <h2 class="result-section-title">🚶 Visitor Logs</h2>
                        <span class="result-count"><?php echo count($results['visitors']); ?></span>
                    </div>
                    <div class="result-items">
                        <?php foreach ($results['visitors'] as $visitor): ?>
                            <div class="result-item">
                                <div class="result-item-content">
                                    <div class="result-item-title">
                                        <?php echo htmlspecialchars($visitor['visitor_name']); ?>
                                    </div>
                                    <div class="result-item-meta">
                                        <?php if (!empty($visitor['phone'])): ?>
                                            <span>📞 <?php echo htmlspecialchars($visitor['phone']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($visitor['purpose'])): ?>
                                            <span>📋 <?php echo htmlspecialchars($visitor['purpose']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($visitor['host_name'])): ?>
                                            <span>👤 Host: <?php echo htmlspecialchars($visitor['host_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($visitor['check_in_time'])): ?>
                                            <span>⏰ <?php echo date('M j, Y g:i A', strtotime($visitor['check_in_time'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="result-item-actions">
                                    <a href="<?php echo APP_URL; ?>/public/visitors/view.php?id=<?php echo $visitor['id']; ?>" class="view-btn">
                                        👁️ View Log
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>