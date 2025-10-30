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
$filter = $_GET['filter'] ?? 'all'; // all, employees, leads, tasks, calls, meetings, visits, documents, visitors
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
    'leads' => [],
    'tasks' => [],
    'calls' => [],
    'meetings' => [],
    'visits' => [],
    'documents' => [],
    'visitors' => []
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
    
    // Search CRM Leads
    if (($filter === 'all' || $filter === 'leads') && tableExists($conn, 'crm_leads')) {
        $sql = "SELECT id, name, company_name, phone, email, status, source 
                FROM crm_leads 
                WHERE (name LIKE '$like' 
                   OR company_name LIKE '$like' 
                   OR phone LIKE '$like' 
                   OR email LIKE '$like'
                   OR source LIKE '$like')
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
    
    // Search CRM Tasks
    if (($filter === 'all' || $filter === 'tasks') && tableExists($conn, 'crm_tasks')) {
        $sql = "SELECT t.id, t.title, t.status, t.due_date, t.assigned_to,
                       CONCAT(e.first_name, ' ', e.last_name) as assigned_name
                FROM crm_tasks t
                LEFT JOIN employees e ON t.assigned_to = e.id
                WHERE (t.title LIKE '$like' OR t.description LIKE '$like')
                  AND t.deleted_at IS NULL
                ORDER BY 
                    CASE 
                        WHEN t.title LIKE '$searchTerm%' THEN 1
                        ELSE 2
                    END,
                    t.due_date ASC
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['tasks'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search CRM Calls
    if (($filter === 'all' || $filter === 'calls') && tableExists($conn, 'crm_calls')) {
        $sql = "SELECT c.id, c.title, c.call_date, c.duration, c.outcome,
                       l.name as lead_name, l.company_name
                FROM crm_calls c
                LEFT JOIN crm_leads l ON c.lead_id = l.id
                WHERE (c.title LIKE '$like' OR c.summary LIKE '$like' OR l.name LIKE '$like' OR l.company_name LIKE '$like')
                  AND c.deleted_at IS NULL
                ORDER BY c.call_date DESC
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['calls'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search CRM Meetings
    if (($filter === 'all' || $filter === 'meetings') && tableExists($conn, 'crm_meetings')) {
                $sql = "SELECT m.id, m.title, m.meeting_date, m.location, m.outcome,
                                             l.name as lead_name, l.company_name
                                FROM crm_meetings m
                                LEFT JOIN crm_leads l ON m.lead_id = l.id
                                WHERE (m.title LIKE '$like' OR m.description LIKE '$like' OR m.location LIKE '$like' 
                                     OR l.name LIKE '$like' OR l.company_name LIKE '$like')
                                    AND m.deleted_at IS NULL
                                ORDER BY m.meeting_date DESC
                                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['meetings'][] = $row;
            $totalResults++;
        }
        if ($r) mysqli_free_result($r);
    }
    
    // Search CRM Visits
    if (($filter === 'all' || $filter === 'visits') && tableExists($conn, 'crm_visits')) {
        $sql = "SELECT v.id, v.title, v.visit_date, v.notes, v.location, v.outcome,
                       l.name as lead_name, l.company_name
                FROM crm_visits v
                LEFT JOIN crm_leads l ON v.lead_id = l.id
                WHERE (v.title LIKE '$like' OR v.notes LIKE '$like' OR v.location LIKE '$like' OR v.outcome LIKE '$like'
                   OR l.name LIKE '$like' OR l.company_name LIKE '$like')
                  AND v.deleted_at IS NULL
                ORDER BY v.visit_date DESC
                LIMIT 30";
        $r = mysqli_query($conn, $sql);
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $results['visits'][] = $row;
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
                    <p>Search across employees, CRM data, documents, and more</p>
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
                        placeholder="🔎 Search employees, leads, tasks, documents, visitors..." 
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
                    <a href="?q=<?php echo urlencode($q); ?>&filter=leads" class="filter-tab <?php echo $filter === 'leads' ? 'active' : ''; ?>">
                        📇 Leads
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=tasks" class="filter-tab <?php echo $filter === 'tasks' ? 'active' : ''; ?>">
                        ✅ Tasks
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=calls" class="filter-tab <?php echo $filter === 'calls' ? 'active' : ''; ?>">
                        ☎️ Calls
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=meetings" class="filter-tab <?php echo $filter === 'meetings' ? 'active' : ''; ?>">
                        🗓️ Meetings
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=visits" class="filter-tab <?php echo $filter === 'visits' ? 'active' : ''; ?>">
                        🚗 Visits
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=documents" class="filter-tab <?php echo $filter === 'documents' ? 'active' : ''; ?>">
                        📄 Documents
                    </a>
                    <a href="?q=<?php echo urlencode($q); ?>&filter=visitors" class="filter-tab <?php echo $filter === 'visitors' ? 'active' : ''; ?>">
                        🚶 Visitors
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
                    Enter at least 2 characters to search across employees, CRM data, documents, and more
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