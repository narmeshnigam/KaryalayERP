<?php
/**
 * Data Transfer Module - Import Data
 * Upload CSV files to import data into ERP tables
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

$page_title = 'Import Data - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📥 Import Data</h1>
                    <p>Upload CSV files to import data into your ERP tables</p>
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

        <!-- Import Instructions -->
        <div class="card" style="margin-bottom: 25px; background: linear-gradient(to right, #e3f2fd, #ffffff);">
            <h3 style="color: #003581; margin: 0 0 15px 0;">📋 How to Import Data</h3>
            <ol style="margin: 0; padding-left: 20px; line-height: 1.8; color: #495057;">
                <li><strong>Select a table</strong> from the dropdown below</li>
                <li><strong>Download the sample CSV</strong> to see the correct format and field names</li>
                <li><strong>Fill your CSV file</strong> with data following the sample structure</li>
                <li><strong>Upload the file</strong> using the form below</li>
                <li>System will <strong>validate and process</strong> your data automatically</li>
            </ol>
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 12px; margin-top: 15px;">
                <strong style="color: #856404;">💡 Pro Tips:</strong>
                <ul style="margin: 8px 0 0 0; padding-left: 20px; color: #856404;">
                    <li>Include <code>id</code> column to update existing records, omit it to insert new ones</li>
                    <li><code>created_by</code> will be auto-assigned to you if not provided</li>
                    <li>Automatic backup will be created before import</li>
                    <li>Failed rows will be exported with error descriptions</li>
                </ul>
            </div>
        </div>

        <!-- Import Form -->
        <div class="card" style="margin-bottom: 25px;">
            <h3 style="color: #003581; margin: 0 0 20px 0; border-bottom: 2px solid #003581; padding-bottom: 10px;">
                📤 Upload CSV File
            </h3>
            
            <form id="importForm" method="POST" enctype="multipart/form-data" style="max-width: 600px;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                        Select Target Table *
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

                <div id="sampleSection" style="display: none; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <p style="margin: 0 0 10px 0; color: #495057; font-weight: 600;">📄 Sample CSV Template</p>
                    <button type="button" id="downloadSampleBtn" class="btn btn-accent" style="padding: 10px 20px;">
                        📥 Download Sample CSV
                    </button>
                    <p style="margin: 10px 0 0 0; font-size: 13px; color: #6c757d;">
                        Download this file to see the correct format and required fields
                    </p>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                        Upload CSV File *
                    </label>
                    <input type="file" name="csv_file" id="csvFile" accept=".csv" required
                           style="width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 4px;">
                    <small style="color: #6c757d; display: block; margin-top: 5px;">
                        Maximum file size: 10 MB | Format: UTF-8 CSV
                    </small>
                </div>

                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px;">
                        🚀 Start Import
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
                        Processing your import... Please wait.
                    </p>
                </div>
            </form>
        </div>

        <!-- Result Section (will be shown after import) -->
        <div id="resultSection" style="display: none;" class="card">
            <h3 style="color: #003581; margin: 0 0 20px 0;">📊 Import Results</h3>
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
// Show sample download section when table is selected
document.getElementById('tableSelect').addEventListener('change', function() {
    const tableName = this.value;
    const sampleSection = document.getElementById('sampleSection');
    
    if (tableName) {
        sampleSection.style.display = 'block';
    } else {
        sampleSection.style.display = 'none';
    }
});

// Download sample CSV
document.getElementById('downloadSampleBtn').addEventListener('click', async function() {
    const tableName = document.getElementById('tableSelect').value;
    if (!tableName) {
        alert('Please select a table first');
        return;
    }
    
    try {
        const response = await fetch(`../api/data-transfer/sample.php?table=${encodeURIComponent(tableName)}`);
        const result = await response.json();
        
        if (result.success) {
            // Trigger download
            window.location.href = result.url;
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error downloading sample: ' + error.message);
    }
});

// Handle form submission
document.getElementById('importForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    console.log('=== Import Started ===');
    
    const formData = new FormData(this);
    console.log('Table:', formData.get('table_name'));
    console.log('File:', formData.get('csv_file')?.name);
    
    const progressSection = document.getElementById('progressSection');
    const resultSection = document.getElementById('resultSection');
    const submitBtn = this.querySelector('button[type="submit"]');
    
    // Show progress
    progressSection.style.display = 'block';
    submitBtn.disabled = true;
    resultSection.style.display = 'none';
    
    try {
        console.log('Sending request to API...');
        const response = await fetch('../api/data-transfer/import.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', Object.fromEntries(response.headers.entries()));
        
        const text = await response.text();
        console.log('Raw response:', text);
        
        let result;
        try {
            result = JSON.parse(text);
            console.log('Parsed result:', result);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            throw new Error('Server returned invalid response: ' + text.substring(0, 500));
        }
        
        // Hide progress
        progressSection.style.display = 'none';
        submitBtn.disabled = false;
        
        // Show results
        resultSection.style.display = 'block';
        console.log('Success:', result.success);
        console.log('Total rows:', result.total_rows);
        console.log('Success count:', result.success_count);
        console.log('Failed count:', result.failed_count);
        console.log('Errors:', result.errors);
        console.log('Table count after:', result.table_count_after);
        
        if (result.success) {
            let html = '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 20px; margin-bottom: 20px;">';
            html += '<h4 style="color: #155724; margin: 0 0 15px 0;">✅ Import Completed</h4>';
            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
            html += `<div><strong style="color: #155724;">Total Rows:</strong> ${result.total_rows}</div>`;
            html += `<div><strong style="color: #155724;">Successful:</strong> ${result.success_count}</div>`;
            html += `<div><strong style="color: #155724;">Failed:</strong> ${result.failed_count}</div>`;
            html += `<div><strong style="color: #155724;">Status:</strong> ${result.status}</div>`;
            if (result.table_count_after !== undefined) {
                html += `<div><strong style="color: #155724;">Records in table:</strong> ${result.table_count_after}</div>`;
            }
            html += '</div>';
            
            if (result.error_file) {
                html += `<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #c3e6cb;">`;
                html += `<p style="color: #856404; margin: 0 0 10px 0;">⚠️ Some rows failed. Download the error report:</p>`;
                html += `<a href="${result.error_file}" class="btn btn-secondary" download>📄 Download Error Report</a>`;
                html += `</div>`;
            }
            
            html += '</div>';
            
            if (result.backup_path) {
                html += '<div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; padding: 15px; margin-top: 15px;">';
                html += '<p style="color: #0c5460; margin: 0;">💾 <strong>Backup created:</strong> ' + result.backup_path + '</p>';
                html += '</div>';
            }
            
            html += '<div style="margin-top: 20px;"><a href="index.php" class="btn btn-primary">Go to Dashboard</a></div>';
            
            document.getElementById('resultContent').innerHTML = html;
            
            // Reset form
            this.reset();
            document.getElementById('sampleSection').style.display = 'none';
            
        } else {
            let html = '<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 20px;">';
            html += '<h4 style="color: #721c24; margin: 0 0 10px 0;">❌ Import Failed</h4>';
            html += `<p style="color: #721c24; margin: 0;">${result.message}</p>`;
            html += '</div>';
            html += '<div style="margin-top: 20px;"><button type="button" onclick="location.reload()" class="btn btn-secondary">Try Again</button></div>';
            
            document.getElementById('resultContent').innerHTML = html;
        }
        
    } catch (error) {
        console.error('=== Import Error ===');
        console.error('Error:', error);
        
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
