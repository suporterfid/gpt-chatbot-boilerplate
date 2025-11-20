<?php
// Load configuration
$config = require __DIR__ . '/../../config.php';

// Get branding configuration
$brandName = $config['branding']['brand_name'] ?? 'Assistant Chat Boilerplate';
$logoUrl = $config['branding']['logo_url'] ?? '';
$poweredByLabel = $config['branding']['powered_by_label'] ?? 'Powered by';
$appVersion = $config['app_version'] ?? '1.0.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?> Admin</title>
    <link rel="stylesheet" href="admin.css">
    <?php if (!empty($logoUrl)): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="admin-container">
        <!-- Mobile Menu Overlay -->
        <div class="mobile-overlay" id="mobile-overlay"></div>

        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <?php if (!empty($logoUrl)): ?>
                <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?>"
                     style="height: 32px; width: auto; margin-bottom: 0.5rem;">
                <?php endif; ?>
                <h1><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <nav class="sidebar-nav">
                <!-- Dashboard - Top level link (currently shows agents as overview) -->
                <a href="#agents" class="nav-link active" data-page="agents" data-is-dashboard="true" data-tooltip="View all agents and system overview" data-tooltip-position="right">
                    <i data-lucide="home" class="nav-icon"></i>
                    Dashboard
                </a>

                <!-- Chatbot Configuration Group -->
                <div class="nav-group">
                    <button class="nav-group-toggle" data-group="chatbot-config" data-tooltip="Configure AI agents and knowledge" data-tooltip-position="right">
                        <i data-lucide="brain" class="nav-icon"></i>
                        <span class="nav-group-title">Chatbot Configuration</span>
                        <i data-lucide="chevron-right" class="nav-group-arrow"></i>
                    </button>
                    <div class="nav-group-content" data-group-content="chatbot-config">
                        <a href="#agents" class="nav-link nav-link-sub" data-page="agents" data-group-item="chatbot-config" data-tooltip="Manage AI chatbot agents and their settings" data-tooltip-position="right">
                            <i data-lucide="bot" class="nav-icon"></i>
                            Agents
                        </a>
                        <a href="#prompts" class="nav-link nav-link-sub" data-page="prompts" data-group-item="chatbot-config" data-tooltip="Create and manage system prompts" data-tooltip-position="right">
                            <i data-lucide="file-text" class="nav-icon"></i>
                            Prompts
                        </a>
                        <a href="#vector-stores" class="nav-link nav-link-sub" data-page="vector-stores" data-group-item="chatbot-config" data-tooltip="Upload files and manage knowledge bases" data-tooltip-position="right">
                            <i data-lucide="database" class="nav-icon"></i>
                            Knowledge Bases
                        </a>
                    </div>
                </div>

                <!-- Operations & Monitoring Group -->
                <div class="nav-group">
                    <button class="nav-group-toggle" data-group="operations" data-tooltip="Monitor activity and performance" data-tooltip-position="right">
                        <i data-lucide="activity" class="nav-icon"></i>
                        <span class="nav-group-title">Operations & Monitoring</span>
                        <i data-lucide="chevron-right" class="nav-group-arrow"></i>
                    </button>
                    <div class="nav-group-content" data-group-content="operations">
                        <a href="#audit-conversations" class="nav-link nav-link-sub" data-page="audit-conversations" data-group-item="operations" data-tooltip="Browse and search chat conversations" data-tooltip-position="right">
                            <i data-lucide="message-square" class="nav-icon"></i>
                            Chat History
                        </a>
                        <a href="#billing" class="nav-link nav-link-sub" data-page="billing" data-group-item="operations" data-tooltip="View usage metrics and costs" data-tooltip-position="right">
                            <i data-lucide="bar-chart-3" class="nav-icon"></i>
                            Metrics
                        </a>
                        <a href="#jobs" class="nav-link nav-link-sub" data-page="jobs" data-group-item="operations" data-tooltip="Monitor background jobs and tasks" data-tooltip-position="right">
                            <i data-lucide="clock" class="nav-icon"></i>
                            Jobs
                        </a>
                        <a href="#audit" class="nav-link nav-link-sub" data-page="audit" data-group-item="operations" data-tooltip="Review system activity and changes" data-tooltip-position="right">
                            <i data-lucide="list" class="nav-icon"></i>
                            Audit Log
                        </a>
                    </div>
                </div>

                <!-- System Administration Group -->
                <div class="nav-group">
                    <button class="nav-group-toggle" data-group="system-admin" data-tooltip="System settings and administration" data-tooltip-position="right">
                        <i data-lucide="settings" class="nav-icon"></i>
                        <span class="nav-group-title">System Administration</span>
                        <i data-lucide="chevron-right" class="nav-group-arrow"></i>
                    </button>
                    <div class="nav-group-content" data-group-content="system-admin">
                        <a href="#users" class="nav-link nav-link-sub" data-page="users" data-super-admin-only="true" data-group-item="system-admin" data-tooltip="Manage users and access control" data-tooltip-position="right">
                            <i data-lucide="users" class="nav-icon"></i>
                            Users & Permissions
                        </a>
                        <a href="#tenants" class="nav-link nav-link-sub" data-page="tenants" data-super-admin-only="true" data-group-item="system-admin" data-tooltip="Manage multi-tenant organizations" data-tooltip-position="right">
                            <i data-lucide="building" class="nav-icon"></i>
                            Tenants
                        </a>
                        <a href="#settings" class="nav-link nav-link-sub" data-page="settings" data-group-item="system-admin" data-tooltip="Configure system-wide settings" data-tooltip-position="right">
                            <i data-lucide="sliders" class="nav-icon"></i>
                            General Settings
                        </a>
                    </div>
                </div>

                <!-- Other/Legacy Items (kept at bottom for backwards compatibility) -->
                <div class="nav-group">
                    <button class="nav-group-toggle" data-group="other" data-tooltip="Additional tools and features" data-tooltip-position="right">
                        <i data-lucide="wrench" class="nav-icon"></i>
                        <span class="nav-group-title">Other Tools</span>
                        <i data-lucide="chevron-right" class="nav-group-arrow"></i>
                    </button>
                    <div class="nav-group-content" data-group-content="other">
                        <a href="leadsense-crm.html" class="nav-link nav-link-sub" data-tooltip="Manage leads and customer pipelines" data-tooltip-position="right">
                            <i data-lucide="kanban-square" class="nav-icon"></i>
                            LeadSense CRM
                        </a>
                        <a href="#whatsapp-templates" class="nav-link nav-link-sub" data-page="whatsapp-templates" data-group-item="other" data-tooltip="Create WhatsApp message templates" data-tooltip-position="right">
                            <i data-lucide="smartphone" class="nav-icon"></i>
                            WhatsApp Templates
                        </a>
                        <a href="#consent-management" class="nav-link nav-link-sub" data-page="consent-management" data-group-item="other" data-tooltip="Manage user consent and preferences" data-tooltip-position="right">
                            <i data-lucide="check-square" class="nav-icon"></i>
                            Consent Management
                        </a>
                        <a href="#webhook-testing" class="nav-link nav-link-sub" data-page="webhook-testing" data-group-item="other" data-tooltip="Test and debug webhook integrations" data-tooltip-position="right">
                            <i data-lucide="webhook" class="nav-icon"></i>
                            Webhook Testing
                        </a>
                    </div>
                </div>
            </nav>
            <div class="sidebar-footer">
                <a href="#logout" class="nav-link nav-link-logout" id="logout-link" role="button">
                    <i data-lucide="log-out" class="nav-icon"></i>
                    Logout
                </a>
                <div class="sidebar-version" id="sidebar-version" style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; color: #999; border-top: 1px solid #e5e7eb;">
                    <!-- Version will be loaded dynamically -->
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Toggle menu">
                        <i data-lucide="menu" class="mobile-menu-icon"></i>
                    </button>
                    <h2 id="page-title">Agents</h2>
                </div>
                <div class="header-right">
                    <div class="status-wrapper" title="Connection status">
                        <span class="status-indicator" id="status-indicator">●</span>
                        <span id="status-text">Offline</span>
                    </div>
                    <div class="tenant-session" id="current-tenant-pill">
                        <span class="tenant-session-label">Tenant</span>
                        <span class="tenant-session-value" id="current-tenant-value">—</span>
                    </div>
                    <div class="tenant-selector" id="tenant-selector-wrapper">
                        <label for="tenant-selector" class="sr-only">Select tenant</label>
                        <select id="tenant-selector">
                            <option value="">All tenants</option>
                        </select>
                    </div>
                    <div class="user-session" id="current-user-pill">
                        <span class="user-session-label">Signed in as</span>
                        <span class="user-session-value" id="current-user-email">Guest</span>
                    </div>
                </div>
            </header>

            <!-- Content Container -->
            <div class="content" id="content">
                <!-- Dynamic content will be loaded here -->
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div class="modal" id="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Modal Title</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modal-body">
                <!-- Modal content -->
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Login Modal -->
    <div class="login-modal" id="login-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="login-modal-title">
        <div class="login-modal-content">
            <?php if (!empty($logoUrl)): ?>
            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?>"
                 style="height: 48px; width: auto; margin: 0 auto 1rem; display: block;">
            <?php endif; ?>
            <h2 id="login-modal-title">Sign in to <?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="login-modal-subtitle">Enter your credentials to manage agents, tenants and more.</p>
            <form id="login-form" novalidate>
                <label class="form-label" for="login-email">Email</label>
                <input type="email" id="login-email" name="email" class="form-input" placeholder="you@example.com" autocomplete="username" required />

                <label class="form-label" for="login-password">Password</label>
                <input type="password" id="login-password" name="password" class="form-input" placeholder="••••••••" autocomplete="current-password" required />

                <p class="login-error" id="login-error" role="alert" aria-live="assertive"></p>

                <button type="submit" class="btn btn-primary btn-full" id="login-submit">Sign in</button>
            </form>
        </div>
    </div>

    <script src="admin.js"></script>
    <script src="prompt-builder.js"></script>
    <script src="agent-workspace.js"></script>
    <script>
        // Initialize Lucide icons after page load
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Mobile menu functionality
        (function() {
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobile-overlay');

            function openMobileMenu() {
                sidebar.classList.add('open');
                mobileOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeMobileMenu() {
                sidebar.classList.remove('open');
                mobileOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    if (sidebar.classList.contains('open')) {
                        closeMobileMenu();
                    } else {
                        openMobileMenu();
                    }
                });
            }

            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', closeMobileMenu);
            }

            // Close menu when clicking on navigation links
            const navLinks = sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    // Close menu on mobile when link is clicked
                    if (window.innerWidth <= 768) {
                        closeMobileMenu();
                    }
                });
            });

            // Close menu on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeMobileMenu();
                }
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                    closeMobileMenu();
                }
            });
        })();
    </script>
</body>
</html>
