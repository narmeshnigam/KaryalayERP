<?php
/**
 * Clients Module - Client Profile View
 * 360° client profile with tabs for overview, contacts, addresses, documents, projects, timeline
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/helpers.php';

if (!authz_user_can_any($conn, [
    ['table' => 'clients', 'permission' => 'view_all'],
    ['table' => 'clients', 'permission' => 'view_assigned'],
    ['table' => 'clients', 'permission' => 'view_own'],
])) {
    authz_require_permission($conn, 'clients', 'view_all');
}

// Get client ID
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$client_id) {
    header("Location: index.php");
    exit;
}

// Get client details
$client = get_client_by_id($conn, $client_id);
if (!$client) {
    $_SESSION['flash_message'] = "Client not found.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit;
}

// Get related data
$addresses = get_client_addresses($conn, $client_id);
$contacts = get_client_contacts($conn, $client_id);
$documents = get_client_documents($conn, $client_id);
$custom_fields = get_client_custom_fields($conn, $client_id);

// Get active tab
$active_tab = $_GET['tab'] ?? 'overview';

// Check for projects (if table exists)
$projects = [];
$has_projects_table = $conn->query("SHOW TABLES LIKE 'projects'")->num_rows > 0;
if ($has_projects_table) {
    $stmt = $conn->prepare("SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Check permissions
$can_update = authz_user_can($conn, 'clients', 'update');

$page_title = $client['name'] . ' - Client Profile - ' . APP_NAME;
require_once __DIR__ . '/../../includes/header_sidebar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-wrapper">
<style>
.clients-view-header-flex{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;}
.clients-view-header-buttons{display:flex;gap:8px;align-items:center;}

@media (max-width:768px){
.clients-view-header-flex{flex-direction:column;align-items:stretch;}
.clients-view-header-buttons{width:100%;flex-direction:column;gap:10px;}
.clients-view-header-buttons .btn{width:100%;text-align:center;}
}

@media (max-width:480px){
.clients-view-header-flex h1{font-size:1.5rem;}
}

/* Client Header Info Responsive */
.clients-view-header-info{display:flex;gap:16px;align-items:center;flex:1;}
.clients-view-header-avatar{width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg, #003581 0%, #0059b3 100%);color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:24px;flex-shrink:0;}

@media (max-width:768px){
.clients-view-header-info{flex-direction:column;gap:12px;}
}

@media (max-width:480px){
.clients-view-header-avatar{width:50px;height:50px;font-size:18px;}
.clients-view-header-info{text-align:center;}
}

/* Tabs Responsive */
.clients-view-tabs{background:white;border-radius:8px;overflow:hidden;margin-bottom:24px;box-shadow:0 2px 4px rgba(0,0,0,0.05);}
.clients-view-tabs-container{display:flex;border-bottom:2px solid #e9ecef;overflow-x:auto;}
.clients-view-tab-link{padding:16px 24px;text-decoration:none;white-space:nowrap;border-bottom:3px solid transparent;font-weight:600;transition:all 0.3s;}

@media (max-width:768px){
.clients-view-tab-link{padding:12px 16px;font-size:13px;}
}

@media (max-width:480px){
.clients-view-tab-link{padding:12px 12px;font-size:12px;}
}

/* Info Grid Responsive */
.clients-info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px, 1fr));gap:16px;margin-bottom:24px;}

@media (max-width:768px){
.clients-info-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
}

@media (max-width:480px){
.clients-info-grid{grid-template-columns:1fr;gap:12px;}
.clients-info-card{padding:12px !important;}
.clients-info-label{font-size:10px !important;}
.clients-info-value{font-size:13px !important;}
}

