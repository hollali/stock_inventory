<?php
// Include database connection and settings if not already included
if (!isset($settings)) {
    include_once './config/connect.php';
    require_once './config/Settings.php';
    $settings = new Settings($conn);
}

// Helper function for safe HTML output if not defined
if (!function_exists('safe_html')) {
    function safe_html($value, $default = '') {
        return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
    }
}

// Get current page and hash for active states
$current_page = basename($_SERVER['PHP_SELF']);
$current_hash = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '#') !== false 
    ? substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], '#') + 1) 
    : '';

// Get company name from settings
$company_name = $settings->get('company_name', 'InventoryPro');
$company_logo = $settings->get('company_logo', '');
$company_email = $settings->get('company_email', 'admin@inventory.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= safe_html($company_name) ?> - Sidebar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<!-- sidebar.php -->
<div>
    <!-- Overlay for mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden lg:hidden transition-opacity duration-300" onclick="toggleSidebarMobile()"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="fixed inset-y-0 left-0 w-72 bg-white transform -translate-x-72 lg:translate-x-0 transition-all duration-300 ease-in-out z-50 flex flex-col shadow-xl">
        <!-- Header with Logo -->
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center shadow-sm">
                        <?php if ($company_logo): ?>
                            <img src="<?= safe_html($company_logo) ?>" alt="<?= safe_html($company_name) ?>" class="w-6 h-6 object-contain">
                        <?php else: ?>
                            <span class="text-white font-bold text-xl"><?= substr(safe_html($company_name), 0, 1) ?></span>
                        <?php endif; ?>
                    </div>
                    <span id="sidebarLogo" class="text-xl font-semibold text-gray-800"><?= safe_html($company_name) ?></span>
                </div>
                <div class="flex items-center space-x-2">
                    <!-- Collapse toggle for large screens -->
                    <button class="hidden lg:flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition-colors duration-200" onclick="toggleSidebar()">
                        <svg id="collapseIcon" class="w-5 h-5 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <!-- Close button for mobile -->
                    <button class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition-colors duration-200" onclick="toggleSidebarMobile()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- User Info -->
        <div class="px-6 py-4 border-b border-gray-200 user-info">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-semibold shadow-sm flex-shrink-0">
                    <?php 
                    // Get user initials from session (you'll need to implement this)
                    $user_initials = isset($_SESSION['user_initials']) ? $_SESSION['user_initials'] : 'AD';
                    echo safe_html($user_initials);
                    ?>
                </div>
                <div class="flex-1 min-w-0 user-details">
                    <p class="text-sm font-medium text-gray-800 truncate">
                        <?= isset($_SESSION['user_name']) ? safe_html($_SESSION['user_name']) : 'Admin User' ?>
                    </p>
                    <p class="text-xs text-gray-500 truncate">
                        <?= isset($_SESSION['user_email']) ? safe_html($_SESSION['user_email']) : safe_html($company_email) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav id="sidebarLinks" class="flex-1 overflow-y-auto py-4 px-3">
            <div class="space-y-1">
                <!-- Dashboard -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group <?= $current_page == 'dashboard.php' ? 'bg-blue-50 text-blue-600' : '' ?>" data-page="dashboard.php">
                    <svg class="w-5 h-5 <?= $current_page == 'dashboard.php' ? 'text-blue-600' : 'text-gray-400' ?> group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span class="menu-text text-sm">Dashboard</span>
                </a>

                <!-- Products -->
                <a href="products.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group <?= $current_page == 'products.php' ? 'bg-blue-50 text-blue-600' : '' ?>" data-page="products.php">
                    <svg class="w-5 h-5 <?= $current_page == 'products.php' ? 'text-blue-600' : 'text-gray-400' ?> group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span class="menu-text text-sm">Products</span>
                </a>

                <!-- Categories -->
                <a href="categories.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group <?= $current_page == 'categories.php' ? 'bg-blue-50 text-blue-600' : '' ?>" data-page="categories.php">
                    <svg class="w-5 h-5 <?= $current_page == 'categories.php' ? 'text-blue-600' : 'text-gray-400' ?> group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-5-5A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    <span class="menu-text text-sm">Categories</span>
                </a>

                <!-- Reports -->
                <a href="reports.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group <?= $current_page == 'reports.php' ? 'bg-blue-50 text-blue-600' : '' ?>" data-page="reports.php">
                    <svg class="w-5 h-5 <?= $current_page == 'reports.php' ? 'text-blue-600' : 'text-gray-400' ?> group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="menu-text text-sm">Reports</span>
                </a>
            </div>

            <!-- Settings Section -->
            <div class="mt-8 pt-4 border-t border-gray-200">
                <div class="space-y-1">
                    <!-- Settings -->
                    <a href="settings.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group <?= $current_page == 'settings.php' ? 'bg-blue-50 text-blue-600' : '' ?>" data-page="settings.php" onclick="handleSettingsClick(event)">
                        <svg class="w-5 h-5 <?= $current_page == 'settings.php' ? 'text-blue-600' : 'text-gray-400' ?> group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="menu-text text-sm">Settings</span>
                        
                        <!-- Settings Submenu Indicator (visible when active) -->
                        <span class="settings-submenu-indicator ml-auto <?= $current_page == 'settings.php' ? '' : 'hidden' ?>">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </span>
                    </a>

                    <!-- Settings Submenu (appears when settings is active and expanded) -->
                    <div id="settingsSubmenu" class="ml-8 mt-1 space-y-1 <?= $current_page == 'settings.php' ? '' : 'hidden' ?>">
                        <a href="settings.php#company" class="settings-submenu-item flex items-center space-x-3 px-3 py-2 rounded-lg text-sm <?= ($current_page == 'settings.php' && $current_hash == 'company') ? 'text-blue-600 bg-blue-50' : 'text-gray-500' ?> hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group" data-tab="company">
                            <span class="w-1.5 h-1.5 <?= ($current_page == 'settings.php' && $current_hash == 'company') ? 'bg-blue-600' : 'bg-gray-300' ?> rounded-full group-hover:bg-blue-600"></span>
                            <span class="menu-text">Company</span>
                        </a>
                        <a href="settings.php#financial" class="settings-submenu-item flex items-center space-x-3 px-3 py-2 rounded-lg text-sm <?= ($current_page == 'settings.php' && $current_hash == 'financial') ? 'text-blue-600 bg-blue-50' : 'text-gray-500' ?> hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group" data-tab="financial">
                            <span class="w-1.5 h-1.5 <?= ($current_page == 'settings.php' && $current_hash == 'financial') ? 'bg-blue-600' : 'bg-gray-300' ?> rounded-full group-hover:bg-blue-600"></span>
                            <span class="menu-text">Financial</span>
                        </a>
                        <a href="settings.php#tax" class="settings-submenu-item flex items-center space-x-3 px-3 py-2 rounded-lg text-sm <?= ($current_page == 'settings.php' && $current_hash == 'tax') ? 'text-blue-600 bg-blue-50' : 'text-gray-500' ?> hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group" data-tab="tax">
                            <span class="w-1.5 h-1.5 <?= ($current_page == 'settings.php' && $current_hash == 'tax') ? 'bg-blue-600' : 'bg-gray-300' ?> rounded-full group-hover:bg-blue-600"></span>
                            <span class="menu-text">Tax Settings</span>
                        </a>
                        <a href="settings.php#notifications" class="settings-submenu-item flex items-center space-x-3 px-3 py-2 rounded-lg text-sm <?= ($current_page == 'settings.php' && $current_hash == 'notifications') ? 'text-blue-600 bg-blue-50' : 'text-gray-500' ?> hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group" data-tab="notifications">
                            <span class="w-1.5 h-1.5 <?= ($current_page == 'settings.php' && $current_hash == 'notifications') ? 'bg-blue-600' : 'bg-gray-300' ?> rounded-full group-hover:bg-blue-600"></span>
                            <span class="menu-text">Notifications</span>
                        </a>
                        <a href="settings.php#system" class="settings-submenu-item flex items-center space-x-3 px-3 py-2 rounded-lg text-sm <?= ($current_page == 'settings.php' && $current_hash == 'system') ? 'text-blue-600 bg-blue-50' : 'text-gray-500' ?> hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group" data-tab="system">
                            <span class="w-1.5 h-1.5 <?= ($current_page == 'settings.php' && $current_hash == 'system') ? 'bg-blue-600' : 'bg-gray-300' ?> rounded-full group-hover:bg-blue-600"></span>
                            <span class="menu-text">System</span>
                        </a>
                        <a href="settings.php#invoice" class="settings-submenu-item flex items-center space-x-3 px-3 py-2 rounded-lg text-sm <?= ($current_page == 'settings.php' && $current_hash == 'invoice') ? 'text-blue-600 bg-blue-50' : 'text-gray-500' ?> hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group" data-tab="invoice">
                            <span class="w-1.5 h-1.5 <?= ($current_page == 'settings.php' && $current_hash == 'invoice') ? 'bg-blue-600' : 'bg-gray-300' ?> rounded-full group-hover:bg-blue-600"></span>
                            <span class="menu-text">Invoice</span>
                        </a>
                        <a href="settings.php#security" class="settings-submenu-item flex items-center space-x-3 px-3 py-2 rounded-lg text-sm <?= ($current_page == 'settings.php' && $current_hash == 'security') ? 'text-blue-600 bg-blue-50' : 'text-gray-500' ?> hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group" data-tab="security">
                            <span class="w-1.5 h-1.5 <?= ($current_page == 'settings.php' && $current_hash == 'security') ? 'bg-blue-600' : 'bg-gray-300' ?> rounded-full group-hover:bg-blue-600"></span>
                            <span class="menu-text">Security</span>
                        </a>
                    </div>

                    <!-- Logout -->
                    <a href="logout.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-red-600 hover:bg-red-50 transition-colors duration-200 group" onclick="return confirm('Are you sure you want to logout?')">
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-red-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span class="menu-text text-sm">Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Footer -->
        <div class="p-4 border-t border-gray-200 footer-info">
            <div class="text-xs text-gray-400 text-center">
                <p>Version 2.0.0</p>
                <p class="mt-1">© <?= date('Y') ?> <?= safe_html($company_name) ?></p>
            </div>
        </div>
    </div>

    <!-- Mobile Hamburger -->
    <button class="fixed top-4 left-4 z-40 bg-white text-gray-700 p-3 rounded-xl shadow-lg lg:hidden hover:shadow-xl transition-all duration-200 border border-gray-200" onclick="toggleSidebarMobile()">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
</div>

<script>
    // ------------------- Desktop toggle -------------------
    let sidebarCollapsed = false;
    
    function toggleSidebar(){
        const sidebar = document.getElementById('sidebar');
        const logo = document.getElementById('sidebarLogo');
        const menuTexts = document.querySelectorAll('.menu-text');
        const userDetails = document.querySelector('.user-details');
        const footerInfo = document.querySelector('.footer-info');
        const collapseIcon = document.getElementById('collapseIcon');
        const settingsSubmenu = document.getElementById('settingsSubmenu');
        const mainContent = document.getElementById('mainContent');
        
        if(!sidebarCollapsed){
            // Collapse sidebar
            sidebar.style.width = '5rem';
            sidebar.classList.remove('w-72');
            sidebar.classList.add('w-20');
            
            // Hide text elements
            logo.classList.add('hidden');
            if(userDetails) userDetails.classList.add('hidden');
            if(footerInfo) footerInfo.classList.add('hidden');
            
            // Hide menu texts
            menuTexts.forEach(text => {
                text.classList.add('hidden');
            });
            
            // Hide settings submenu when collapsed
            if(settingsSubmenu) {
                settingsSubmenu.classList.add('hidden');
            }
            
            // Adjust padding for nav items
            document.querySelectorAll('#sidebarLinks a').forEach(a => {
                a.classList.add('justify-center');
                a.classList.remove('px-3');
                a.classList.add('px-0');
            });
            
            // Adjust settings submenu items
            document.querySelectorAll('.settings-submenu-item').forEach(item => {
                item.classList.add('justify-center');
                item.classList.remove('px-3');
                item.classList.add('px-0');
            });
            
            // Rotate collapse icon
            if(collapseIcon) {
                collapseIcon.style.transform = 'rotate(180deg)';
            }
            
            // Adjust main content margin
            if(mainContent) {
                mainContent.classList.remove('lg:ml-72');
                mainContent.classList.add('lg:ml-20');
            }
            
            sidebarCollapsed = true;
            
            // Dispatch event for main content
            window.dispatchEvent(new CustomEvent('sidebarCollapsed', { 
                detail: { collapsed: true } 
            }));
            
            // Save state
            localStorage.setItem('sidebarCollapsed', 'true');
        } else {
            // Expand sidebar
            sidebar.style.width = '18rem';
            sidebar.classList.remove('w-20');
            sidebar.classList.add('w-72');
            
            // Show text elements
            logo.classList.remove('hidden');
            if(userDetails) userDetails.classList.remove('hidden');
            if(footerInfo) footerInfo.classList.remove('hidden');
            
            // Show menu texts
            menuTexts.forEach(text => {
                text.classList.remove('hidden');
            });
            
            // Show settings submenu if settings is active
            const settingsLink = document.querySelector('a[data-page="settings.php"]');
            if(settingsLink && settingsLink.classList.contains('bg-blue-50')) {
                if(settingsSubmenu) {
                    settingsSubmenu.classList.remove('hidden');
                }
            }
            
            // Restore padding for nav items
            document.querySelectorAll('#sidebarLinks a').forEach(a => {
                a.classList.remove('justify-center');
                a.classList.add('px-3');
                a.classList.remove('px-0');
            });
            
            // Restore settings submenu items
            document.querySelectorAll('.settings-submenu-item').forEach(item => {
                item.classList.remove('justify-center');
                item.classList.add('px-3');
                item.classList.remove('px-0');
            });
            
            // Reset collapse icon rotation
            if(collapseIcon) {
                collapseIcon.style.transform = 'rotate(0deg)';
            }
            
            // Adjust main content margin
            if(mainContent) {
                mainContent.classList.remove('lg:ml-20');
                mainContent.classList.add('lg:ml-72');
            }
            
            sidebarCollapsed = false;
            
            // Dispatch event for main content
            window.dispatchEvent(new CustomEvent('sidebarCollapsed', { 
                detail: { collapsed: false } 
            }));
            
            // Save state
            localStorage.setItem('sidebarCollapsed', 'false');
        }
    }

    // ------------------- Mobile toggle -------------------
    function toggleSidebarMobile(){
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mobileButton = document.querySelector('.fixed.top-4.left-4');
        
        sidebar.classList.toggle('-translate-x-72');
        overlay.classList.toggle('hidden');
        
        // Toggle mobile button visibility
        if(mobileButton) {
            if(sidebar.classList.contains('-translate-x-72')) {
                mobileButton.style.opacity = '1';
                mobileButton.style.pointerEvents = 'auto';
            } else {
                mobileButton.style.opacity = '0';
                mobileButton.style.pointerEvents = 'none';
            }
        }
        
        // Reset collapsed state on mobile if expanded
        if(!sidebar.classList.contains('-translate-x-72') && sidebarCollapsed) {
            toggleSidebar();
        }
    }

    // ------------------- Settings Submenu Toggle -------------------
    function toggleSettingsSubmenu(show) {
        const submenu = document.getElementById('settingsSubmenu');
        const indicator = document.querySelector('.settings-submenu-indicator');
        
        if(!sidebarCollapsed && submenu) {
            if(show) {
                submenu.classList.remove('hidden');
                if(indicator) indicator.classList.remove('hidden');
            } else {
                submenu.classList.add('hidden');
                if(indicator) indicator.classList.add('hidden');
            }
        }
    }

    // ------------------- Handle Settings Click -------------------
    function handleSettingsClick(event) {
        // If sidebar is collapsed, expand it first
        if(sidebarCollapsed) {
            event.preventDefault();
            toggleSidebar();
            // Navigate after animation
            setTimeout(() => {
                window.location.href = 'settings.php';
            }, 300);
        }
    }

    // ------------------- Active Tab Highlighting -------------------
    function highlightActiveSettingsTab() {
        const hash = window.location.hash.substring(1);
        if(!hash) return;
        
        // Remove active class from all submenu items
        document.querySelectorAll('.settings-submenu-item').forEach(item => {
            item.classList.remove('text-blue-600', 'bg-blue-50');
            const dot = item.querySelector('span:first-child');
            if(dot) dot.classList.remove('bg-blue-600');
        });
        
        // Add active class to current tab
        const activeItem = document.querySelector(`.settings-submenu-item[data-tab="${hash}"]`);
        if(activeItem) {
            activeItem.classList.add('text-blue-600', 'bg-blue-50');
            const dot = activeItem.querySelector('span:first-child');
            if(dot) dot.classList.add('bg-blue-600');
        }
    }

    // Handle hash changes
    window.addEventListener('hashchange', function() {
        highlightActiveSettingsTab();
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mobileButton = document.querySelector('.fixed.top-4.left-4');
        const mainContent = document.getElementById('mainContent');
        
        if(window.innerWidth >= 1024) { // lg breakpoint
            overlay.classList.add('hidden');
            sidebar.classList.remove('-translate-x-72');
            
            if(mobileButton) {
                mobileButton.style.opacity = '0';
                mobileButton.style.pointerEvents = 'none';
            }
            
            // Ensure sidebar is in correct state based on collapse setting
            if(sidebarCollapsed) {
                sidebar.style.width = '5rem';
                sidebar.classList.add('w-20');
                sidebar.classList.remove('w-72');
                
                if(mainContent) {
                    mainContent.classList.add('lg:ml-20');
                    mainContent.classList.remove('lg:ml-72');
                }
            } else {
                sidebar.style.width = '18rem';
                sidebar.classList.add('w-72');
                sidebar.classList.remove('w-20');
                
                if(mainContent) {
                    mainContent.classList.add('lg:ml-72');
                    mainContent.classList.remove('lg:ml-20');
                }
                
                // Check if we need to show settings submenu
                const settingsLink = document.querySelector('a[data-page="settings.php"]');
                if(settingsLink && settingsLink.classList.contains('bg-blue-50')) {
                    const submenu = document.getElementById('settingsSubmenu');
                    if(submenu) submenu.classList.remove('hidden');
                }
            }
        } else {
            if(!sidebar.classList.contains('-translate-x-72')) {
                sidebar.classList.add('-translate-x-72');
            }
            
            if(mobileButton) {
                mobileButton.style.opacity = '1';
                mobileButton.style.pointerEvents = 'auto';
            }
            
            // Remove desktop margin on mobile
            if(mainContent) {
                mainContent.classList.remove('lg:ml-72', 'lg:ml-20');
            }
        }
    });

    // Initialize sidebar state on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Check if sidebar should be collapsed based on previous state
        const savedState = localStorage.getItem('sidebarCollapsed');
        if(savedState === 'true') {
            // Small delay to ensure DOM is ready
            setTimeout(() => toggleSidebar(), 100);
        }
        
        // Handle initial hash
        setTimeout(() => highlightActiveSettingsTab(), 200);
        
        // Close mobile sidebar when clicking a link
        document.querySelectorAll('#sidebarLinks a').forEach(link => {
            link.addEventListener('click', function() {
                if(window.innerWidth < 1024) {
                    setTimeout(() => toggleSidebarMobile(), 100);
                }
            });
        });
        
        // Initial resize check
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 150);
    });
