<?php
/**
 * Data Transfer Module - Export Data
 * Export table data to CSV files
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!data_transfer_tables_exist($conn)) {
    require_once __DIR__ . '/onboarding.php';
    exit;
}

// Get accessible tables
$accessible_tables = get_accessible_tables($conn);

$page_title = 'Export Data - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📤 Export Data</h1>
                    <p>Download table data in CSV format</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['flash_success'];
                unset($_SESSION['flash_success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo htmlspecialchars($_SESSION['flash_error']); 
                unset($_SESSION['flash_error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Export Instructions -->
        <div class="card" style="margin-bottom: 25px; background: linear-gradient(to right, #e3f2fd, #ffffff);">
            <h3 style="color: #003581; margin: 0 0 15px 0;">📋 About Data Export</h3>
            <p style="margin: 0; line-height: 1.8; color: #495057;">
                Export complete table data to CSV format. The exported file will include all records with proper headers 
                and can be used for backup, analysis, or re-import after modifications.
            </p>
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; padding: 12px; margin-top: 15px;">
                <strong style="color: #0c5460;">💡 What you'll get:</strong>
                <ul style="margin: 8px 0 0 0; padding-left: 20px; color: #0c5460;">
                    <li>UTF-8 encoded CSV file with all table data</li>
                    <li>Proper column headers matching database field names</li>
                    <li>Auto-generated filename with timestamp</li>
                    <li>Activity logged for audit trail</li>
                </ul>
            </div>
        </div>

        <!-- Export Form -->
        <div class="card" style="margin-bottom: 25px;">
            <h3 style="color: #003581; margin: 0 0 20px 0; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                🎯 Select Table to Export
            </h3>
            
            <form id="exportForm" method="GET" style="max-width: 600px;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                        Select Table *
                    </label>
                    <select name="table_name" id="tableSelect" required 
                            style="width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 15px;">
                        <option value="">-- Choose a table --</option>
                        <?php foreach ($accessible_tables as $table): ?>
                            <option value="<?php echo htmlspecialchars($table); ?>">
                                <?php echo htmlspecialchars($table); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="tableInfo" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <p style="margin: 0 0 10px 0; font-weight: 600; color: #495057;">📊 Table Information</p>
                    <div id="tableInfoContent" style="font-size: 14px; color: #6c757d;"></div>
                </div>

                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px;">
                        📥 Export to CSV
                    </button>
                    <button type="button" onclick="location.href='index.php'" class="btn btn-secondary" style="padding: 12px 30px; font-size: 16px; margin-left: 10px;">
                        Cancel
                    </button>
                </div>

                <div id="progressSection" style="display: none; margin-top: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="text-align: center; margin-bottom: 15px;">
                        <div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #003581; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    </div>
                    <p style="text-align: center; margin: 0; color: #003581; font-weight: 600;">
                        Generating CSV file... Please wait.
                    </p>
                </div>
            </form>
        </div>

        <!-- Result Section -->
        <div id="resultSection" style="display: none;" class="card">
            <h3 style="color: #003581; margin: 0 0 20px 0;">📊 Export Results</h3>
            <div id="resultContent"></div>
        </div>
    </div>
</div>

<style>
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<script>
// Get table info when table is selected
document.getElementById('tableSelect').addEventListener('change', async function() {
    const tableName = this.value;
    const tableInfo = document.getElementById('tableInfo');
    const tableInfoContent = document.getElementById('tableInfoContent');
    
    if (!tableName) {
        tableInfo.style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`../api/data-transfer/table-info.php?table=${encodeURIComponent(tableName)}`);
        const result = await response.json();
        
        if (result.success) {
            tableInfoContent.innerHTML = `
                <strong>Total Records:</strong> ${result.record_count.toLocaleString()}<br>
                <strong>Columns:</strong> ${result.column_count}
            `;
            tableInfo.style.display = 'block';
        }
    } catch (error) {
        console.error('Error fetching table info:', error);
    }
});

// Handle form submission
document.getElementById('exportForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const tableName = document.getElementById('tableSelect').value;
    if (!tableName) {
        alert('Please select a table');
        return;
    }
    
    const progressSection = document.getElementById('progressSection');
    const resultSection = document.getElementById('resultSection');
    const submitBtn = this.querySelector('button[type="submit"]');
    
    // Show progress
    progressSection.style.display = 'block';
    submitBtn.disabled = true;
    resultSection.style.display = 'none';
    
    try {
        const response = await fetch(`../api/data-transfer/export.php?table=${encodeURIComponent(tableName)}`);
        const result = await response.json();
        
        // Hide progress
        progressSection.style.display = 'none';
        submitBtn.disabled = false;
        
        // Show results
        resultSection.style.display = 'block';
        
        if (result.success) {
            let html = '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 20px;">';
            html += '<h4 style="color: #155724; margin: 0 0 15px 0;">✅ Export Successful</h4>';
            html += `<p style="color: #155724; margin: 0 0 15px 0;"><strong>${result.record_count}</strong> records exported from <strong>${tableName}</strong></p>`;
            html += `<a href="${result.download_url}" class="btn btn-primary" download>📥 Download CSV File</a>`;
            html += '</div>';
            html += '<div style="margin-top: 20px;"><a href="index.php" class="btn btn-secondary">Go to Dashboard</a></div>';
            
            document.getElementById('resultContent').innerHTML = html;
            
            // Auto-download
            window.location.href = result.download_url;
            
        } else {
            let html = '<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 20px;">';
            html += '<h4 style="color: #721c24; margin: 0 0 10px 0;">❌ Export Failed</h4>';
            html += `<p style="color: #721c24; margin: 0;">${result.message}</p>`;
            html += '</div>';
            html += '<div style="margin-top: 20px;"><button type="button" onclick="location.reload()" class="btn btn-secondary">Try Again</button></div>';
            
            document.getElementById('resultContent').innerHTML = html;
        }
        
    } catch (error) {
        progressSection.style.display = 'none';
        submitBtn.disabled = false;
        
        resultSection.style.display = 'block';
        let html = '<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 20px;">';
        html += '<h4 style="color: #721c24; margin: 0 0 10px 0;">❌ Error</h4>';
        html += `<p style="color: #721c24; margin: 0;">${error.message}</p>`;
        html += '</div>';
        
        document.getElementById('resultContent').innerHTML = html;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
