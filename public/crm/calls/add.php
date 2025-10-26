<?php
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'employee';
$user_id = (int)$_SESSION['user_id'];

$conn = createConnection(true);
if (!$conn) {
    die('Database connection failed');
}

$current_employee_id = crm_current_employee_id($conn, $user_id);
if (!$current_employee_id && !crm_role_can_manage($user_role)) {
    closeConnection($conn);
    die('Unable to identify your employee record.');
}

$employees = crm_fetch_employees($conn);
$leads = crm_fetch_active_leads($conn);
$outcomes = crm_call_outcomes();
$follow_up_types = crm_lead_follow_up_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int)$_POST['lead_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $call_date = trim($_POST['call_date'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $outcome = trim($_POST['outcome'] ?? '');
    $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : $current_employee_id;
    $location = trim($_POST['location'] ?? '');
    $follow_up_date = trim($_POST['follow_up_date'] ?? '');
    $follow_up_type = trim($_POST['follow_up_type'] ?? '');

    // Validation
    if ($title === '') {
        $errors[] = 'Call title is required.';
    }
    if ($summary === '') {
        $errors[] = 'Call summary is required.';
    }
    if ($call_date === '') {
        $errors[] = 'Call date and time are required.';
    } else {
        $call_dt = DateTime::createFromFormat('Y-m-d\TH:i', $call_date);
        if (!$call_dt || $call_dt > new DateTime()) {
            $errors[] = 'Call date cannot be in the future.';
        }
    }
    if ($outcome === '') {
        $errors[] = 'Call outcome is required.';
    }
    if ($lead_id !== null) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM crm_leads WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $lead_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if (!mysqli_fetch_assoc($res)) {
            $errors[] = 'Selected lead does not exist.';
        }
        mysqli_free_result($res);
        mysqli_stmt_close($stmt);
    }
    if (!crm_employee_exists($conn, $assigned_to)) {
        $errors[] = 'Assigned employee does not exist.';
    }

    // Handle file upload
    $attachment_path = null;
    if (!empty($_FILES['attachment']['name'])) {
        $file = $_FILES['attachment'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > 3 * 1024 * 1024) {
                $errors[] = 'Attachment file size must not exceed 3 MB.';
            } elseif (!in_array($file['type'], crm_allowed_mime_types(), true)) {
                $errors[] = 'Attachment must be PDF, JPEG, or PNG.';
            } else {
                if (crm_ensure_upload_directory()) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'call_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $upload_path = crm_upload_directory() . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $attachment_path = $filename;
                    } else {
                        $errors[] = 'Failed to save attachment.';
                    }
                } else {
                    $errors[] = 'Upload directory is not writable.';
                }
            }
        } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'File upload error: ' . $file['error'];
        }
    }

    if (empty($errors)) {
        // Build dynamic INSERT based on available columns
        $res_cols = mysqli_query($conn, "SHOW COLUMNS FROM crm_calls");
        $existing_cols = [];
        if ($res_cols) {
            while ($r = mysqli_fetch_assoc($res_cols)) {
                $existing_cols[] = $r['Field'];
            }
            mysqli_free_result($res_cols);
        }

        $data = [
            'lead_id' => $lead_id,
            'title' => $title,
            'summary' => $summary,
            'call_date' => $call_date,
            'duration' => $duration !== '' ? $duration : null,
            'outcome' => $outcome,
            'created_by' => $current_employee_id,
            'assigned_to' => $assigned_to,
            'location' => $location !== '' ? $location : null,
            'attachment' => $attachment_path,
        ];

        if (in_array('follow_up_date', $existing_cols, true) && $follow_up_date !== '') {
            $data['follow_up_date'] = $follow_up_date;
        }
        if (in_array('follow_up_type', $existing_cols, true) && $follow_up_type !== '') {
            $data['follow_up_type'] = $follow_up_type;
        }

        $cols = [];
        $vals = [];
        $types = '';
        $params = [];
        foreach ($data as $col => $val) {
            if (in_array($col, $existing_cols, true)) {
                $cols[] = $col;
                $vals[] = '?';
                if (is_int($val)) {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
                $params[] = $val;
            }
        }

        $sql = "INSERT INTO crm_calls (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Update lead's last_contacted_at
                if ($lead_id) {
                    crm_update_lead_contact_time($conn, $lead_id);
                }

                flash_add('success', 'Call logged successfully!', 'crm');
                closeConnection($conn);
                header('Location: view.php?id=' . $new_id);
                exit;
            } else {
                $errors[] = 'Database error: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        } else {
            $errors[] = 'Failed to prepare statement: ' . mysqli_error($conn);
        }
    }
}

