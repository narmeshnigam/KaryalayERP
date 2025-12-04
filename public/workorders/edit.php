<?php
require_once __DIR__ . '/../../config/config.php';
/**
 * Work Orders Module - Edit Work Order
 */

// Removed auth_check.php include

// Permission checks removed

$work_order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$work_order_id) {
    header('Location: index.php');
    exit;
}

$page_title = "Edit Work Order - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch work order details
$query = "SELECT * FROM work_orders WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $work_order_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$work_order = mysqli_fetch_assoc($result);

if (!$work_order) {
    header('Location: index.php');
    exit;
}

// Fetch leads
$leads_query = "SELECT id, CONCAT(COALESCE(company_name, name), ' - ', name) as display_name FROM crm_leads WHERE status != 'Closed' ORDER BY COALESCE(company_name, name)";
$leads_result = mysqli_query($conn, $leads_query);
$leads = [];
if ($leads_result) {
    while ($row = mysqli_fetch_assoc($leads_result)) {
        $leads[] = $row;
    }
}

// Fetch clients
$clients_query = "SELECT id, CONCAT(COALESCE(legal_name, name), ' - ', name) as display_name FROM clients WHERE status = 'Active' ORDER BY COALESCE(legal_name, name)";
$clients_result = mysqli_query($conn, $clients_query);
$clients = [];
if ($clients_result) {
    while ($row = mysqli_fetch_assoc($clients_result)) {
        $clients[] = $row;
    }
}

// Fetch employees
$employees_query = "SELECT id, CONCAT(first_name, ' ', last_name, ' (', employee_code, ')') as display_name FROM employees WHERE status = 'Active' ORDER BY first_name";
$employees_result = mysqli_query($conn, $employees_query);
$employees = [];
if ($employees_result) {
    while ($row = mysqli_fetch_assoc($employees_result)) {
        $employees[] = $row;
    }
}

// Fetch users for approval
$users_query = "SELECT id, username FROM users WHERE status = 'active' ORDER BY username";
$users_result = mysqli_query($conn, $users_query);
$users = [];
if ($users_result) {
    while ($row = mysqli_fetch_assoc($users_result)) {
        $users[] = $row;
    }
}

// Fetch team members
$team_query = "SELECT * FROM work_order_team WHERE work_order_id = ? ORDER BY id";
$team_stmt = mysqli_prepare($conn, $team_query);
mysqli_stmt_bind_param($team_stmt, 'i', $work_order_id);
mysqli_stmt_execute($team_stmt);
$team_result = mysqli_stmt_get_result($team_stmt);
$team_members = [];
while ($row = mysqli_fetch_assoc($team_result)) {
    $team_members[] = $row;
}

// Fetch deliverables
$deliv_query = "SELECT * FROM work_order_deliverables WHERE work_order_id = ? ORDER BY due_date";
$deliv_stmt = mysqli_prepare($conn, $deliv_query);
mysqli_stmt_bind_param($deliv_stmt, 'i', $work_order_id);
mysqli_stmt_execute($deliv_stmt);
$deliv_result = mysqli_stmt_get_result($deliv_stmt);
$deliverables = [];
while ($row = mysqli_fetch_assoc($deliv_result)) {
    $deliverables[] = $row;
}

// Fetch files
$files_query = "SELECT wof.*, u.username as uploaded_by_name
                FROM work_order_files wof
                LEFT JOIN users u ON wof.uploaded_by = u.id
                WHERE wof.work_order_id = ?
                ORDER BY wof.created_at DESC";
