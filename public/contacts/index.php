<?php
/**
 * Contacts Module - Main Listing Page
 * View all accessible contacts with filters and search
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
    $page_title = 'Contacts Module Setup Required - ' . APP_NAME;
    require_once __DIR__ . '/../../includes/header_sidebar.php';
    require_once __DIR__ . '/../../includes/sidebar.php';
    echo '<div class="main-wrapper"><div class="main-content">';
    echo '<div class="alert alert-warning" style="margin:40px auto;max-width:600px;padding:32px 28px;font-size:1.1em;">';
    echo '<h2 style="margin-bottom:16px;color:#b85c00;">Contacts Module Not Set Up</h2>';
    echo '<p>The contacts module database tables have not been created yet. To use this module, please run the setup for contacts.</p>';
    echo '<a href="' . APP_URL . '/scripts/setup_contacts_tables.php" class="btn btn-primary" style="margin-top:18px;">Set Up Contacts Module</a>';
    echo '</div></div></div>';
    require_once __DIR__ . '/../../includes/footer.php';
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
<style>
.contacts-header-flex{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.contacts-header-buttons{display:flex;gap:8px;flex-wrap:wrap;}

@media (max-width:768px){
.contacts-header-flex{flex-direction:column;align-items:stretch;}
.contacts-header-buttons{width:100%;flex-direction:column;gap:10px;}
.contacts-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.contacts-header-flex h1{font-size:1.5rem;}
}

/* Statistics Cards Responsive */
.contacts-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;}

@media (max-width:768px){
.contacts-stats-grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;}
}

@media (max-width:480px){
.contacts-stats-grid{grid-template-columns:1fr;gap:12px;}
.contacts-stats-grid .card{padding:16px !important;}
.contacts-stats-grid .card>div:first-child{font-size:28px !important;}
}

/* Filter Form Responsive */
.contacts-filter-form{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:16px;}

@media (max-width:1024px){
.contacts-filter-form{grid-template-columns:1fr 1fr 1fr auto;gap:12px;}
}

@media (max-width:768px){
.contacts-filter-form{grid-template-columns:1fr 1fr;gap:12px;}
.contacts-filter-form>div:nth-child(4){grid-column:1/2;}
.contacts-filter-form>div:nth-child(5){grid-column:2/3;}
}

@media (max-width:480px){
.contacts-filter-form{grid-template-columns:1fr;gap:12px;}
.contacts-filter-form>div:nth-child(4){grid-column:1/-1;}
.contacts-filter-form>div:nth-child(5){grid-column:1/-1;}
.contacts-filter-buttons{grid-column:1/-1 !important;display:flex;gap:8px;}
.contacts-filter-buttons button,.contacts-filter-buttons a{flex:1;}
}

/* Quick Links Responsive */
.contacts-quick-links{display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;}

@media (max-width:768px){
.contacts-quick-links .btn{font-size:13px;padding:8px 12px;}
}

@media (max-width:480px){
.contacts-quick-links{gap:8px;}
.contacts-quick-links .btn{font-size:12px;padding:6px 10px;flex:1 1 calc(50% - 4px);}
}

/* Contact Cards Responsive */
.contacts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;}

@media (max-width:1024px){
.contacts-grid{grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;}
}

@media (max-width:768px){
.contacts-grid{grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;}
}

