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
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-4xl">
        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
            <div class="text-center mb-8">
                <div class="text-4xl text-white mb-4">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <h1 class="text-3xl font-bold text-white">Admin Panel</h1>
                <p class="text-white opacity-75 mt-2">DPS POS FBR Integrated - Management Dashboard</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- POS System -->
                <div class="bg-white bg-opacity-10 rounded-lg p-6 text-center">
                    <div class="text-3xl text-white mb-4">
                        <i class="fas fa-cash-register"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">POS System</h3>
                    <p class="text-white opacity-75 text-sm mb-4">Manage sales and transactions</p>
                    <button class="bg-white text-indigo-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-all">
                        <i class="fas fa-arrow-right mr-2"></i>Open POS
                    </button>
                </div>
                
                <!-- Inventory Management -->
                <div class="bg-white bg-opacity-10 rounded-lg p-6 text-center">
                    <div class="text-3xl text-white mb-4">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Inventory</h3>
                    <p class="text-white opacity-75 text-sm mb-4">Manage products and stock</p>
                    <button class="bg-white text-indigo-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-all">
                        <i class="fas fa-arrow-right mr-2"></i>Manage Inventory
                    </button>
                </div>
                
                <!-- FBR Integration -->
                <div class="bg-white bg-opacity-10 rounded-lg p-6 text-center">
                    <div class="text-3xl text-white mb-4">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">FBR Integration</h3>
                    <p class="text-white opacity-75 text-sm mb-4">Configure tax integration</p>
                    <button class="bg-white text-indigo-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-all">
                        <i class="fas fa-arrow-right mr-2"></i>Configure FBR
                    </button>
                </div>
                
                <!-- Reports -->
                <div class="bg-white bg-opacity-10 rounded-lg p-6 text-center">
                    <div class="text-3xl text-white mb-4">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Reports</h3>
                    <p class="text-white opacity-75 text-sm mb-4">View analytics and reports</p>
                    <button class="bg-white text-indigo-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-all">
                        <i class="fas fa-arrow-right mr-2"></i>View Reports
                    </button>
                </div>
                
                <!-- Settings -->
                <div class="bg-white bg-opacity-10 rounded-lg p-6 text-center">
                    <div class="text-3xl text-white mb-4">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Settings</h3>
                    <p class="text-white opacity-75 text-sm mb-4">Configure system settings</p>
                    <button class="bg-white text-indigo-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-all">
                        <i class="fas fa-arrow-right mr-2"></i>Open Settings
                    </button>
                </div>
                
                <!-- Users -->
                <div class="bg-white bg-opacity-10 rounded-lg p-6 text-center">
                    <div class="text-3xl text-white mb-4">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Users</h3>
                    <p class="text-white opacity-75 text-sm mb-4">Manage users and permissions</p>
                    <button class="bg-white text-indigo-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-all">
                        <i class="fas fa-arrow-right mr-2"></i>Manage Users
                    </button>
                </div>
            </div>
            
            <div class="mt-8 text-center">
                <div class="bg-green-500 bg-opacity-20 border border-green-400 rounded-lg p-4">
                    <div class="flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-400 text-xl mr-3"></i>
                        <div class="text-left">
                            <div class="text-white font-semibold">Installation Successful!</div>
                            <div class="text-white opacity-75 text-sm">DPS POS FBR Integrated is ready to use</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>