<?php
/**
 * Migration Script - Add Geo-location Columns to Attendance Table
 * Adds latitude/longitude fields for check-in and check-out tracking
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$page_title = "Add Geo-location to Attendance - Migration";
require_once __DIR__ . '/../includes/header_sidebar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function migrateAttendanceGeoLocation() {
    $conn = createConnection(true);
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Check if attendance table exists
        $check_query = "SHOW TABLES LIKE 'attendance'";
        $result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($result) == 0) {
            closeConnection($conn);
            return ['success' => false, 'message' => 'Attendance table does not exist! Please run setup first.'];
        }
        
        // Check if columns already exist
        $check_columns = "SHOW COLUMNS FROM attendance LIKE 'checkin_latitude'";
        $col_result = mysqli_query($conn, $check_columns);
        
        if (mysqli_num_rows($col_result) > 0) {
            closeConnection($conn);
            return ['success' => false, 'message' => 'Geo-location columns already exist!'];
        }
        
        // Add geo-location columns after check_out_time
        $alter_queries = [
            "ALTER TABLE attendance 
             ADD COLUMN checkin_latitude DECIMAL(10,8) DEFAULT NULL COMMENT 'Check-in location latitude' 
             AFTER check_out_time",
            
            "ALTER TABLE attendance 
             ADD COLUMN checkin_longitude DECIMAL(11,8) DEFAULT NULL COMMENT 'Check-in location longitude' 
             AFTER checkin_latitude",
            
            "ALTER TABLE attendance 
             ADD COLUMN checkout_latitude DECIMAL(10,8) DEFAULT NULL COMMENT 'Check-out location latitude' 
             AFTER checkin_longitude",
            
            "ALTER TABLE attendance 
             ADD COLUMN checkout_longitude DECIMAL(11,8) DEFAULT NULL COMMENT 'Check-out location longitude' 
             AFTER checkout_latitude"
        ];
        
        foreach ($alter_queries as $query) {
            if (!mysqli_query($conn, $query)) {
                closeConnection($conn);
                return ['success' => false, 'message' => 'Error adding columns: ' . mysqli_error($conn)];
            }
        }
        
        closeConnection($conn);
        return [
            'success' => true, 
            'message' => 'Successfully added geo-location columns to attendance table! Added: checkin_latitude, checkin_longitude, checkout_latitude, checkout_longitude'
        ];
        
    } catch (Exception $e) {
        closeConnection($conn);
        return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
    }
}

// Run migration if form submitted
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = migrateAttendanceGeoLocation();
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📍 Add Geo-location Tracking</h1>
                    <p>Migrate attendance table to support GPS coordinates</p>
                </div>
                <div>
                    <a href="../public/dashboard.php" class="btn btn-accent">← Back to Dashboard</a>
                </div>
            </div>
        </div>

        <div class="card" style="max-width: 800px; margin: 0 auto;">
            <?php if ($result): ?>
                <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($result['message']); ?>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="../public/attendance/index.php" class="btn">Go to Attendance Module</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>ℹ️ Migration Information</strong><br><br>
                    This migration will add the following columns to the <strong>attendance</strong> table:<br><br>
                    • <strong>checkin_latitude</strong> - GPS latitude for check-in location<br>
                    • <strong>checkin_longitude</strong> - GPS longitude for check-in location<br>
                    • <strong>checkout_latitude</strong> - GPS latitude for check-out location<br>
                    • <strong>checkout_longitude</strong> - GPS longitude for check-out location<br><br>
                    
                    <strong>🎯 Use Cases:</strong><br>
                    • Track employee location when checking in/out from mobile devices<br>
                    • Verify employees are at designated work locations<br>
                    • View check-in/out locations on Google Maps<br>
                    • Support remote work and field employee tracking<br><br>
                    
                    <strong>⚠️ Note:</strong> Existing attendance records will have NULL values for these fields.
                </div>

                <form method="POST" style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn" style="padding: 15px 40px; font-size: 16px;">
                        🚀 Add Geo-location Columns
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_sidebar.php'; ?>
