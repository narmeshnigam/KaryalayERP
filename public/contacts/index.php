<?php
/**
 * Contacts Module - Main Listing Page
 * View all accessible contacts with filters and search
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

authz_require_permission($conn, 'contacts', 'view');

// Check if tables exist
if (!contacts_tables_exist($conn)) {
    header('Location: /KaryalayERP/setup/index.php?module=contacts');
    exit;
}

// Get filters from query string
$filters = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (isset($_GET['contact_type']) && !empty($_GET['contact_type'])) {
    $filters['contact_type'] = $_GET['contact_type'];
}
if (isset($_GET['tag']) && !empty($_GET['tag'])) {
    $filters['tag'] = $_GET['tag'];
}
if (isset($_GET['share_scope']) && !empty($_GET['share_scope'])) {
    $filters['share_scope'] = $_GET['share_scope'];
}

// Get contacts
$contacts = get_all_contacts($conn, $CURRENT_USER_ID, $filters);

// Get statistics
$stats = get_contacts_statistics($conn, $CURRENT_USER_ID);

// Get all tags for filter dropdown
$all_tags = get_all_contact_tags($conn);

// Check permissions
$can_create = authz_user_can($conn, 'contacts', 'create');
$can_export = authz_user_can($conn, 'contacts', 'export');

$page_title = 'All Contacts - Contacts - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 8px 0;">📇 Contacts Management</h1>
                    <p style="color: #6c757d; margin: 0;">Manage and organize your business contacts</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php if ($can_export): ?>
                        <a href="export.php?<?php echo http_build_query($filters); ?>" class="btn btn-secondary">📤 Export</a>
                    <?php endif; ?>
                    <a href="import.php" class="btn btn-secondary">📥 Import</a>
                    <a href="groups.php" class="btn btn-secondary">👥 Groups</a>
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn btn-primary">➕ Add Contact</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e3f2fd;">📊</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Contacts</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #f3e5f5;">👤</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['my_contacts']; ?></div>
                    <div class="stat-label">My Contacts</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #e8f5e9;">🤝</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['shared']; ?></div>
                    <div class="stat-label">Shared With Me</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #fff3e0;">💼</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['clients']; ?></div>
                    <div class="stat-label">Clients</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #fce4ec;">🏢</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['vendors']; ?></div>
                    <div class="stat-label">Vendors</div>
                </div>
            </div>
        </div>

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
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1b2a57;">👁️ Share Scope</label>
                    <select name="share_scope" class="form-control">
                        <option value="">All Scopes</option>
                        <option value="Private" <?php echo (isset($_GET['share_scope']) && $_GET['share_scope'] === 'Private') ? 'selected' : ''; ?>>🔒 Private</option>
                        <option value="Team" <?php echo (isset($_GET['share_scope']) && $_GET['share_scope'] === 'Team') ? 'selected' : ''; ?>>👥 Team</option>
                        <option value="Organization" <?php echo (isset($_GET['share_scope']) && $_GET['share_scope'] === 'Organization') ? 'selected' : ''; ?>>🌐 Organization</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 8px; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Apply Filters</button>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Quick Links -->
        <div style="display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;">
            <a href="index.php" class="btn <?php echo empty($filters) ? 'btn-primary' : 'btn-secondary'; ?>" style="font-size: 14px;">
                📊 All Contacts (<?php echo $stats['total']; ?>)
            </a>
            <a href="my.php" class="btn btn-secondary" style="font-size: 14px;">
                👤 My Contacts (<?php echo $stats['my_contacts']; ?>)
            </a>
            <a href="?contact_type=Client" class="btn btn-secondary" style="font-size: 14px;">
                💼 Clients (<?php echo $stats['clients']; ?>)
            </a>
            <a href="?contact_type=Vendor" class="btn btn-secondary" style="font-size: 14px;">
                🏢 Vendors (<?php echo $stats['vendors']; ?>)
            </a>
        </div>

        <!-- Contacts Grid -->
        <?php if (empty($contacts)): ?>
            <div class="card">
                <div style="text-align: center; padding: 48px; color: #6c757d;">
                    <div style="font-size: 64px; margin-bottom: 16px;">📇</div>
                    <h3 style="color: #495057; margin-bottom: 8px;">No Contacts Found</h3>
                    <p>Start by adding your first contact or adjust your filters.</p>
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
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #6c757d; border-top: 1px solid #e5e7eb; padding-top: 12px;">
                            <div>
                                👤 <?php echo htmlspecialchars($contact['created_by_username']); ?>
                            </div>
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