/* Contact Persons Stack */
.clients-contacts-list{display:grid;gap:16px;}
.clients-contact-card{padding:20px;background:#f8f9fa;border-radius:6px;border:1px solid #e9ecef;}

@media (max-width:768px){
.clients-contact-card{padding:16px;}
}

@media (max-width:480px){
.clients-contact-card{padding:12px;}
.clients-contact-card h4{font-size:14px !important;}
.clients-contact-info{flex-direction:column;gap:8px !important;}
}

/* Documents Responsive */
.clients-documents-list{display:grid;gap:16px;}
.clients-doc-item{padding:20px;background:#f8f9fa;border-radius:6px;border:1px solid #e9ecef;display:flex;align-items:center;gap:16px;}

@media (max-width:768px){
.clients-doc-item{flex-direction:column;text-align:center;}
.clients-doc-icon{font-size:32px;}
}

@media (max-width:480px){
.clients-doc-item{padding:12px;gap:12px;}
.clients-doc-info{flex:1;text-align:left;}
}

/* Projects Table Responsive */
.clients-projects-table{width:100%;border-collapse:collapse;}
.clients-projects-table-wrapper{overflow-x:auto;}

@media (max-width:768px){
.clients-projects-table th{padding:10px 8px !important;font-size:13px;}
.clients-projects-table td{padding:10px 8px !important;font-size:13px;}
}

@media (max-width:600px){
.clients-projects-table{display:block;width:100%;}
.clients-projects-table thead{display:none;}
.clients-projects-table tbody{display:block;}
.clients-projects-table tbody tr{display:block;margin-bottom:20px;border:1px solid #dee2e6;border-radius:8px;overflow:hidden;}
.clients-projects-table td{display:block;text-align:left;padding:12px;border:none;border-bottom:1px solid #dee2e6;}
.clients-projects-table td:last-child{border-bottom:none;text-align:center;}
.clients-projects-table td:before{content:attr(data-label);font-weight:700;color:#003581;margin-bottom:4px;display:block;font-size:11px;}
}

/* Tags Responsive */
.clients-tags-container{display:flex;gap:8px;flex-wrap:wrap;}

@media (max-width:480px){
.clients-tags-container{gap:4px;}
.clients-tags-container .badge{font-size:11px;padding:4px 8px;}
}

/* Timeline Responsive */
.clients-timeline{position:relative;padding-left:40px;}
.clients-timeline-item{position:relative;margin-bottom:24px;}
.clients-timeline-dot{position:absolute;left:-33px;width:24px;height:24px;border-radius:50%;border:3px solid white;box-shadow:0 2px 4px rgba(0,0,0,0.1);}
.clients-timeline-content{padding:16px;background:#f8f9fa;border-radius:6px;border:1px solid #e9ecef;}

@media (max-width:768px){
.clients-timeline{padding-left:30px;}
.clients-timeline-dot{left:-26px;width:20px;height:20px;}
.clients-timeline-content{padding:12px;}
}

@media (max-width:480px){
.clients-timeline{padding-left:25px;}
.clients-timeline-dot{left:-21px;width:18px;height:18px;}
.clients-timeline-content{padding:10px;font-size:13px;}
}
</style>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="clients-view-header-flex">
                <div class="clients-view-header-info">
                    <div class="clients-view-header-avatar">
                        <?= get_client_initials($client['name']) ?>
                    </div>
                    <div style="flex: 1;">
                        <h1 style="margin: 0 0 8px 0;"><?= htmlspecialchars($client['name']) ?></h1>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 14px; color: #6c757d;">
                            <span><strong>Code:</strong> <?= htmlspecialchars($client['code']) ?></span>
                            <?php if ($client['legal_name']): ?>
                                <span><strong>Legal:</strong> <?= htmlspecialchars($client['legal_name']) ?></span>
                            <?php endif; ?>
                            <?php if ($client['industry']): ?>
                                <span>🏭 <?= htmlspecialchars($client['industry']) ?></span>
                            <?php endif; ?>
                            <span>👤 <?= htmlspecialchars($client['owner_username']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="clients-view-header-buttons">
                    <?php if ($client['status'] === 'Active'): ?>
                        <span class="badge badge-success" style="padding: 8px 16px; font-size: 14px;">
                            <?= get_status_icon($client['status']) ?> <?= htmlspecialchars($client['status']) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge badge-secondary" style="padding: 8px 16px; font-size: 14px;">
                            <?= get_status_icon($client['status']) ?> <?= htmlspecialchars($client['status']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($can_update): ?>
                        <a href="edit.php?id=<?= $client_id ?>" class="btn">✏️ Edit</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-accent">← Back</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <!-- Tabs -->
        <div class="clients-view-tabs">
            <div class="clients-view-tabs-container">
                <a href="?id=<?= $client_id ?>&tab=overview" 
                   class="clients-view-tab-link" 
                   style="border-bottom: 3px solid <?= $active_tab === 'overview' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'overview' ? '#003581' : '#6c757d' ?>;">
                    📊 Overview
                </a>
                <a href="?id=<?= $client_id ?>&tab=contacts" 
                   class="clients-view-tab-link" 
                   style="border-bottom: 3px solid <?= $active_tab === 'contacts' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'contacts' ? '#003581' : '#6c757d' ?>;">
                    👥 Contacts (<?= count($contacts) ?>)
                </a>
                <a href="?id=<?= $client_id ?>&tab=addresses" 
                   class="clients-view-tab-link" 
                   style="border-bottom: 3px solid <?= $active_tab === 'addresses' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'addresses' ? '#003581' : '#6c757d' ?>;">
                    📍 Addresses (<?= count($addresses) ?>)
                </a>
                <a href="?id=<?= $client_id ?>&tab=documents" 
                   class="clients-view-tab-link" 
                   style="border-bottom: 3px solid <?= $active_tab === 'documents' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'documents' ? '#003581' : '#6c757d' ?>;">
                    📄 Documents (<?= count($documents) ?>)
                </a>
                <?php if ($has_projects_table): ?>
                    <a href="?id=<?= $client_id ?>&tab=projects" 
                       class="clients-view-tab-link" 
                       style="border-bottom: 3px solid <?= $active_tab === 'projects' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'projects' ? '#003581' : '#6c757d' ?>;">
                        🚀 Projects (<?= count($projects) ?>)
                    </a>
                <?php endif; ?>
                <a href="?id=<?= $client_id ?>&tab=timeline" 
                   class="clients-view-tab-link" 
                   style="border-bottom: 3px solid <?= $active_tab === 'timeline' ? '#003581' : 'transparent' ?>; color: <?= $active_tab === 'timeline' ? '#003581' : '#6c757d' ?>;">
                    🕒 Timeline
                </a>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="card">
        <?php if ($active_tab === 'overview'): ?>
            <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                📋 Client Information
            </h3>
            <div class="clients-info-grid">
                <?php if ($client['email']): ?>
                    <div class="clients-info-card" style="padding: 16px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                        <div class="clients-info-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">
                            📧 Email
                        </div>
                        <div class="clients-info-value" style="font-size: 14px; color: #1b2a57; font-weight: 500;">
                            <a href="mailto:<?= htmlspecialchars($client['email']) ?>" style="color: #003581; text-decoration: none;">
                                <?= htmlspecialchars($client['email']) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($client['phone']): ?>
                    <div class="clients-info-card" style="padding: 16px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                        <div class="clients-info-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">
                            📞 Phone
                        </div>
                        <div class="clients-info-value" style="font-size: 14px; color: #1b2a57; font-weight: 500;">
                            <a href="tel:<?= htmlspecialchars($client['phone']) ?>" style="color: #003581; text-decoration: none;">
                                <?= htmlspecialchars($client['phone']) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($client['website']): ?>
                    <div class="clients-info-card" style="padding: 16px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                        <div class="clients-info-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">
                            🌐 Website
                        </div>
                        <div class="clients-info-value" style="font-size: 14px; color: #1b2a57; font-weight: 500;">
                            <a href="<?= htmlspecialchars($client['website']) ?>" target="_blank" style="color: #003581; text-decoration: none;">
                                <?= htmlspecialchars($client['website']) ?> ↗
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($client['gstin']): ?>
                    <div class="clients-info-card" style="padding: 16px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                        <div class="clients-info-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">
                            🏢 GSTIN
                        </div>
                        <div class="clients-info-value" style="font-size: 14px; color: #1b2a57; font-weight: 500;">
                            <?= htmlspecialchars($client['gstin']) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($client['lead_name'])): ?>
                    <div class="clients-info-card" style="padding: 16px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                        <div class="clients-info-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">
                            🎯 Converted from Lead
                        </div>
                        <div class="clients-info-value" style="font-size: 14px; color: #1b2a57; font-weight: 500;">
                            <?= htmlspecialchars($client['lead_name']) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="clients-info-card" style="padding: 16px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                    <div class="clients-info-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">
                        📅 Created
                    </div>
                    <div class="clients-info-value" style="font-size: 14px; color: #1b2a57; font-weight: 500;">
                        <?= date('M d, Y', strtotime($client['created_at'])) ?>
                        <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">by <?= htmlspecialchars($client['created_by_username']) ?></div>
                    </div>
                </div>
                
                <div class="clients-info-card" style="padding: 16px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                    <div class="clients-info-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">
                        🔄 Last Updated
                    </div>
                    <div class="clients-info-value" style="font-size: 14px; color: #1b2a57; font-weight: 500;">
                        <?php if (!empty($client['updated_at'])): ?>
                            <?= date('M d, Y H:i', strtotime($client['updated_at'])) ?>
                        <?php else: ?>
                            <span style="color: #6c757d;">Not updated</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($client['tags']): ?>
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin: 24px 0 16px 0; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                    🏷️ Tags
                </h3>
                <div class="clients-tags-container">
                    <?php foreach (explode(',', $client['tags']) as $tag): ?>
                        <span class="badge badge-info" style="padding: 6px 12px; font-size: 13px;">
                            <?= htmlspecialchars(trim($tag)) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($client['notes']): ?>
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin: 24px 0 16px 0; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                    📝 Internal Notes
                </h3>
                <div style="white-space: pre-wrap; background: #f8f9fa; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef; line-height: 1.6; color: #1b2a57;">
                    <?= htmlspecialchars($client['notes']) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($custom_fields)): ?>
                <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin: 24px 0 16px 0; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                    ⚙️ Custom Fields
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                    <?php foreach ($custom_fields as $key => $value): ?>
                        <div style="padding: 16px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                            <div style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">
                                <?= htmlspecialchars($key) ?>
                            </div>
                            <div style="font-size: 14px; color: #1b2a57; font-weight: 500;">
                                <?= htmlspecialchars($value) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'contacts'): ?>
            <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                👥 Contact Persons
            </h3>
            <?php if (!empty($contacts)): ?>
                <div class="clients-contacts-list">
                    <?php foreach ($contacts as $contact): ?>
                        <div class="clients-contact-card">
                            <h4 style="margin: 0 0 12px 0; font-size: 16px; color: #1b2a57; font-weight: 600;">
                                <?= htmlspecialchars($contact['name']) ?>
                            </h4>
                            <?php if ($contact['role_at_client']): ?>
                                <div style="margin-bottom: 8px; font-size: 14px; color: #6c757d;">
                                    <strong>Role:</strong> <?= htmlspecialchars($contact['role_at_client']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="clients-contact-info" style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 14px;">
                                <?php if ($contact['email']): ?>
                                    <div>
                                        📧 <a href="mailto:<?= htmlspecialchars($contact['email']) ?>" style="color: #003581; text-decoration: none;">
                                            <?= htmlspecialchars($contact['email']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($contact['phone']): ?>
                                    <div>
                                        📞 <a href="tel:<?= htmlspecialchars($contact['phone']) ?>" style="color: #003581; text-decoration: none;">
                                            <?= htmlspecialchars($contact['phone']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($contact['designation']): ?>
                                    <div style="color: #6c757d;">
                                        💼 <?= htmlspecialchars($contact['designation']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">👥</div>
                    <h3 style="color: #1b2a57; margin-bottom: 8px;">No contact persons linked yet</h3>
                    <p style="color: #6c757d; margin-bottom: 24px;">Link contacts from your contacts module to this client</p>
                    <a href="../contacts/index.php" class="btn">Add from Contacts</a>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'addresses'): ?>
            <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                📍 Addresses
            </h3>
            <?php if (!empty($addresses)): ?>
                <div style="display: grid; gap: 16px;">
                    <?php foreach ($addresses as $addr): ?>
                        <div style="padding: 20px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                            <h4 style="margin: 0 0 12px 0; font-size: 16px; color: #1b2a57; font-weight: 600;">
                                <?= htmlspecialchars($addr['label']) ?>
                                <?php if ($addr['is_default']): ?>
                                    <span class="badge badge-info" style="margin-left: 8px; font-size: 11px;">Default</span>
                                <?php endif; ?>
                            </h4>
                            <div style="font-size: 14px; color: #495057; line-height: 1.6;">
                                <?= htmlspecialchars($addr['line1']) ?><br>
                                <?php if ($addr['line2']): ?>
                                    <?= htmlspecialchars($addr['line2']) ?><br>
                                <?php endif; ?>
                                <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?> - <?= htmlspecialchars($addr['zip']) ?><br>
                                <?= htmlspecialchars($addr['country']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">📍</div>
                    <h3 style="color: #1b2a57; margin-bottom: 8px;">No addresses added yet</h3>
                    <p style="color: #6c757d; margin-bottom: 24px;">Add billing, shipping, or office addresses for this client</p>
                    <?php if ($can_update): ?>
                        <button class="btn">+ Add Address</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'documents'): ?>
            <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                📄 Documents
            </h3>
            <?php if (!empty($documents)): ?>
                <div class="clients-documents-list">
                    <?php foreach ($documents as $doc): ?>
                        <div class="clients-doc-item">
                            <div class="clients-doc-icon" style="font-size: 40px; flex-shrink: 0;">📄</div>
                            <div class="clients-doc-info" style="flex: 1;">
                                <h4 style="margin: 0 0 8px 0; font-size: 16px; color: #1b2a57; font-weight: 600;">
                                    <?= htmlspecialchars($doc['file_name']) ?>
                                </h4>
                                <div style="font-size: 13px; color: #6c757d;">
                                    <span class="badge badge-secondary" style="margin-right: 8px;"><?= htmlspecialchars($doc['doc_type']) ?></span>
                                    Uploaded: <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?> by <?= htmlspecialchars($doc['uploaded_by_username']) ?>
                                </div>
                            </div>
                            <div>
                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn" style="padding: 8px 16px; font-size: 14px;">
                                    👁️ View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">📄</div>
                    <h3 style="color: #1b2a57; margin-bottom: 8px;">No documents uploaded yet</h3>
                    <p style="color: #6c757d; margin-bottom: 24px;">Upload NDAs, contracts, certificates, or other client documents</p>
                    <?php if ($can_update): ?>
                        <button class="btn">+ Upload Document</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'projects' && $has_projects_table): ?>
            <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                🚀 Projects
            </h3>
            <?php if (!empty($projects)): ?>
                <div class="clients-projects-table-wrapper">
                <table class="clients-projects-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Project Name</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Status</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #003581;">Start Date</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #003581;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr style="border-bottom: 1px solid #dee2e6;">
                                <td style="padding: 12px; font-weight: 600; color: #1b2a57;" data-label="Project Name"><?= htmlspecialchars($project['title'] ?? '') ?></td>
                                <td style="padding: 12px;" data-label="Status"><span class="badge badge-info"><?= htmlspecialchars($project['status']) ?></span></td>
                                <td style="padding: 12px;" data-label="Start Date">
                                    <?php
                                    $start_date = $project['start_date'] ?? '';
                                    if (!empty($start_date)) {
                                        echo date('M d, Y', strtotime($start_date));
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td style="padding: 12px; text-align: center;" data-label="Actions">
                                    <a href="../projects/view.php?id=<?= $project['id'] ?>" class="btn btn-accent" style="padding: 6px 12px; font-size: 12px;">
                                        View →
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">🚀</div>
                    <h3 style="color: #1b2a57; margin-bottom: 8px;">No projects for this client yet</h3>
                    <p style="color: #6c757d; margin-bottom: 24px;">Projects associated with this client will appear here</p>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'timeline'): ?>
            <h3 style="font-size: 18px; font-weight: 700; color: #003581; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #003581;">
                🕒 Activity Timeline
            </h3>
            <div class="clients-timeline">
                <div style="position: absolute; left: 15px; top: 0; bottom: 0; width: 3px; background: #e9ecef;"></div>
                
                <div class="clients-timeline-item">
                    <div class="clients-timeline-dot" style="background: #003581;"></div>
                    <div class="clients-timeline-content">
                        <div style="font-size: 12px; color: #6c757d; margin-bottom: 6px;">
                            <?= date('M d, Y H:i', strtotime($client['created_at'])) ?>
                        </div>
                        <div style="font-weight: 600; color: #1b2a57; margin-bottom: 4px;">✨ Client Created</div>
                        <div style="font-size: 14px; color: #6c757d;">by <?= htmlspecialchars($client['created_by_username']) ?></div>
                    </div>
                </div>
                
                <?php if ($client['updated_at'] != $client['created_at']): ?>
                    <div class="clients-timeline-item">
                        <div class="clients-timeline-dot" style="background: #faa718;"></div>
                        <div class="clients-timeline-content">
                            <div style="font-size: 12px; color: #6c757d; margin-bottom: 6px;">
                                <?= date('M d, Y H:i', strtotime($client['updated_at'])) ?>
                            </div>
                            <div style="font-weight: 600; color: #1b2a57;">🔄 Client Updated</div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($client['lead_id']): ?>
                    <div class="clients-timeline-item">
                        <div class="clients-timeline-dot" style="background: #28a745;"></div>
                        <div class="clients-timeline-content">
                            <div style="font-size: 12px; color: #6c757d; margin-bottom: 6px;">
                                <?= date('M d, Y', strtotime($client['created_at'])) ?>
                            </div>
                            <div style="font-weight: 600; color: #1b2a57; margin-bottom: 4px;">🎯 Converted from Lead</div>
                            <div style="font-size: 14px; color: #6c757d;">
                                <?= htmlspecialchars(isset($client['lead_name']) ? $client['lead_name'] : ('Lead #' . $client['lead_id'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>

    </div>
</div>

<?php 
if (!empty($GLOBALS['AUTHZ_CONN_MANAGED'])) {
    closeConnection($conn);
}
require_once __DIR__ . '/../../includes/footer_sidebar.php'; 
?>