$files_stmt = mysqli_prepare($conn, $files_query);
mysqli_stmt_bind_param($files_stmt, 'i', $work_order_id);
mysqli_stmt_execute($files_stmt);
$files_result = mysqli_stmt_get_result($files_stmt);
$files = [];
while ($row = mysqli_fetch_assoc($files_result)) {
    $files[] = $row;
}

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="wo-header-flex">
                <div>
                    <h1>✏️ Edit Work Order</h1>
                    <p><?php echo htmlspecialchars($work_order['work_order_code']); ?></p>
                </div>
                <div class="wo-header-btn">
                    <a href="view.php?id=<?php echo $work_order_id; ?>" class="btn btn-accent">← Back to View</a>
                </div>
            </div>
        </div>

        <form method="POST" action="api/update.php" enctype="multipart/form-data" id="workOrderEditForm">
            <input type="hidden" name="work_order_id" value="<?php echo $work_order_id; ?>">
            
            <!-- Basic Information -->
            <div class="card wo-card">
                <h3 class="wo-section-title">📄 Basic Information</h3>
                <div class="wo-form-grid-3">
                    <div class="form-group">
                        <label>Work Order Code</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($work_order['work_order_code']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Order Date <span class="wo-required">*</span></label>
                        <input type="date" name="order_date" class="form-control" value="<?php echo $work_order['order_date']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Linked To <span class="wo-required">*</span></label>
                        <select name="linked_type" id="linkedType" class="form-control" required onchange="toggleLinkedOptions()">
                            <option value="">Select Type</option>
                            <option value="Lead" <?php echo $work_order['linked_type'] === 'Lead' ? 'selected' : ''; ?>>Lead</option>
                            <option value="Client" <?php echo $work_order['linked_type'] === 'Client' ? 'selected' : ''; ?>>Client</option>
                        </select>
                    </div>

                    <div class="form-group" id="leadSelectGroup" style="display:<?php echo $work_order['linked_type'] === 'Lead' ? 'block' : 'none'; ?>;">
                        <label>Select Lead <span class="wo-required">*</span></label>
                        <select name="linked_id_lead" id="linkedIdLead" class="form-control">
                            <option value="">Select Lead</option>
                            <?php foreach ($leads as $lead): ?>
                                <option value="<?php echo $lead['id']; ?>" <?php echo $work_order['linked_type'] === 'Lead' && $work_order['linked_id'] == $lead['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lead['display_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="clientSelectGroup" style="display:<?php echo $work_order['linked_type'] === 'Client' ? 'block' : 'none'; ?>;">
                        <label>Select Client <span class="wo-required">*</span></label>
                        <select name="linked_id_client" id="linkedIdClient" class="form-control">
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $work_order['linked_type'] === 'Client' && $work_order['linked_id'] == $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['display_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Service Type <span class="wo-required">*</span></label>
                        <input type="text" name="service_type" class="form-control" value="<?php echo htmlspecialchars($work_order['service_type']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Priority <span class="wo-required">*</span></label>
                        <select name="priority" class="form-control" required>
                            <option value="Low" <?php echo $work_order['priority'] === 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo $work_order['priority'] === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo $work_order['priority'] === 'High' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status <span class="wo-required">*</span></label>
                        <select name="status" class="form-control" required>
                            <option value="Draft" <?php echo $work_order['status'] === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="In Progress" <?php echo $work_order['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="On Hold" <?php echo $work_order['status'] === 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                            <option value="Completed" <?php echo $work_order['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $work_order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date <span class="wo-required">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $work_order['start_date']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Due Date <span class="wo-required">*</span></label>
                        <input type="date" name="due_date" class="form-control" value="<?php echo $work_order['due_date']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Internal Approver</label>
                        <select name="internal_approver" class="form-control">
                            <option value="">Select Approver</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $work_order['internal_approver'] == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group wo-grid-full">
                        <label>Description <span class="wo-required">*</span></label>
                        <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($work_order['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Dependencies</label>
                        <textarea name="dependencies" class="form-control" rows="2"><?php echo htmlspecialchars($work_order['dependencies'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Exceptions</label>
                        <textarea name="exceptions" class="form-control" rows="2"><?php echo htmlspecialchars($work_order['exceptions'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"><?php echo htmlspecialchars($work_order['remarks'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Team Members -->
            <div class="card wo-card">
                <div class="wo-section-header">
                    <h3 class="wo-section-title">👥 Team Members</h3>
                    <button type="button" class="btn btn-small" onclick="addTeamMember()">+ Add Member</button>
                </div>
                <div id="teamMembersContainer">
                    <?php if (empty($team_members)): ?>
                        <div class="wo-team-row">
                            <div class="form-group">
                                <label>Employee</label>
                                <select name="team_employee[]" class="form-control">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['display_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Role/Responsibility</label>
                                <input type="text" name="team_role[]" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Remarks</label>
                                <input type="text" name="team_remarks[]" class="form-control">
                            </div>
                            <input type="hidden" name="team_id[]" value="">
                            <div class="wo-team-actions">
                                <button type="button" class="btn btn-small btn-accent" onclick="removeTeamRow(this)" style="visibility:hidden;">Remove</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($team_members as $index => $member): ?>
                            <div class="wo-team-row">
                                <div class="form-group">
                                    <label>Employee</label>
                                    <select name="team_employee[]" class="form-control">
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>" <?php echo $member['employee_id'] == $emp['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($emp['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Role/Responsibility</label>
                                    <input type="text" name="team_role[]" class="form-control" value="<?php echo htmlspecialchars($member['role'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Remarks</label>
                                    <input type="text" name="team_remarks[]" class="form-control" value="<?php echo htmlspecialchars($member['remarks'] ?? ''); ?>">
                                </div>
                                <input type="hidden" name="team_id[]" value="<?php echo $member['id']; ?>">
                                <div class="wo-team-actions">
                                    <button type="button" class="btn btn-small btn-accent" onclick="removeTeamRow(this)" style="visibility:<?php echo $index === 0 ? 'hidden' : 'visible'; ?>;">Remove</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deliverables -->
            <div class="card wo-card">
                <div class="wo-section-header">
                    <h3 class="wo-section-title">📦 Deliverables <span class="wo-required">*</span></h3>
                    <button type="button" class="btn btn-small" onclick="addDeliverable()">+ Add Deliverable</button>
                </div>
                <div id="deliverablesContainer">
                    <?php foreach ($deliverables as $index => $deliv): ?>
                        <div class="wo-deliverable-row">
                            <div class="wo-deliverable-grid">
                                <div class="form-group">
                                    <label>Deliverable Name <span class="wo-required">*</span></label>
                                    <input type="text" name="deliverable_name[]" class="form-control" value="<?php echo htmlspecialchars($deliv['deliverable_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Assigned To <span class="wo-required">*</span></label>
                                    <select name="deliverable_assigned[]" class="form-control" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>" <?php echo $deliv['assigned_to'] == $emp['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($emp['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="delivery_status[]" class="form-control">
                                        <option value="Pending" <?php echo $deliv['delivery_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="In Progress" <?php echo $deliv['delivery_status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="Ready" <?php echo $deliv['delivery_status'] === 'Ready' ? 'selected' : ''; ?>>Ready</option>
                                        <option value="Delivered" <?php echo $deliv['delivery_status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Start Date <span class="wo-required">*</span></label>
                                    <input type="date" name="deliverable_start[]" class="form-control" value="<?php echo $deliv['start_date']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Due Date <span class="wo-required">*</span></label>
                                    <input type="date" name="deliverable_due[]" class="form-control" value="<?php echo $deliv['due_date']; ?>" required>
                                </div>
                                <div class="form-group wo-grid-full">
                                    <label>Description</label>
                                    <textarea name="deliverable_desc[]" class="form-control" rows="2"><?php echo htmlspecialchars($deliv['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <input type="hidden" name="deliverable_id[]" value="<?php echo $deliv['id']; ?>">
                            <div class="wo-deliverable-actions">
                                <button type="button" class="btn btn-small btn-accent" onclick="removeDeliverableRow(this)" style="visibility:<?php echo $index === 0 && count($deliverables) === 1 ? 'hidden' : 'visible'; ?>;">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Existing Files -->
            <?php if (!empty($files)): ?>
            <div class="card wo-card">
                <h3 class="wo-section-title">📎 Existing Attachments</h3>
                <div class="wo-files-list">
                    <?php foreach ($files as $file): ?>
                        <div class="wo-file-item">
                            <div class="wo-file-icon">📄</div>
                            <div class="wo-file-info">
                                <a href="../../<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="wo-file-name">
                                    <?php echo htmlspecialchars($file['file_name']); ?>
                                </a>
                                <div class="wo-file-meta">
                                    <small>By <?php echo htmlspecialchars($file['uploaded_by_name']); ?> on <?php echo date('d M Y', strtotime($file['created_at'])); ?></small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-small btn-accent" onclick="deleteFile(<?php echo $file['id']; ?>)">Delete</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Upload New Files -->
            <div class="card wo-card">
                <h3 class="wo-section-title">📎 Upload New Attachments</h3>
                <div class="form-group">
                    <label>Upload Files (Max 10MB each)</label>
                    <input type="file" name="files[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                    <small class="form-text">Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP</small>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="wo-button-group">
                <button type="submit" class="btn">💾 Update Work Order</button>
                <a href="view.php?id=<?php echo $work_order_id; ?>" class="btn btn-accent">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleLinkedOptions() {
    const linkedType = document.getElementById('linkedType').value;
    const leadGroup = document.getElementById('leadSelectGroup');
    const clientGroup = document.getElementById('clientSelectGroup');
    const leadSelect = document.getElementById('linkedIdLead');
    const clientSelect = document.getElementById('linkedIdClient');
    
    if (linkedType === 'Lead') {
        leadGroup.style.display = 'block';
        clientGroup.style.display = 'none';
        leadSelect.required = true;
        clientSelect.required = false;
        clientSelect.value = '';
    } else if (linkedType === 'Client') {
        leadGroup.style.display = 'none';
        clientGroup.style.display = 'block';
        leadSelect.required = false;
        clientSelect.required = true;
        leadSelect.value = '';
    } else {
        leadGroup.style.display = 'none';
        clientGroup.style.display = 'none';
        leadSelect.required = false;
        clientSelect.required = false;
        leadSelect.value = '';
        clientSelect.value = '';
    }
}

function addTeamMember() {
    const container = document.getElementById('teamMembersContainer');
    const template = container.querySelector('.wo-team-row').cloneNode(true);
    template.querySelectorAll('select, input').forEach(el => {
        if (el.name !== 'team_id[]') {
            el.value = '';
        }
    });
    template.querySelector('input[name="team_id[]"]').value = '';
    template.querySelector('.btn-accent').style.visibility = 'visible';
    container.appendChild(template);
}

function removeTeamRow(btn) {
    btn.closest('.wo-team-row').remove();
}

function addDeliverable() {
    const container = document.getElementById('deliverablesContainer');
    const template = container.querySelector('.wo-deliverable-row').cloneNode(true);
    template.querySelectorAll('select, input, textarea').forEach(el => {
        if (el.name !== 'deliverable_id[]') {
            el.value = '';
        }
    });
    template.querySelector('input[name="deliverable_id[]"]').value = '';
    template.querySelector('.btn-accent').style.visibility = 'visible';
    container.appendChild(template);
}

function removeDeliverableRow(btn) {
    const container = document.getElementById('deliverablesContainer');
    if (container.querySelectorAll('.wo-deliverable-row').length > 1) {
        btn.closest('.wo-deliverable-row').remove();
    } else {
        alert('At least one deliverable is required.');
    }
}

function deleteFile(fileId) {
    if (confirm('Are you sure you want to delete this file?')) {
        fetch('api/delete_file.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'file_id=' + fileId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting file: ' + data.message);
            }
        });
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
