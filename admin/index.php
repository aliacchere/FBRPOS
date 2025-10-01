<?php
require_once '../includes/auth.php';

// Check if user is logged in
try {
    $pdo = getDatabaseConnection();
    $auth = new Auth($pdo);
    $auth->requireLogin();
    $user = $auth->getCurrentUser();
} catch (Exception $e) {
    header('Location: /login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: /login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - DPS POS FBR Integrated</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Header -->
    <div class="bg-white bg-opacity-10 backdrop-blur-md border-b border-white border-opacity-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <i class="fas fa-cash-register text-white text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-white">DPS POS FBR Integrated</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-white opacity-75">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                    <a href="?logout=1" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-all">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-white mb-2">Admin Dashboard</h2>
            <p class="text-white opacity-75">Manage your DPS POS FBR Integrated system</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- POS System -->
            <div class="glass-effect rounded-lg p-6 text-center card-hover">
                <div class="text-4xl text-white mb-4">
                    <i class="fas fa-cash-register"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">POS System</h3>
                <p class="text-white opacity-75 text-sm mb-4">Manage sales and transactions</p>
                <button onclick="openPOS()" class="bg-white text-indigo-600 px-6 py-2 rounded-lg hover:bg-gray-100 transition-all">
                    <i class="fas fa-arrow-right mr-2"></i>Open POS
                </button>
            </div>
            
            <!-- Inventory Management -->
            <div class="glass-effect rounded-lg p-6 text-center card-hover">
                <div class="text-4xl text-white mb-4">
                    <i class="fas fa-boxes"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Inventory</h3>
                <p class="text-white opacity-75 text-sm mb-4">Manage products and stock</p>
                <button onclick="openInventory()" class="bg-white text-indigo-600 px-6 py-2 rounded-lg hover:bg-gray-100 transition-all">
                    <i class="fas fa-arrow-right mr-2"></i>Manage Inventory
                </button>
            </div>
            
            <!-- FBR Integration -->
            <div class="glass-effect rounded-lg p-6 text-center card-hover">
                <div class="text-4xl text-white mb-4">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">FBR Integration</h3>
                <p class="text-white opacity-75 text-sm mb-4">Configure tax integration</p>
                <button onclick="openFBR()" class="bg-white text-indigo-600 px-6 py-2 rounded-lg hover:bg-gray-100 transition-all">
                    <i class="fas fa-arrow-right mr-2"></i>Configure FBR
                </button>
            </div>
            
            <!-- Reports -->
            <div class="glass-effect rounded-lg p-6 text-center card-hover">
                <div class="text-4xl text-white mb-4">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Reports</h3>
                <p class="text-white opacity-75 text-sm mb-4">View analytics and reports</p>
                <button onclick="openReports()" class="bg-white text-indigo-600 px-6 py-2 rounded-lg hover:bg-gray-100 transition-all">
                    <i class="fas fa-arrow-right mr-2"></i>View Reports
                </button>
            </div>
            
            <!-- Settings -->
            <div class="glass-effect rounded-lg p-6 text-center card-hover">
                <div class="text-4xl text-white mb-4">
                    <i class="fas fa-cog"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Settings</h3>
                <p class="text-white opacity-75 text-sm mb-4">Configure system settings</p>
                <button onclick="openSettings()" class="bg-white text-indigo-600 px-6 py-2 rounded-lg hover:bg-gray-100 transition-all">
                    <i class="fas fa-arrow-right mr-2"></i>Open Settings
                </button>
            </div>
            
            <!-- Customers -->
            <div class="glass-effect rounded-lg p-6 text-center card-hover">
                <div class="text-4xl text-white mb-4">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Customers</h3>
                <p class="text-white opacity-75 text-sm mb-4">Manage customers and profiles</p>
                <button onclick="openCustomers()" class="bg-white text-indigo-600 px-6 py-2 rounded-lg hover:bg-gray-100 transition-all">
                    <i class="fas fa-arrow-right mr-2"></i>Manage Customers
                </button>
            </div>
            
            <!-- Users -->
            <div class="glass-effect rounded-lg p-6 text-center card-hover">
                <div class="text-4xl text-white mb-4">
                    <i class="fas fa-user-cog"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Users</h3>
                <p class="text-white opacity-75 text-sm mb-4">Manage users and permissions</p>
                <button onclick="openUsers()" class="bg-white text-indigo-600 px-6 py-2 rounded-lg hover:bg-gray-100 transition-all">
                    <i class="fas fa-arrow-right mr-2"></i>Manage Users
                </button>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="glass-effect rounded-lg p-6 text-center">
                <div class="text-2xl text-white font-bold">0</div>
                <div class="text-white opacity-75 text-sm">Total Sales</div>
            </div>
            <div class="glass-effect rounded-lg p-6 text-center">
                <div class="text-2xl text-white font-bold">0</div>
                <div class="text-white opacity-75 text-sm">Products</div>
            </div>
            <div class="glass-effect rounded-lg p-6 text-center">
                <div class="text-2xl text-white font-bold">0</div>
                <div class="text-white opacity-75 text-sm">Customers</div>
            </div>
            <div class="glass-effect rounded-lg p-6 text-center">
                <div class="text-2xl text-white font-bold">Rs. 0</div>
                <div class="text-white opacity-75 text-sm">Revenue</div>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="mt-8">
            <div class="glass-effect rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">System Status</h3>
                        <p class="text-white opacity-75 text-sm">All systems operational</p>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-400 rounded-full mr-2"></div>
                        <span class="text-white">Online</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openPOS() {
            window.location.href = '/pos/';
        }
        
        function openInventory() {
            window.location.href = '/inventory/';
        }
        
        function openFBR() {
            alert('FBR Integration settings will be available in the next update!');
        }
        
        function openReports() {
            window.location.href = '/reports/';
        }
        
        function openSettings() {
            alert('Settings panel will be available in the next update!');
        }
        
        function openCustomers() {
            window.location.href = '/customers/';
        }
        
        function openUsers() {
            alert('User management will be available in the next update!');
        }
    </script>
</body>
</html>