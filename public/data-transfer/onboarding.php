<?php
/**
 * Data Transfer Module - Onboarding Page
 * Setup instructions and database configuration
 */

require_once __DIR__ . '/../../config/config.php';

$page_title = 'Data Transfer Module - Setup Required - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
?>

<style>
    .onboarding-container {
        max-width: 900px;
        margin: 50px auto;
        padding: 40px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .onboarding-header {
        text-align: center;
        margin-bottom: 40px;
        padding-bottom: 20px;
        border-bottom: 3px solid #003581;
    }
    
    .onboarding-header h1 {
        color: #003581;
        font-size: 32px;
        margin-bottom: 10px;
    }
    
    .onboarding-header p {
        color: #6c757d;
        font-size: 16px;
    }
    
    .setup-card {
        background: #f8f9fa;
        border-left: 4px solid #003581;
        padding: 24px;
        margin: 20px 0;
        border-radius: 8px;
    }
    
    .setup-card h3 {
        color: #003581;
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 20px;
    }
    
    .setup-card p {
        color: #495057;
        line-height: 1.6;
        margin-bottom: 10px;
    }
    
    .setup-card ul {
        color: #495057;
        line-height: 1.8;
        margin: 15px 0;
    }
    
    .setup-card ul li {
        margin: 8px 0;
    }
    
    .feature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin: 30px 0;
    }
    
    .feature-item {
        background: white;
        padding: 20px;
        border-radius: 8px;
        border: 2px solid #e3f2fd;
        text-align: center;
    }
    
    .feature-item h4 {
        color: #003581;
        margin: 10px 0;
        font-size: 18px;
    }
    
    .feature-item p {
        color: #6c757d;
        font-size: 14px;
        margin: 0;
    }
    
    .feature-icon {
        font-size: 40px;
        margin-bottom: 10px;
    }
    
    .btn-setup {
        display: inline-block;
        padding: 15px 40px;
        background: #003581;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: 600;
        transition: all 0.3s ease;
        margin-top: 20px;
    }
    
    .btn-setup:hover {
        background: #002a66;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,53,129,0.3);
    }
    
    .warning-box {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
    }
    
    .warning-box h4 {
        color: #856404;
        margin-top: 0;
        margin-bottom: 10px;
    }
    
    .warning-box p {
        color: #856404;
        margin: 0;
    }
    
    .center-action {
        text-align: center;
        margin-top: 40px;
        padding-top: 30px;
        border-top: 2px solid #e0e0e0;
    }
</style>

<div class="onboarding-container">
    <div class="onboarding-header">
        <h1>🔁 Data Transfer Module Setup</h1>
        <p>Import and export data seamlessly with full audit trail and validation</p>
    </div>

    <div class="setup-card">
        <h3>📋 What is the Data Transfer Module?</h3>
        <p>The Data Transfer Module provides a secure, structured interface for importing and exporting data directly to and from your ERP tables. It eliminates the need for direct database access while maintaining full control, validation, and audit trails.</p>
    </div>

    <div class="feature-grid">
        <div class="feature-item">
            <div class="feature-icon">📥</div>
            <h4>CSV Import</h4>
            <p>Upload CSV files with automatic validation and error handling</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">📤</div>
            <h4>CSV Export</h4>
            <p>Download complete table data in standard CSV format</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">💾</div>
            <h4>Auto Backup</h4>
            <p>Automatic backups before every import operation</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">📊</div>
            <h4>Activity Log</h4>
            <p>Complete audit trail of all import/export operations</p>
        </div>
    </div>

    <div class="setup-card">
        <h3>✨ Key Features</h3>
        <ul>
            <li><strong>Smart Import:</strong> Automatically updates existing records (by ID) or inserts new ones</li>
            <li><strong>Sample CSV Generator:</strong> Download pre-formatted templates for any table</li>
            <li><strong>Data Validation:</strong> Real-time validation of data types, formats, and required fields</li>
            <li><strong>Error Reporting:</strong> Downloadable CSV with detailed error descriptions for failed rows</li>
            <li><strong>Transaction Safety:</strong> All operations run in transactions with automatic rollback on error</li>
            <li><strong>Ownership Tracking:</strong> Automatically assigns created_by field to current user</li>
        </ul>
    </div>

    <div class="warning-box">
        <h4>⚠️ Before You Begin</h4>
        <p>The setup process will create the <code>data_transfer_logs</code> table to track all import/export activities. This is a one-time setup and will not affect your existing data.</p>
    </div>

    <div class="setup-card">
        <h3>🚀 Setup Process</h3>
        <p>Click the button below to:</p>
        <ul>
            <li>Create the <code>data_transfer_logs</code> table</li>
            <li>Set up necessary indexes and relationships</li>
            <li>Initialize the module for use</li>
        </ul>
        <p style="margin-top: 20px;"><strong>Time required:</strong> Less than 10 seconds</p>
    </div>

    <div class="center-action">
        <a href="<?php echo APP_URL; ?>/scripts/setup_data_transfer_tables.php" class="btn-setup">
            🔧 Run Setup Now
        </a>
        <p style="margin-top: 20px; color: #6c757d;">You will be redirected to the setup page</p>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
