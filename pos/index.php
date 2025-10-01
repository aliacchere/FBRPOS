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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - DPS POS FBR Integrated</title>
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
<body class="min-h-screen">
    <!-- Header -->
    <div class="bg-white bg-opacity-10 backdrop-blur-md border-b border-white border-opacity-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <i class="fas fa-cash-register text-white text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-white">POS System</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-white opacity-75">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                    <a href="/admin/" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg transition-all">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Admin
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-white mb-2">Point of Sale System</h2>
            <p class="text-white opacity-75">Process sales and manage transactions</p>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Product Search -->
            <div class="lg:col-span-2">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-4">Product Search</h3>
                    <div class="relative">
                        <input type="text" 
                               placeholder="Search products by name, SKU, or barcode..." 
                               class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        <button class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white opacity-75">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    
                    <!-- Product Grid -->
                    <div class="mt-6 grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white mb-2">📱</div>
                            <div class="text-white font-medium">Sample Product</div>
                            <div class="text-white opacity-75 text-sm">Rs. 1,000</div>
                            <button class="mt-2 bg-white text-indigo-600 px-3 py-1 rounded text-sm hover:bg-gray-100 transition-all">
                                Add to Cart
                            </button>
                        </div>
                        
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white mb-2">💻</div>
                            <div class="text-white font-medium">Laptop</div>
                            <div class="text-white opacity-75 text-sm">Rs. 50,000</div>
                            <button class="mt-2 bg-white text-indigo-600 px-3 py-1 rounded text-sm hover:bg-gray-100 transition-all">
                                Add to Cart
                            </button>
                        </div>
                        
                        <div class="bg-white bg-opacity-10 rounded-lg p-4 text-center">
                            <div class="text-2xl text-white mb-2">⌚</div>
                            <div class="text-white font-medium">Watch</div>
                            <div class="text-white opacity-75 text-sm">Rs. 5,000</div>
                            <button class="mt-2 bg-white text-indigo-600 px-3 py-1 rounded text-sm hover:bg-gray-100 transition-all">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cart & Checkout -->
            <div class="lg:col-span-1">
                <div class="glass-effect rounded-lg p-6">
                    <h3 class="text-xl font-semibold text-white mb-4">Shopping Cart</h3>
                    
                    <!-- Cart Items -->
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between items-center text-white">
                            <span>Sample Product</span>
                            <span>Rs. 1,000</span>
                        </div>
                        <div class="flex justify-between items-center text-white">
                            <span>Laptop</span>
                            <span>Rs. 50,000</span>
                        </div>
                    </div>
                    
                    <!-- Total -->
                    <div class="border-t border-white border-opacity-20 pt-4 mb-6">
                        <div class="flex justify-between items-center text-white font-bold text-lg">
                            <span>Total:</span>
                            <span>Rs. 51,000</span>
                        </div>
                    </div>
                    
                    <!-- Payment Options -->
                    <div class="space-y-3">
                        <button class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg font-semibold transition-all">
                            <i class="fas fa-credit-card mr-2"></i>Cash Payment
                        </button>
                        <button class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg font-semibold transition-all">
                            <i class="fas fa-mobile-alt mr-2"></i>Card Payment
                        </button>
                        <button class="w-full bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-lg font-semibold transition-all">
                            <i class="fas fa-qrcode mr-2"></i>Mobile Payment
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-6">
            <button class="glass-effect rounded-lg p-6 text-center hover:bg-white hover:bg-opacity-20 transition-all">
                <div class="text-3xl text-white mb-2">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="text-white font-medium">Add Product</div>
            </button>
            
            <button class="glass-effect rounded-lg p-6 text-center hover:bg-white hover:bg-opacity-20 transition-all">
                <div class="text-3xl text-white mb-2">
                    <i class="fas fa-search"></i>
                </div>
                <div class="text-white font-medium">Search</div>
            </button>
            
            <button class="glass-effect rounded-lg p-6 text-center hover:bg-white hover:bg-opacity-20 transition-all">
                <div class="text-3xl text-white mb-2">
                    <i class="fas fa-print"></i>
                </div>
                <div class="text-white font-medium">Print Receipt</div>
            </button>
            
            <button class="glass-effect rounded-lg p-6 text-center hover:bg-white hover:bg-opacity-20 transition-all">
                <div class="text-3xl text-white mb-2">
                    <i class="fas fa-history"></i>
                </div>
                <div class="text-white font-medium">Sales History</div>
            </button>
        </div>
    </div>

    <script>
        // Simple POS functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('POS System loaded successfully!');
            
            // Add click handlers for demo buttons
            document.querySelectorAll('button').forEach(button => {
                button.addEventListener('click', function() {
                    if (this.textContent.includes('Add to Cart')) {
                        alert('Product added to cart! (Demo)');
                    } else if (this.textContent.includes('Payment')) {
                        alert('Payment processed! (Demo)');
                    } else if (this.textContent.includes('Add Product')) {
                        alert('Add Product feature coming soon!');
                    } else if (this.textContent.includes('Search')) {
                        alert('Search feature coming soon!');
                    } else if (this.textContent.includes('Print')) {
                        alert('Print Receipt feature coming soon!');
                    } else if (this.textContent.includes('Sales History')) {
                        alert('Sales History feature coming soon!');
                    }
                });
            });
        });
    </script>
</body>
</html>