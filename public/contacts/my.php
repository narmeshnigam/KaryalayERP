<?php
/**
 * Contacts Module - My Contacts
 * View only contacts created by current user
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'contacts', 'view');

// Check if tables exist
if (!contacts_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=contacts');
    exit;
}

// Get filters from query string and force created_by filter
$filters = ['created_by' => $CURRENT_USER_ID];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (isset($_GET['contact_type']) && !empty($_GET['contact_type'])) {
    $filters['contact_type'] = $_GET['contact_type'];
}
if (isset($_GET['tag']) && !empty($_GET['tag'])) {
    $filters['tag'] = $_GET['tag'];
}

// Get contacts
$contacts = get_all_contacts($conn, $CURRENT_USER_ID, $filters);

// Get statistics
$stats = get_contacts_statistics($conn, $CURRENT_USER_ID);

// Get all tags for filter dropdown
$all_tags = get_all_contact_tags($conn);

// Check permissions
$can_create = authz_user_can($conn, 'contacts', 'create');

$page_title = 'My Contacts - Contacts - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">👤 My Contacts</h1>
                    <p style="color: #6c757d; margin: 0;">Contacts created by you</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn btn-primary">➕ Add Contact</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">← All Contacts</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <!-- Filters and Search -->
        <div class="card" style="margin-bottom: 24px;">
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">🔍 Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                           placeholder="Name, email, phone, org..." class="form-control">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">📂 Contact Type</label>
                    <select name="contact_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Client" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Client') ? 'selected' : ''; ?>>Client</option>
                        <option value="Vendor" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Vendor') ? 'selected' : ''; ?>>Vendor</option>
                        <option value="Partner" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Partner') ? 'selected' : ''; ?>>Partner</option>
                        <option value="Personal" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Personal') ? 'selected' : ''; ?>>Personal</option>
                        <option value="Other" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">🏷️ Tag</label>
                    <select name="tag" class="form-control">
                        <option value="">All Tags</option>
                        <?php foreach ($all_tags as $tag): ?>
                            <option value="<?php echo htmlspecialchars($tag); ?>" 
                                    <?php echo (isset($_GET['tag']) && $_GET['tag'] === $tag) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tag); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 8px; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Apply Filters</button>
                    <a href="my.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Contacts Grid -->
        <?php if (empty($contacts)): ?>
            <div class="card">
                <div style="text-align: center; padding: 48px; color: #6c757d;">
                    <div style="font-size: 64px; margin-bottom: 16px;">📇</div>
                    <h3 style="color: #495057; margin-bottom: 8px;">No Contacts Found</h3>
                    <p>You haven't created any contacts yet or no contacts match your filters.</p>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn btn-primary" style="margin-top: 16px;">➕ Add First Contact</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;">
                <?php foreach ($contacts as $contact): ?>
                    <div class="card" style="position: relative; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;"
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 16px rgba(0,0,0,0.15)';"
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)';"
                         onclick="window.location.href='view.php?id=<?php echo $contact['id']; ?>'">
                        
                        <!-- Share Scope Badge -->
                        <div style="position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.9); padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                            <?php echo get_share_scope_icon($contact['share_scope']); ?> <?php echo $contact['share_scope']; ?>
                        </div>
                        
                        <!-- Avatar and Name -->
                        <div style="display: flex; align-items: start; gap: 16px; margin-bottom: 16px;">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #003581 0%, #0066cc 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 20px; flex-shrink: 0;">
                                <?php echo get_contact_initials($contact['name']); ?>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h3 style="margin: 0 0 4px 0; color: #1b2a57; font-size: 18px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($contact['name']); ?>
                                </h3>
                                <?php if (!empty($contact['designation'])): ?>
                                    <div style="color: #6c757d; font-size: 13px; margin-bottom: 2px;">
                                        <?php echo htmlspecialchars($contact['designation']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($contact['organization'])): ?>
                                    <div style="color: #003581; font-size: 13px; font-weight: 600;">
                                        🏢 <?php echo htmlspecialchars($contact['organization']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Contact Type Badge -->
                        <div style="display: inline-block; background: #f8f9fa; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; color: #495057; margin-bottom: 12px;">
                            <?php echo get_contact_type_icon($contact['contact_type']); ?> <?php echo $contact['contact_type']; ?>
                        </div>
                        
                        <!-- Contact Methods -->
                        <div style="border-top: 1px solid #e5e7eb; padding-top: 12px; margin-bottom: 12px;">
                            <?php if (!empty($contact['phone'])): ?>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 14px;">
                                    <span>📞</span>
                                    <a href="tel:<?php echo htmlspecialchars($contact['phone']); ?>" 
                                       style="color: #003581; text-decoration: none;"
                                       onclick="event.stopPropagation();">
                                        <?php echo htmlspecialchars($contact['phone']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($contact['email'])): ?>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <span>✉️</span>
                                    <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" 
                                       style="color: #003581; text-decoration: none;"
                                       onclick="event.stopPropagation();">
                                        <?php echo htmlspecialchars($contact['email']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($contact['whatsapp'])): ?>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 14px;">
                                    <span>💬</span>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $contact['whatsapp']); ?>" 
                                       target="_blank"
                                       style="color: #25D366; text-decoration: none;"
                                       onclick="event.stopPropagation();">
                                        WhatsApp
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Tags -->
                        <?php if (!empty($contact['tags'])): ?>
                            <div style="display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 12px;">
                                <?php 
                                $tags = array_filter(array_map('trim', explode(',', $contact['tags'])));
                                $display_tags = array_slice($tags, 0, 3);
                                foreach ($display_tags as $tag): 
                                ?>
                                    <span style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                        #<?php echo htmlspecialchars($tag); ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($tags) > 3): ?>
                                    <span style="color: #6c757d; font-size: 11px; padding: 2px 8px;">
                                        +<?php echo count($tags) - 3; ?> more
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Footer -->
                        <div style="display: flex; justify-content: flex-end; font-size: 12px; color: #6c757d; border-top: 1px solid #e5e7eb; padding-top: 12px;">
                            <div>
                                📅 <?php echo date('M d, Y', strtotime($contact['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
