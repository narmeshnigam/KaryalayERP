<?php
/**
 * Payments Module - Edit Payment
 * Edit payment details (not allocations)
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

// Get payment ID
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($payment_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch payment details
$payment = get_payment_by_id($conn, $payment_id);
if (!$payment) {
    header('Location: index.php');
    exit;
}

// Get all clients for dropdown
$clients = [];
$sql = "SELECT id, name FROM clients WHERE status = 'active' ORDER BY name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
}

// Page title
$page_title = "Edit Payment - " . $payment['payment_no'];
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.form-section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}
.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}
.form-control:focus {
    outline: none;
    border-color: #003581;
    box-shadow: 0 0 0 3px rgba(0, 53, 129, 0.1);
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.info-box {
    background: #e3f2fd;
    border-left: 4px solid #003581;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.file-info {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 13px;
}
.file-info a {
    color: #003581;
    text-decoration: none;
    font-weight: 600;
}
.file-info a:hover {
    text-decoration: underline;
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h1>✏️ Edit Payment</h1>
          <p>Update payment details - <?php echo htmlspecialchars($payment['payment_no']); ?></p>
        </div>
        <div>
          <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-accent" style="text-decoration: none;">← Back to Payment</a>
        </div>
      </div>
    </div>

    <div class="info-box">
        <strong>📌 Note:</strong> You can only edit payment details here. To modify invoice allocations, please visit the payment view page.
    </div>

    <div id="errorContainer" class="alert alert-error" style="display: none;"></div>

    <form id="editPaymentForm" enctype="multipart/form-data">
        <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">

        <div class="form-section">
            <h3 style="color: #003581; margin-bottom: 20px; border-bottom: 2px solid #003581; padding-bottom: 10px;">💰 Payment Information</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" 
                           name="payment_date" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($payment['payment_date']); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Client *</label>
                    <select name="client_id" class="form-control" required>
                        <option value="">-- Select Client --</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" 
                                    <?php echo $client['id'] == $payment['client_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Payment Mode *</label>
                    <select name="payment_mode" class="form-control" required>
                        <option value="cash" <?php echo $payment['payment_mode'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="cheque" <?php echo $payment['payment_mode'] == 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                        <option value="online" <?php echo $payment['payment_mode'] == 'online' ? 'selected' : ''; ?>>Online Transfer</option>
                        <option value="bank_transfer" <?php echo $payment['payment_mode'] == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Reference Number</label>
                    <input type="text" 
                           name="reference_no" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($payment['reference_no'] ?? ''); ?>"
                           placeholder="Cheque/Transaction ID">
                </div>
            </div>

            <div class="form-group">
                <label>Amount Received *</label>
                <input type="number" 
                       name="amount_received" 
                       class="form-control" 
                       step="0.01" 
                       min="0.01"
                       value="<?php echo htmlspecialchars($payment['amount_received']); ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" 
                          class="form-control" 
                          rows="3" 
                          placeholder="Additional notes"><?php echo htmlspecialchars($payment['remarks'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Attachment (PDF, JPG, PNG - Max 5MB)</label>
                <input type="file" 
                       name="attachment" 
                       class="form-control" 
                       accept=".pdf,.jpg,.jpeg,.png">
                
                <?php if (!empty($payment['attachment_path'])): ?>
                <div class="file-info">
                    <strong>Current File:</strong> 
                    <a href="<?php echo htmlspecialchars($payment['attachment_path']); ?>" target="_blank">
                        View Attachment
                    </a>
                    <br>
                    <small style="color: #666;">Upload a new file to replace</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 16px; border-top: 2px solid #e9ecef;">
            <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-accent" style="text-decoration: none;">← Cancel</a>
            <button type="submit" class="btn">💾 Update Payment</button>
        </div>
    </form>
  </div>
</div>

<script>
function showError(message) {
    const errorContainer = document.getElementById('errorContainer');
    errorContainer.textContent = message;
    errorContainer.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function hideError() {
    const errorContainer = document.getElementById('errorContainer');
    errorContainer.style.display = 'none';
}

document.getElementById('editPaymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    hideError();

    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Updating...';

    try {
        const response = await fetch('../api/payments/update.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            window.location.href = 'view.php?id=<?php echo $payment_id; ?>&updated=1';
        } else {
            showError(result.message || 'Failed to update payment');
            submitBtn.disabled = false;
            submitBtn.textContent = '💾 Update Payment';
        }
    } catch (error) {
        showError('An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.textContent = '💾 Update Payment';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