@media (max-width:480px){
.contacts-grid{grid-template-columns:1fr;gap:12px;}
.contacts-card-avatar{width:50px !important;height:50px !important;font-size:18px !important;}
.contacts-card-name{font-size:16px !important;}
}
</style>

        <!-- Page Header -->
        <div class="page-header">
            <div class="contacts-header-flex">
                <div style="flex: 1;">
                    <h1>📇 Contacts Management</h1>
                    <p>Manage and organize your business contacts</p>
                </div>
                <div class="contacts-header-buttons">
                    <?php if ($can_create): ?>
                        <a href="add.php" class="btn">➕ Add Contact</a>
                    <?php endif; ?>
                    <a href="groups.php" class="btn btn-accent">👥 Groups</a>
                    <a href="import.php" class="btn btn-accent">📥 Import</a>
                    <?php if ($can_export): ?>
                        <a href="export.php?<?php echo http_build_query($filters); ?>" class="btn btn-accent">📤 Export</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <!-- Statistics Cards -->
        <div class="contacts-stats-grid">
            <div class="card" style="background: linear-gradient(135deg, #003581 0%, #004aad 100%); padding: 20px; color: #fff; border: none;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 4px;"><?php echo $stats['total']; ?></div>
                <div style="font-size: 14px; opacity: 0.95;">📊 Total Contacts</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%); padding: 20px; color: #fff; border: none;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 4px;"><?php echo $stats['my_contacts']; ?></div>
                <div style="font-size: 14px; opacity: 0.95;">👤 My Contacts</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 20px; color: #fff; border: none;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 4px;"><?php echo $stats['shared']; ?></div>
                <div style="font-size: 14px; opacity: 0.95;">🤝 Shared With Me</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); padding: 20px; color: #fff; border: none;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 4px;"><?php echo $stats['clients']; ?></div>
                <div style="font-size: 14px; opacity: 0.95;">💼 Clients</div>
            </div>
            
            <div class="card" style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 20px; color: #fff; border: none;">
                <div style="font-size: 32px; font-weight: 700; margin-bottom: 4px;"><?php echo $stats['vendors']; ?></div>
                <div style="font-size: 14px; opacity: 0.95;">🏢 Vendors</div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card" style="margin-bottom: 24px;">
            <form method="GET" action="" class="contacts-filter-form">
                <div class="form-group">
                    <label>🔍 Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                           placeholder="Name, email, phone, org..." class="form-control">
                </div>
                
                <div class="form-group">
                    <label>📂 Contact Type</label>
                    <select name="contact_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Client" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Client') ? 'selected' : ''; ?>>Client</option>
                        <option value="Vendor" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Vendor') ? 'selected' : ''; ?>>Vendor</option>
                        <option value="Partner" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Partner') ? 'selected' : ''; ?>>Partner</option>
                        <option value="Personal" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Personal') ? 'selected' : ''; ?>>Personal</option>
                        <option value="Other" <?php echo (isset($_GET['contact_type']) && $_GET['contact_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>🏷️ Tag</label>
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
                
                <div class="form-group">
                    <label>👁️ Share Scope</label>
                    <select name="share_scope" class="form-control">
                        <option value="">All Scopes</option>
                        <option value="Private" <?php echo (isset($_GET['share_scope']) && $_GET['share_scope'] === 'Private') ? 'selected' : ''; ?>>🔒 Private</option>
                        <option value="Team" <?php echo (isset($_GET['share_scope']) && $_GET['share_scope'] === 'Team') ? 'selected' : ''; ?>>👥 Team</option>
                        <option value="Organization" <?php echo (isset($_GET['share_scope']) && $_GET['share_scope'] === 'Organization') ? 'selected' : ''; ?>>🌐 Organization</option>
                    </select>
                </div>
                
                <div class="contacts-filter-buttons" style="display: flex; gap: 8px; align-items: flex-end;">
                    <button type="submit" class="btn">Filter</button>
                    <a href="index.php" class="btn btn-accent">Clear</a>
                </div>
            </form>
        </div>

        <!-- Quick Links -->
        <div class="contacts-quick-links">
            <a href="index.php" class="btn <?php echo empty($filters) ? '' : 'btn-accent'; ?>" style="font-size: 14px;">
                📊 All Contacts (<?php echo $stats['total']; ?>)
            </a>
            <a href="my.php" class="btn btn-accent" style="font-size: 14px;">
                👤 My Contacts (<?php echo $stats['my_contacts']; ?>)
            </a>
            <a href="?contact_type=Client" class="btn btn-accent" style="font-size: 14px;">
                💼 Clients (<?php echo $stats['clients']; ?>)
            </a>
            <a href="?contact_type=Vendor" class="btn btn-accent" style="font-size: 14px;">
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
                        <a href="add.php" class="btn" style="margin-top: 16px;">➕ Add First Contact</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="contacts-grid">
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
                            <div class="contacts-card-avatar" style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #003581 0%, #0066cc 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 20px; flex-shrink: 0;">
                                <?php echo get_contact_initials($contact['name']); ?>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h3 class="contacts-card-name" style="margin: 0 0 4px 0; color: #003581; font-size: 18px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
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
