<?php
function renderSidebar($activePage = '') {
?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-boxes-stacked logo-icon"></i> 
                <span class="logo-text">STOCKPORT</span>
            </div>
            <button class="toggle-btn" id="sidebarToggle" title="Toggle Sidebar">
                <i class="fas fa-angles-left"></i>
            </button>
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="overview.php" class="nav-link <?php echo ($activePage === 'overview') ? 'active' : ''; ?>" title="Overview">
                    <i class="fas fa-chart-line"></i> <span class="nav-text">Overview</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="inventory.php" class="nav-link <?php echo ($activePage === 'inventory') ? 'active' : ''; ?>" title="Inventory">
                    <i class="fas fa-box"></i> <span class="nav-text">Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="employee_request.php" class="nav-link <?php echo ($activePage === 'employee_request') ? 'active' : ''; ?>" title="My Requests">
                    <i class="fas fa-tasks"></i> <span class="nav-text">My Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="overall_processing.php" class="nav-link <?php echo ($activePage === 'overall_processing') ? 'active' : ''; ?>" title="Easy Processing">
                    <i class="fas fa-box"></i> <span class="nav-text">Easy Processing</span>
                </a>
            </li>
            
            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle <?php echo (in_array($activePage, ['rawMaterialOrder', 'materialOrderHistory', 'clientOrderAdd', 'clientOrderTracker', 'deliverproducts'])) ? 'active' : ''; ?>" title="Request Processing">
                    <i class="fas fa-clipboard-list"></i> <span class="nav-text">Request Processing</span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                    <span class="submenu-indicator"></span>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a href="materialOrderAdd.php" class="nav-link <?php echo ($activePage === 'rawMaterialOrder') ? 'active' : ''; ?>" title="Order Materials">
                            <i class="fas fa-cart-plus"></i> <span class="nav-text">Order Materials</span>
                        </a>
                    </li>
                    <li>
                        <a href="materialOrderHistory.php" class="nav-link <?php echo ($activePage === 'materialOrderHistory') ? 'active' : ''; ?>" title="Order History">
                            <i class="fas fa-history"></i> <span class="nav-text">Order History</span>
                        </a>
                    </li>
                    <li>
                        <a href="clientOrderAdd.php" class="nav-link <?php echo ($activePage === 'clientOrderAdd') ? 'active' : ''; ?>" title="New Client Order">
                            <i class="fas fa-file-circle-plus"></i> <span class="nav-text">New Client Order</span>
                        </a>
                    </li>
                    <li>
                        <a href="clientOrderTracker.php" class="nav-link <?php echo ($activePage === 'clientOrderTracker') ? 'active' : ''; ?>" title="Order Tracker">
                            <i class="fas fa-truck-fast"></i> <span class="nav-text">Order Tracker</span>
                        </a>
                    </li>
                    <li>
                        <a href="deliverproducts.php" class="nav-link <?php echo ($activePage === 'deliverproducts') ? 'active' : ''; ?>" title="Deliver Products">
                            <i class="fas fa-box-open"></i> <span class="nav-text">Deliver Products</span>
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle <?php echo (in_array($activePage, ['warehouse', 'manage_storage'])) ? 'active' : ''; ?>" title="Warehouse Operations">
                    <i class="fas fa-warehouse"></i> <span class="nav-text">Warehouse Operations</span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                    <span class="submenu-indicator"></span>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a href="warehouse.php" class="nav-link <?php echo ($activePage === 'warehouse') ? 'active' : ''; ?>" title="Overview">
                            <i class="fas fa-box-open"></i> <span class="nav-text">Overview</span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_storage.php" class="nav-link <?php echo ($activePage === 'manage_storage') ? 'active' : ''; ?>" title="Manage Storage">
                            <i class="fas fa-exchange-alt"></i> <span class="nav-text">Manage Storage</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a href="employee_profile.php" class="nav-link <?php echo ($activePage === 'employee_profile') ? 'active' : ''; ?>" title="Employee Profile">
                    <i class="fas fa-user"></i> <span class="nav-text">Employee Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="message_form.php" class="nav-link <?php echo ($activePage === 'message_form') ? 'active' : ''; ?>" title="Message Admins">
                    <i class="fas fa-envelope"></i> <span class="nav-text">Admin Support</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <button class="collapse-btn" id="fullCollapseBtn" title="Hide Sidebar">
                <i class="fas fa-angles-left"></i>
            </button>
        </div>
    </div>
    
    <!-- Mini sidebar that appears when sidebar is fully collapsed -->
    <div class="mini-sidebar" id="miniSidebar">
        <button class="expand-btn" id="expandBtn" title="Show Sidebar">
            <i class="fas fa-angles-right"></i>
        </button>
    </div>

    <style>
        /* Main content adjustment */
        body {
            margin: 0;
            padding: 0;
            transition: margin-left 0.3s ease;
            position: relative; /* Added for better stacking context */
        }
        
        .content-wrapper {
            margin-left: 55px; /* Collapsed sidebar width - updated to be slightly narrower */
            transition: margin-left 0.3s ease;
            padding: 20px;
            position: relative; /* Added for proper tab handling */
            z-index: 1; /* Lower than sidebar but higher than base */
        }
        
        body.sidebar-expanded .content-wrapper {
            margin-left: 220px; /* Expanded sidebar width */
        }
        
        body.sidebar-hidden .content-wrapper {
            margin-left: 20px; /* Fully collapsed state */
        }
        
        /* Tab content specific styles */
        .tab-content {
            position: relative;
            z-index: 1;
        }
        
        .nav-tabs {
            position: relative;
            z-index: 2;
        }
        
        /* Sidebar styles with updated z-index */
        .sidebar {
            background: #1a1a1a;
            width: 55px; /* Collapsed width - updated to be narrower */
            min-height: 100vh;
            padding: 0;
            color: #e1e1e1;
            transition: all 0.3s ease;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 10; /* Changed to be lower than modals but higher than content */
            overflow: visible; /* Changed to allow dropdowns to overflow */
        }
        
        .sidebar.expanded {
            width: 220px; /* Expanded width - slightly wider for comfort */
        }
        
        .sidebar.hidden {
            left: -70px; /* Move offscreen when fully collapsed */
            box-shadow: none;
        }
        
        /* Mini sidebar that appears when main sidebar is hidden */
        .mini-sidebar {
            position: fixed;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            background: #1a1a1a;
            width: 20px;
            height: 40px;
            border-radius: 0 4px 4px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9; /* Slightly lower than main sidebar */
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        
        body.sidebar-hidden .mini-sidebar {
            opacity: 1;
            pointer-events: all;
        }
        
        .expand-btn {
            background: transparent;
            color: #4CAF50;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            padding: 0;
        }
        
        /* Header styles - UPDATED for logo icon disappearance */
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 12px;
            border-bottom: 1px solid #333;
            height: 50px;
            box-sizing: border-box;
            position: relative;
        }
        
        .logo {
            display: flex;
            align-items: center;
            position: relative;
            width: 100%;
            height: 12px;
            margin-bottom: 0px;
        }
        
        .logo-icon {
            color: #4CAF50;
            font-size: 1.2rem;
            position: absolute;
            left: 0;
            transition: all 0.3s ease;
            opacity: 0; /* Hidden by default */
        }
        
        .sidebar.expanded .logo-icon {
            opacity: 1; /* Only visible when expanded */
            left: 0;
        }
        
        .logo-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: #e1e1e1;
            margin-left: 30px;
            opacity: 0;
            transition: opacity 0.3s ease;
            white-space: nowrap;
        }
        
        .sidebar.expanded .logo-text {
            opacity: 1;
        }

        .toggle-btn {
            background: transparent;
            border: none;
            color: #e1e1e1;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            right: 12px;
            z-index: 2;
        }
        
        .sidebar.expanded .toggle-btn i {
            transform: rotate(180deg);
        }
        
        .toggle-btn:hover {
            color: #4CAF50;
        }
        
        /* Footer with collapse button */
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 10px 0;
            display: flex;
            justify-content: center;
            border-top: 1px solid #333;
        }
        
        .collapse-btn {
            background: transparent;
            border: none;
            color: #e1e1e1;
            cursor: pointer;
            padding: 5px;
            font-size: 0.8rem;
        }
        
        .collapse-btn:hover {
            color: #4CAF50;
        }
        
        /* Navigation menu - MODIFIED for better handling */
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 15px 0 60px 0; /* Bottom margin for footer */
            overflow: hidden; /* Changed from overflow-y: auto */
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #e1e1e1;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.85rem;
            border-left: 3px solid transparent;
            white-space: nowrap;
            position: relative; /* For the tooltip */
        }
        
        .nav-link i {
            min-width: 20px;
            margin-right: 10px;
            font-size: 1rem;
            text-align: center;
        }
        
        .sidebar:not(.expanded) .nav-link i {
            margin: 0 auto;
        }
        
        .nav-text {
            opacity: 0;
            transition: opacity 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar.expanded .nav-text {
            opacity: 1;
        }
        
        .nav-link:hover {
            background: #2d2d2d;
            border-left-color: #4CAF50;
            color: #4CAF50; /* Updated text color to green when hovered */
        }
        
        .nav-link:hover i {
            color: #4CAF50; /* Ensure icon turns green on hover */
        }
        
        .nav-link.active {
            background: #2d2d2d;
            border-left-color: #4CAF50;
            color: #4CAF50;
        }
        
        /* Tooltip for collapsed state */
        .nav-link::after {
            content: attr(title);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: #333;
            padding: 5px 10px;
            border-radius: 4px;
            white-space: nowrap;
            z-index: 11; /* Higher than sidebar */
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
            transform-origin: left center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .sidebar:not(.expanded) .nav-link:hover::after {
            opacity: 1;
            transform: translateY(-50%) translateX(5px);
        }
        
        /* Dropdown menu - UPDATED for better centering when collapsed */
        .dropdown-menu {
            display: none;
            background:rgb(74, 74, 74);
            padding: 5px 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            position: relative;
            z-index: 12; /* Higher than tooltip */
        }
        
        .dropdown.active .dropdown-menu {
            display: block;
            max-height: 400px;
            transition: max-height 0.3s ease-in;
        }
        
        .dropdown-menu .nav-link {
            padding: 10px 15px 10px 35px;
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        /* Center submenu items when collapsed - FIXED positioning */
        .sidebar:not(.expanded) .dropdown-menu .nav-link {
            padding: 10px 0;
            padding-left: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%; /* Ensure full width */
            text-align: center;
        }
        
        .sidebar:not(.expanded) .dropdown-menu .nav-link i {
            margin: 0 auto;
            position: absolute;
            left: 16px;
            transform: translateX(-50%);
            display: flex;
            min-width: 20px; /* Ensure consistent width */
            padding-left: 16px;
        }
        
        .dropdown-menu .nav-link:hover {
            background: #0e0e0e; /* Slightly lighter than parent but still darker than main menu */
            opacity: 1;
        }
        
        .sidebar.expanded .dropdown-menu .nav-link {
            padding-left: 35px;
        }

        .sidebar.expanded .dropdown-menu .nav-link:hover {
            padding-left: 35px;
        }
        
        .dropdown-toggle {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar:not(.expanded) .dropdown-toggle {
            justify-content: center;
        }
        
        .dropdown-arrow {
            font-size: 0.7em;
            opacity: 0.7;
            transition: transform 0.3s ease;
            margin-left: auto;
        }
        
        .sidebar:not(.expanded) .dropdown-arrow {
            display: none;
        }
        
        .dropdown.active .dropdown-arrow {
            transform: rotate(180deg);
        }
        
        .dropdown.active .nav-link.dropdown-toggle {
            background: #2d2d2d;
            border-left-color: #4CAF50;
        }
        
        /* Submenu indicator for collapsed state - CENTERED */
        .submenu-indicator {
            display: none;
            width: 15px;
            height: 2px;
            background-color: #4CAF50;
            position: absolute;
            bottom: 6px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .sidebar:not(.expanded) .dropdown .submenu-indicator {
            display: block;
        }
        
        /* Animation refinements */
        .dropdown-menu {
            transition: all 0.3s ease-in-out;
        }
        
        .nav-link:hover i {
            transform: scale(1.1);
            transition: transform 0.2s ease;
            color: #4CAF50;
        }
        
        .nav-item {
            margin: 2px 0;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const fullCollapseBtn = document.getElementById('fullCollapseBtn');
            const expandBtn = document.getElementById('expandBtn');
            const miniSidebar = document.getElementById('miniSidebar');
            const body = document.body;
            
            // Get current page from URL
            const currentPage = window.location.pathname.split('/').pop();
            
            // Set initial state based on saved preference
            const sidebarState = localStorage.getItem('sidebarState') || 'collapsed';
            
            if (sidebarState === 'expanded') {
                sidebar.classList.add('expanded');
                body.classList.add('sidebar-expanded');
            } else if (sidebarState === 'hidden') {
                sidebar.classList.add('hidden');
                body.classList.add('sidebar-hidden');
            }
            
            // Auto-activate dropdowns if a child page is active
            const checkActiveChild = function() {
                document.querySelectorAll('.dropdown').forEach(dropdown => {
                    const childLinks = dropdown.querySelectorAll('.dropdown-menu .nav-link');
                    for (let i = 0; i < childLinks.length; i++) {
                        const href = childLinks[i].getAttribute('href');
                        if (href && currentPage.includes(href.replace('.php', ''))) {
                            dropdown.classList.add('active');
                            break;
                        }
                    }
                });
            };
            
            checkActiveChild();
            
            // Toggle sidebar expand/collapse
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('expanded');
                body.classList.toggle('sidebar-expanded');
                
                if (sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('hidden');
                    body.classList.remove('sidebar-hidden');
                }
                
                // Save preference
                localStorage.setItem('sidebarState', 
                    sidebar.classList.contains('expanded') ? 'expanded' : 'collapsed');
            });
            
            // Fully collapse sidebar
            fullCollapseBtn.addEventListener('click', function() {
                sidebar.classList.add('hidden');
                sidebar.classList.remove('expanded');
                body.classList.add('sidebar-hidden');
                body.classList.remove('sidebar-expanded');
                
                // Save preference
                localStorage.setItem('sidebarState', 'hidden');
            });
            
            // Expand from fully collapsed state
            expandBtn.addEventListener('click', function() {
                sidebar.classList.remove('hidden');
                body.classList.remove('sidebar-hidden');
                
                // Optionally also expand the sidebar
                sidebar.classList.add('expanded');
                body.classList.add('sidebar-expanded');
                
                // Save preference
                localStorage.setItem('sidebarState', 'expanded');
            });
            
            // Preserve dropdown state in localStorage
            const saveDropdownState = function() {
                const activeDropdowns = [];
                document.querySelectorAll('.dropdown.active').forEach(dropdown => {
                    const index = Array.from(dropdown.parentNode.children).indexOf(dropdown);
                    activeDropdowns.push(index);
                });
                localStorage.setItem('activeDropdowns', JSON.stringify(activeDropdowns));
            };
            
            // Restore dropdown state
            const restoreDropdownState = function() {
                try {
                    const activeDropdowns = JSON.parse(localStorage.getItem('activeDropdowns')) || [];
                    const allDropdowns = document.querySelectorAll('.dropdown');
                    activeDropdowns.forEach(index => {
                        if (allDropdowns[index]) {
                            allDropdowns[index].classList.add('active');
                        }
                    });
                } catch (e) {
                    console.error('Error restoring dropdown state:', e);
                }
            };
            
            // Apply restored state if not auto-activated
            if (!document.querySelector('.dropdown.active')) {
                restoreDropdownState();
            }
            
            // Handle dropdown menus
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const dropdown = this.closest('.dropdown');
                    dropdown.classList.toggle('active');
                    
                    // If sidebar is collapsed, expand it when opening dropdown
                    if (!sidebar.classList.contains('expanded') && dropdown.classList.contains('active')) {
                        sidebar.classList.add('expanded');
                        body.classList.add('sidebar-expanded');
                        localStorage.setItem('sidebarState', 'expanded');
                    }
                    
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown.active').forEach(openDropdown => {
                        if (openDropdown !== dropdown) {
                            openDropdown.classList.remove('active');
                        }
                    });
                    
                    // Save dropdown state
                    saveDropdownState();
                });
            });
            
            // Handle page navigation and state persistence
            document.querySelectorAll('.nav-link:not(.dropdown-toggle)').forEach(link => {
                link.addEventListener('click', function() {
                    // Save current sidebar and dropdown state before navigation
                    if (sidebar.classList.contains('expanded')) {
                        localStorage.setItem('sidebarState', 'expanded');
                    } else if (sidebar.classList.contains('hidden')) {
                        localStorage.setItem('sidebarState', 'hidden');
                    } else {
                        localStorage.setItem('sidebarState', 'collapsed');
                    }
                    
                    saveDropdownState();
                });
            });
            
            // Add hover intent for better UX - but only if not persistent expanded state
            let hoverTimer;
            sidebar.addEventListener('mouseenter', function() {
                if (!sidebar.classList.contains('expanded') && 
                    !sidebar.classList.contains('hidden') &&
                    localStorage.getItem('sidebarState') !== 'expanded') {
                    hoverTimer = setTimeout(function() {
                        sidebar.classList.add('expanded');
                        body.classList.add('sidebar-expanded');
                    }, 300);
                }
            });
            
            sidebar.addEventListener('mouseleave', function() {
                clearTimeout(hoverTimer);
                if (sidebar.classList.contains('expanded') && 
                    localStorage.getItem('sidebarState') !== 'expanded') {
                    sidebar.classList.remove('expanded');
                    body.classList.remove('sidebar-expanded');
                }
            });
        });
    </script>
<?php
}
?>