<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
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
                        <span class="text-white font-bold text-xl">I</span>
                    </div>
                    <span id="sidebarLogo" class="text-xl font-semibold text-gray-800">InventoryPro</span>
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
                    AD
                </div>
                <div class="flex-1 min-w-0 user-details">
                    <p class="text-sm font-medium text-gray-800 truncate">Admin User</p>
                    <p class="text-xs text-gray-500 truncate">admin@inventory.com</p>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav id="sidebarLinks" class="flex-1 overflow-y-auto py-4 px-3">
            <div class="space-y-1">
                <!-- Dashboard -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span class="menu-text text-sm">Dashboard</span>
                </a>

                <!-- Products -->
                <a href="products.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span class="menu-text text-sm">Products</span>
                </a>

                <!-- Categories -->
                <a href="categories.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-5-5A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    <span class="menu-text text-sm">Categories</span>
                </a>

                <!-- Reports -->
                <a href="reports.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="menu-text text-sm">Reports</span>
                </a>
            </div>

            <!-- Settings Section -->
            <div class="mt-8 pt-4 border-t border-gray-200">
                <div class="space-y-1">
                    <!-- Settings -->
                    <a href="settings.php" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200 group">
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 transition-colors duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="menu-text text-sm">Settings</span>
                    </a>

                    <!-- Logout -->
                    <a href="#" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-gray-600 hover:text-red-600 hover:bg-red-50 transition-colors duration-200 group">
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
                <p class="mt-1">© 2024 InventoryPro</p>
            </div>
        </div>
    </div>

    <!-- Mobile Hamburger -->
    <button class="fixed top-4 left-4 z-50 bg-white text-gray-700 p-3 rounded-xl shadow-lg lg:hidden hover:shadow-xl transition-all duration-200 border border-gray-200" onclick="toggleSidebarMobile()">
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
            
            // Adjust padding for nav items
            document.querySelectorAll('#sidebarLinks a').forEach(a => {
                a.classList.add('justify-center');
                a.classList.remove('px-3');
                a.classList.add('px-0');
            });
            
            // Rotate collapse icon
            if(collapseIcon) {
                collapseIcon.style.transform = 'rotate(180deg)';
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
            
            // Restore padding for nav items
            document.querySelectorAll('#sidebarLinks a').forEach(a => {
                a.classList.remove('justify-center');
                a.classList.add('px-3');
                a.classList.remove('px-0');
            });
            
            // Reset collapse icon rotation
            if(collapseIcon) {
                collapseIcon.style.transform = 'rotate(0deg)';
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
        sidebar.classList.toggle('-translate-x-72');
        overlay.classList.toggle('hidden');
        
        // Reset collapsed state on mobile if expanded
        if(!sidebar.classList.contains('-translate-x-72') && sidebarCollapsed) {
            toggleSidebar();
        }
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if(window.innerWidth >= 1024) { // lg breakpoint
            overlay.classList.add('hidden');
            sidebar.classList.remove('-translate-x-72');
            
            // Ensure sidebar is in correct state based on collapse setting
            if(sidebarCollapsed) {
                sidebar.style.width = '5rem';
                sidebar.classList.add('w-20');
                sidebar.classList.remove('w-72');
            } else {
                sidebar.style.width = '18rem';
                sidebar.classList.add('w-72');
                sidebar.classList.remove('w-20');
            }
        } else {
            if(!sidebar.classList.contains('-translate-x-72')) {
                sidebar.classList.add('-translate-x-72');
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
    });

    // Active link highlighting based on current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop();
        const links = document.querySelectorAll('#sidebarLinks a');
        
        links.forEach(link => {
            const href = link.getAttribute('href');
            if(href === currentPage) {
                link.classList.add('bg-blue-50', 'text-blue-600');
                link.classList.remove('text-gray-600');
                
                // Also change icon color
                const icon = link.querySelector('svg');
                if(icon) {
                    icon.classList.add('text-blue-600');
                    icon.classList.remove('text-gray-400');
                }
            }
        });
    });
</script>
</body>
</html>