$page_title = 'Add Call - CRM - ' . APP_NAME;
require_once __DIR__ . '/../../../includes/header_sidebar.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<style>
.page-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.page-header-top > div:first-child h1 {
    margin: 0 0 4px;
    font-size: 28px;
}
.page-header-top > div:first-child p {
    margin: 0;
    color: #6c757d;
    font-size: 14px;
}
.button-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.form-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}
.card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.card-title {
    font-size: 16px;
    font-weight: 600;
    color: #003581;
    border-bottom: 2px solid #003581;
    padding-bottom: 12px;
    margin-bottom: 20px;
    margin-top: 0;
}
.alert {
    margin-bottom: 20px;
}
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
}
@media (max-width: 768px) {
    .page-header-top {
        flex-direction: column;
        align-items: flex-start;
    }
    .button-group {
        width: 100%;
    }
    .button-group a {
        flex: 1;
        text-align: center;
    }
    .form-grid-3 {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .card {
        padding: 12px;
        margin-bottom: 12px;
    }
    .card-title {
        font-size: 14px;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions a, .form-actions button {
        width: 100%;
    }
}
</style>

<div class="main-wrapper">
  <div class="main-content">
    <div class="page-header-top">
      <div>
        <h1>☎️ Log New Call</h1>
        <p>Record telephonic interaction with lead or client.</p>
      </div>
      <div class="button-group">
        <a class="btn" href="index.php" style="text-decoration: none;">All Calls</a>
        <a class="btn btn-accent" href="my.php" style="text-decoration: none;">My Calls</a>
      </div>
    </div>

    <?php echo flash_render(); ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Please correct the following:</strong>
            <ul style="margin:8px 0 0 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <!-- Call Information -->
        <div class="card">
            <h2 class="card-title">📞 Call Information</h2>
            <div class="form-grid-3">
                <div class="form-group">
                    <label for="lead_id">Related Lead</label>
                    <select id="lead_id" name="lead_id" class="form-control">
                        <option value="">-- Select Lead (Optional) --</option>
                        <?php $prefilled_lead = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : (isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0); ?>
                        <?php foreach ($leads as $l): ?>
                            <option value="<?php echo (int)$l['id']; ?>"
                                <?php echo $prefilled_lead === (int)$l['id'] ? 'selected' : ''; ?>>
                                <?php 
                                    $lbl = htmlspecialchars($l['name']);
                                    if (!empty($l['company_name'])) {
                                        $lbl .= ' (' . htmlspecialchars($l['company_name']) . ')';
                                    }
                                    echo $lbl;
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title">Call Title <span style="color:red;">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                           placeholder="e.g., Follow-up on product demo" required>
                </div>

                <div class="form-group">
                    <label for="call_date">Call Date & Time <span style="color:red;">*</span></label>
                    <input type="datetime-local" id="call_date" name="call_date" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['call_date'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="duration">Duration</label>
                    <input type="text" id="duration" name="duration" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>"
                           placeholder="e.g., 15m, 5m30s">
                </div>

                <div class="form-group">
                    <label for="outcome">Call Outcome <span style="color:red;">*</span></label>
                    <select id="outcome" name="outcome" class="form-control" required>
                        <option value="">-- Select Outcome --</option>
                        <?php foreach ($outcomes as $o): ?>
                            <option value="<?php echo htmlspecialchars($o); ?>"
                                <?php echo (isset($_POST['outcome']) && $_POST['outcome'] === $o) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assigned_to">Assigned To <span style="color:red;">*</span></label>
                    <select id="assigned_to" name="assigned_to" class="form-control" required>
                        <?php foreach ($employees as $emp): ?>
                            <?php
                                $emp_id = (int)$emp['id'];
                                $emp_label = htmlspecialchars(trim($emp['first_name'] . ' ' . $emp['last_name']));
                                $selected = (isset($_POST['assigned_to']) && (int)$_POST['assigned_to'] === $emp_id) 
                                            || (!isset($_POST['assigned_to']) && $emp_id === $current_employee_id);
                            ?>
                            <option value="<?php echo $emp_id; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                <?php echo $emp_label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="summary">Call Summary <span style="color:red;">*</span></label>
                <textarea id="summary" name="summary" class="form-control" rows="4" 
                          placeholder="Brief notes on discussion points and outcomes..." required><?php echo htmlspecialchars($_POST['summary'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="location">Location / GPS</label>
                <input type="text" id="location" name="location" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                       placeholder="Optional location or coordinates">
            </div>
        </div>

        <!-- Follow-Up & Additional Details -->
        <div class="card">
            <h2 class="card-title">📅 Follow-Up & Additional Details</h2>
            <div class="form-grid-3">
                <div class="form-group">
                    <label for="follow_up_date">Follow-Up Date</label>
                    <input type="date" id="follow_up_date" name="follow_up_date" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['follow_up_date'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="follow_up_type">Follow-Up Type</label>
                    <select id="follow_up_type" name="follow_up_type" class="form-control">
                        <option value="">-- None --</option>
                        <?php foreach ($follow_up_types as $ft): ?>
                            <option value="<?php echo htmlspecialchars($ft); ?>"
                                <?php echo (isset($_POST['follow_up_type']) && $_POST['follow_up_type'] === $ft) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ft); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="attachment">Attachment (PDF, JPG, PNG - Max 3MB)</label>
                    <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <a href="index.php" class="btn btn-secondary" style="text-decoration: none;">Cancel</a>
            <button type="submit" class="btn">Log Call</button>
        </div>
    </form>
  </div>
</div>

<?php
closeConnection($conn);
require_once __DIR__ . '/../../../includes/footer_sidebar.php';
?>
