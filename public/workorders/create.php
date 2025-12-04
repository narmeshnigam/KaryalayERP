<?php
require_once __DIR__ . '/../../config/config.php';
/**
 * Work Orders Module - Create New Work Order
 */

// Removed auth_check.php include

// Permission checks removed

$page_title = "Create Work Order - " . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch leads
$leads_query = "SELECT id, CONCAT(company_name, ' - ', name) as display_name FROM crm_leads WHERE status != 'Closed' ORDER BY company_name";
$leads_result = mysqli_query($conn, $leads_query);
$leads = [];
if ($leads_result) {
    while ($row = mysqli_fetch_assoc($leads_result)) {
        $leads[] = $row;
    }
}

// Fetch clients
$clients_query = "SELECT id, name as display_name FROM clients WHERE status = 'Active' ORDER BY name";
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

if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
?>

<div class="main-wrapper">
    <div class="main-content">
        <?php echo flash_render(); ?>
        <!-- Page Header -->
        <div class="page-header">
            <div class="wo-header-flex">
                <div>
                    <h1>📋 Create Work Order</h1>
                    <p>Create a new work order with deliverables and team assignments</p>
                </div>
                <div class="wo-header-btn">
                    <a href="index.php" class="btn btn-accent">← Back to List</a>
                </div>
            </div>
        </div>

        <form method="POST" action="api/create.php" enctype="multipart/form-data" id="workOrderForm">
            <!-- Basic Information -->
            <div class="card wo-card">
                <h3 class="wo-section-title">📄 Basic Information</h3>
                <div class="wo-form-grid-3">
                    <div class="form-group">
                        <label>Order Date <span class="wo-required">*</span></label>
                        <input type="date" name="order_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Linked To <span class="wo-required">*</span></label>
                        <select name="linked_type" id="linkedType" class="form-control" required onchange="toggleLinkedOptions()">
                            <option value="">Select Type</option>
                            <option value="Lead">Lead</option>
                            <option value="Client">Client</option>
                        </select>
                    </div>

                    <div class="form-group" id="leadSelectGroup" style="display:none;">
                        <label>Select Lead <span class="wo-required">*</span></label>
                        <select name="linked_id_lead" id="linkedIdLead" class="form-control">
                            <option value="">Select Lead</option>
                            <?php foreach ($leads as $lead): ?>
                                <option value="<?php echo $lead['id']; ?>"><?php echo htmlspecialchars($lead['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="clientSelectGroup" style="display:none;">
                        <label>Select Client <span class="wo-required">*</span></label>
                        <select name="linked_id_client" id="linkedIdClient" class="form-control">
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Service Type <span class="wo-required">*</span></label>
                        <input type="text" name="service_type" class="form-control" placeholder="e.g., Website Development, Consulting" required>
                    </div>

                    <div class="form-group">
                        <label>Priority <span class="wo-required">*</span></label>
                        <select name="priority" class="form-control" required>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                            <option value="High">High</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Draft">Draft</option>
                            <option value="In Progress">In Progress</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date <span class="wo-required">*</span></label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Due Date <span class="wo-required">*</span></label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Internal Approver</label>
                        <select name="internal_approver" class="form-control">
                            <option value="">Select Approver</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group wo-grid-full">
                        <label>Description <span class="wo-required">*</span></label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Detailed work order description" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Dependencies</label>
                        <textarea name="dependencies" class="form-control" rows="2" placeholder="Any dependencies or prerequisites"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Exceptions</label>
                        <textarea name="exceptions" class="form-control" rows="2" placeholder="Any exceptions or special notes"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="General remarks"></textarea>
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
                            <input type="text" name="team_role[]" class="form-control" placeholder="e.g., Project Manager">
                        </div>
                        <div class="form-group">
                            <label>Remarks</label>
                            <input type="text" name="team_remarks[]" class="form-control" placeholder="Optional notes">
                        </div>
                        <div class="wo-team-actions">
                            <button type="button" class="btn btn-small btn-accent" onclick="removeTeamRow(this)" style="visibility:hidden;">Remove</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deliverables -->
            <div class="card wo-card">
                <div class="wo-section-header">
                    <h3 class="wo-section-title">📦 Deliverables <span class="wo-required">*</span></h3>
                    <button type="button" class="btn btn-small" onclick="addDeliverable()">+ Add Deliverable</button>
                </div>
                <div id="deliverablesContainer">
                    <div class="wo-deliverable-row">
                        <div class="wo-deliverable-grid">
                            <div class="form-group">
                                <label>Deliverable Name <span class="wo-required">*</span></label>
                                <input type="text" name="deliverable_name[]" class="form-control" placeholder="e.g., Homepage Design" required>
                            </div>
                            <div class="form-group">
                                <label>Assigned To <span class="wo-required">*</span></label>
                                <select name="deliverable_assigned[]" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['display_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Start Date <span class="wo-required">*</span></label>
                                <input type="date" name="deliverable_start[]" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Due Date <span class="wo-required">*</span></label>
                                <input type="date" name="deliverable_due[]" class="form-control" required>
                            </div>
                            <div class="form-group wo-grid-full">
                                <label>Description</label>
                                <textarea name="deliverable_desc[]" class="form-control" rows="2" placeholder="Brief description of the deliverable"></textarea>
                            </div>
                        </div>
                        <div class="wo-deliverable-actions">
                            <button type="button" class="btn btn-small btn-accent" onclick="removeDeliverableRow(this)" style="visibility:hidden;">Remove</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Uploads -->
            <div class="card wo-card">
                <h3 class="wo-section-title">📎 Attachments</h3>
                <div class="form-group">
                    <label>Upload Files (Max 10MB each)</label>
                    <input type="file" name="files[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                    <small class="form-text">Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP</small>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="wo-button-group">
                <button type="submit" class="btn">💾 Create Work Order</button>
                <a href="index.php" class="btn btn-accent">Cancel</a>
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
    template.querySelectorAll('select, input').forEach(el => el.value = '');
    template.querySelector('.btn-accent').style.visibility = 'visible';
    container.appendChild(template);
}

function removeTeamRow(btn) {
    btn.closest('.wo-team-row').remove();
}

function addDeliverable() {
    const container = document.getElementById('deliverablesContainer');
    const template = container.querySelector('.wo-deliverable-row').cloneNode(true);
    template.querySelectorAll('select, input, textarea').forEach(el => el.value = '');
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
</script>

<?php require_once __DIR__ . '/../../includes/footer_sidebar.php'; ?>