</script>

<!-- Additional Styles for Sidebar -->
<style>
    /* Smooth transitions for sidebar */
    #sidebar {
        transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
    }
    
    #sidebar a {
        transition: all 0.2s ease;
    }
    
    /* Settings submenu animation */
    #settingsSubmenu {
        transition: all 0.2s ease;
        overflow: hidden;
    }
    
    /* Active link styles */
    #sidebarLinks a.bg-blue-50 {
        border-left: 3px solid #3b82f6;
    }
    
    /* Scrollbar styling */
    #sidebarLinks::-webkit-scrollbar {
        width: 4px;
    }
    
    #sidebarLinks::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    #sidebarLinks::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 4px;
    }
    
    #sidebarLinks::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }
    
    /* Collapsed state adjustments */
    #sidebar.w-20 #sidebarLinks a {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    
    #sidebar.w-20 .settings-submenu-item {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    
    /* Mobile menu button animation */
    .fixed.top-4.left-4 {
        animation: slideIn 0.3s ease-out;
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(-100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    /* Settings submenu indicator animation */
    .settings-submenu-indicator svg {
        transition: transform 0.2s ease;
    }
    
    .settings-submenu-indicator:not(.hidden) svg {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }
    
    /* Active settings tab dot */
    .settings-submenu-item .bg-blue-600 {
        transition: background-color 0.2s ease;
    }
    
    /* Hover effects */
    #sidebarLinks a:hover {
        transform: translateX(2px);
    }
    
    #sidebar.w-20 #sidebarLinks a:hover {
        transform: none;
    }
    
    /* Loading state for active tab */
    .settings-submenu-item.text-blue-600 {
        font-weight: 500;
    }
    
    /* Main content transition */
    #mainContent {
        transition: margin-left 0.3s ease-in-out;
    }
    
    /* Hide scrollbar when collapsed */
    #sidebar.w-20 #sidebarLinks {
        overflow-x: hidden;
    }
    
    /* Ensure icons are visible when collapsed */
    #sidebar.w-20 svg {
        margin: 0 auto;
    }
    
    /* Mobile button visibility */
    .fixed.top-4.left-4 {
        z-index: 45;
    }
</style>
</body>
</html>