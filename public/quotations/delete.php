<?php
/**
 * Quotations Module - Delete Quotation
 * Delete a quotation and its related data
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Check if tables exist
if (!quotations_tables_exist($conn)) {
    header('Location: ../../scripts/setup_quotations_tables.php');
    exit;
}

$quotation_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$quotation_id) {
    $_SESSION['flash_error'] = 'Invalid quotation ID.';
    header('Location: index.php');
    exit;
}

$quotation = get_quotation_by_id($conn, $quotation_id);
if (!$quotation) {
    $_SESSION['flash_error'] = 'Quotation not found.';
    header('Location: index.php');
    exit;
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $result = delete_quotation($conn, $quotation_id, $CURRENT_USER_ID);
    
    if ($result['success']) {
        $_SESSION['flash_success'] = 'Quotation deleted successfully.';
    } else {
        $_SESSION['flash_error'] = $result['message'];
    }
    
    header('Location: index.php');
    exit;
}

$page_title = 'Delete Quotation - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>🗑️ Delete Quotation</h1>
                    <p>Confirm deletion of quotation</p>
                </div>
                <a href="view.php?id=<?php echo $quotation_id; ?>" class="btn btn-secondary">← Back</a>
            </div>
        </div>

        <!-- Confirmation Card -->
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 64px; color: #dc3545; margin-bottom: 20px;">⚠️</div>
                <h2 style="color: #dc3545; margin-bottom: 16px;">Confirm Deletion</h2>
                <p style="color: #6c757d; margin-bottom: 30px;">
                    Are you sure you want to delete this quotation? This action cannot be undone.
                </p>
            </div>

            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                <div style="margin-bottom: 12px;">
                    <strong>Quotation No:</strong> <?php echo htmlspecialchars($quotation['quotation_no']); ?>
                </div>
                <div style="margin-bottom: 12px;">
                    <strong>Title:</strong> <?php echo htmlspecialchars($quotation['title']); ?>
                </div>
                <div style="margin-bottom: 12px;">
                    <strong>Client:</strong> <?php echo htmlspecialchars($quotation['client_name'] ?? 'N/A'); ?>
                </div>
                <div style="margin-bottom: 12px;">
                    <strong>Amount:</strong> ₹<?php echo number_format($quotation['total_amount'], 2); ?>
                </div>
                <div>
                    <strong>Status:</strong> 
                    <span class="badge badge-<?php echo $quotation['status'] === 'Draft' ? 'secondary' : 'info'; ?>">
                        <?php echo htmlspecialchars($quotation['status']); ?>
                    </span>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $quotation_id; ?>">
                <input type="hidden" name="confirm_delete" value="1">
                
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="submit" class="btn btn-danger" style="padding: 12px 40px;">
                        🗑️ Yes, Delete Quotation
                    </button>
                    <a href="view.php?id=<?php echo $quotation_id; ?>" class="btn btn-secondary" style="padding: 12px 40px; text-decoration: none;">
                        ❌ Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
