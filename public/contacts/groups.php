<?php
/**
 * Contacts Module - Contact Groups
 * Manage contact groups and assignments
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

if (!authz_user_can_any($conn, [
    ['table' => 'contacts', 'permission' => 'view_all'],
    ['table' => 'contacts', 'permission' => 'view_assigned'],
    ['table' => 'contacts', 'permission' => 'view_own'],
])) {
    authz_require_permission($conn, 'contacts', 'view_all');
}

// Check if tables exist
if (!contacts_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=contacts');
    exit;
}

$errors = [];

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name)) {
        $errors[] = 'Group name is required';
    } else {
        $group_id = create_contact_group($conn, $name, $description, $CURRENT_USER_ID);
        if ($group_id) {
            $_SESSION['flash_success'] = 'Contact group created successfully!';
            header('Location: groups.php');
            exit;
        } else {
            $errors[] = 'Failed to create group. Please try again.';
        }
    }
}

// Handle group deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_group') {
    $group_id = (int)$_POST['group_id'];
    if (delete_contact_group($conn, $group_id, $CURRENT_USER_ID)) {
        $_SESSION['flash_success'] = 'Contact group deleted successfully!';
    } else {
        $_SESSION['flash_error'] = 'Failed to delete group or permission denied.';
    }
    header('Location: groups.php');
    exit;
}

// Get all groups
$groups = get_all_contact_groups($conn);

// Get selected group contacts
$selected_group_id = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$group_contacts = [];
if ($selected_group_id > 0) {
    $group_contacts = get_group_contacts($conn, $selected_group_id, $CURRENT_USER_ID);
}

$page_title = 'Contact Groups - Contacts - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
<style>
.contacts-groups-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.contacts-groups-main-grid{display:grid;grid-template-columns:350px 1fr;gap:24px;}

@media (max-width:768px){
.contacts-groups-header-flex{flex-direction:column;align-items:stretch;}
.contacts-groups-header-flex .btn{width:100%;text-align:center;}
.contacts-groups-main-grid{grid-template-columns:1fr;}
}

@media (max-width:480px){
.contacts-groups-header-flex h1{font-size:1.5rem;}
}
</style>

        <!-- Page Header -->
        <div class="page-header">
            <div class="contacts-groups-header-flex">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">👥 Contact Groups</h1>
                    <p style="color: #6c757d; margin: 0;">Organize contacts into groups for easy management</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary">← Back to Contacts</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <!-- Error Display -->
        <?php if (!empty($errors)): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <strong>⚠️ Errors:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="contacts-groups-main-grid">
            
            <!-- Groups List -->
            <div>
                <!-- Create Group Form -->
                <div class="card" style="margin-bottom: 24px;">
                    <h3 style="margin: 0 0 16px 0; color: #1b2a57; font-size: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                        ➕ Create New Group
                    </h3>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_group">
                        
                        <div style="margin-bottom: 12px;">
                            <label class="form-label required">Group Name</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g., Vendors, Investors">
                        </div>
                        
                        <div style="margin-bottom: 16px;">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Optional description"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            ✅ Create Group
                        </button>
                    </form>
                </div>
                
                <!-- Groups List -->
                <div class="card">
                    <h3 style="margin: 0 0 16px 0; color: #1b2a57; font-size: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                        📂 All Groups (<?php echo count($groups); ?>)
                    </h3>
                    
                    <?php if (empty($groups)): ?>
                        <div style="text-align: center; padding: 24px; color: #6c757d; font-size: 14px;">
                            No groups created yet
                        </div>
                    <?php else: ?>
                        <div style="display: grid; gap: 8px;">
                            <?php foreach ($groups as $group): ?>
                                <a href="?group=<?php echo $group['id']; ?>" 
                                   style="display: block; padding: 12px; background: <?php echo ($selected_group_id == $group['id']) ? '#e3f2fd' : '#f8f9fa'; ?>; border-radius: 8px; text-decoration: none; border: 2px solid <?php echo ($selected_group_id == $group['id']) ? '#1976d2' : 'transparent'; ?>; transition: all 0.2s;"
                                   onmouseover="if(<?php echo $selected_group_id != $group['id'] ? 'true' : 'false'; ?>) this.style.background='#f0f0f0'"
                                   onmouseout="if(<?php echo $selected_group_id != $group['id'] ? 'true' : 'false'; ?>) this.style.background='#f8f9fa'">
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                        <div style="font-weight: 700; color: #1b2a57; font-size: 14px; flex: 1;">
                                            <?php echo htmlspecialchars($group['name']); ?>
                                        </div>
                                        <div style="background: #003581; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                            <?php echo $group['contact_count']; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($group['description'])): ?>
                                        <div style="font-size: 12px; color: #6c757d; margin-bottom: 6px;">
                                            <?php echo htmlspecialchars(substr($group['description'], 0, 60)); ?>
                                            <?php echo strlen($group['description']) > 60 ? '...' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="font-size: 11px; color: #6c757d;">
                                        by <?php echo htmlspecialchars($group['created_by_username']); ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Group Contacts -->
            <div>
                <?php if ($selected_group_id > 0): ?>
                    <?php 
                    $current_group = null;
                    foreach ($groups as $g) {
                        if ($g['id'] == $selected_group_id) {
                            $current_group = $g;
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($current_group): ?>
                        <div class="card" style="margin-bottom: 24px;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <h2 style="margin: 0 0 8px 0; color: #1b2a57;">
                                        <?php echo htmlspecialchars($current_group['name']); ?>
                                    </h2>
                                    <?php if (!empty($current_group['description'])): ?>
                                        <p style="color: #6c757d; margin: 0; font-size: 14px;">
                                            <?php echo htmlspecialchars($current_group['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($current_group['created_by'] == $CURRENT_USER_ID || $IS_SUPER_ADMIN): ?>
                                    <form method="POST" action="" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this group? Contacts will not be deleted.');">
                                        <input type="hidden" name="action" value="delete_group">
                                        <input type="hidden" name="group_id" value="<?php echo $current_group['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="font-size: 14px;">🗑️ Delete Group</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (empty($group_contacts)): ?>
                            <div class="card">
                                <div style="text-align: center; padding: 48px; color: #6c757d;">
                                    <div style="font-size: 64px; margin-bottom: 16px;">📭</div>
                                    <h3 style="color: #495057; margin-bottom: 8px;">No Contacts in Group</h3>
                                    <p>This group doesn't have any contacts yet.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                                <?php foreach ($group_contacts as $contact): ?>
                                    <div class="card" style="cursor: pointer; transition: transform 0.2s;"
                                         onmouseover="this.style.transform='translateY(-4px)'"
                                         onmouseout="this.style.transform='translateY(0)'"
                                         onclick="window.location.href='view.php?id=<?php echo $contact['id']; ?>'">
                                        
                                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                            <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #003581 0%, #0066cc 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 16px;">
                                                <?php echo get_contact_initials($contact['name']); ?>
                                            </div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="font-weight: 700; color: #1b2a57; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <?php echo htmlspecialchars($contact['name']); ?>
                                                </div>
                                                <?php if (!empty($contact['organization'])): ?>
                                                    <div style="font-size: 12px; color: #6c757d;">
                                                        <?php echo htmlspecialchars($contact['organization']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div style="font-size: 12px; color: #495057; border-top: 1px solid #e5e7eb; padding-top: 8px;">
                                            <?php if (!empty($contact['phone'])): ?>
                                                <div style="margin-bottom: 4px;">📞 <?php echo htmlspecialchars($contact['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($contact['email'])): ?>
                                                <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">✉️ <?php echo htmlspecialchars($contact['email']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="card">
                        <div style="text-align: center; padding: 64px; color: #6c757d;">
                            <div style="font-size: 80px; margin-bottom: 20px;">👥</div>
                            <h3 style="color: #495057; margin-bottom: 12px;">Select a Group</h3>
                            <p style="font-size: 15px;">Choose a group from the left sidebar to view its contacts</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
