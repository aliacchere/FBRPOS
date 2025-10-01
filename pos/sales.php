<?php
/**
 * DPS POS FBR Integrated - POS Sales Interface
 * Modern, mobile-first POS interface for cashiers
 */

session_start();
require_once '../config/database.php';
require_once '../config/app.php';
require_once '../includes/functions.php';
require_once '../includes/fbr_engine.php';

// Check authentication and role
require_login();
require_role(['tenant_admin', 'cashier']);

// Get tenant information
$tenant = get_current_tenant();
if (!$tenant) {
    header('Location: ../login.php?error=Tenant not found');
    exit;
}

// Get products for the tenant
$products = db_fetch_all("
    SELECT p.*, c.name as category_name 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.tenant_id = ? AND p.is_active = 1
    ORDER BY p.name
", [$tenant['id']]);

// Get categories for filtering
$categories = db_fetch_all("
    SELECT * FROM categories 
    WHERE tenant_id = ? AND is_active = 1
    ORDER BY name
", [$tenant['id']]);

// Get recent customers
$customers = db_fetch_all("
    SELECT * FROM customers 
    WHERE tenant_id = ? AND is_active = 1
    ORDER BY name
    LIMIT 20
", [$tenant['id']]);

// Get today's sales
$today_sales = db_fetch_all("
    SELECT s.*, u.name as cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.tenant_id = ? AND DATE(s.created_at) = CURDATE()
    ORDER BY s.created_at DESC
    LIMIT 10
", [$tenant['id']]);

// Get today's statistics
$today_stats = db_fetch("
    SELECT 
        COUNT(*) as total_sales,
        SUM(total_amount) as total_revenue,
        SUM(CASE WHEN fbr_status = 'synced' THEN 1 ELSE 0 END) as synced_sales,
        SUM(CASE WHEN fbr_status = 'pending' THEN 1 ELSE 0 END) as pending_sales
    FROM sales 
    WHERE tenant_id = ? AND DATE(created_at) = CURDATE()
", [$tenant['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Sales - <?php echo htmlspecialchars($tenant['business_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .product-card {
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .cart-item {
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white bg-opacity-10 backdrop-blur-md border-b border-white border-opacity-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-cash-register text-2xl text-white mr-3"></i>
                        <span class="text-xl font-bold text-white"><?php echo htmlspecialchars($tenant['business_name']); ?></span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-white text-sm">
                        <i class="fas fa-user mr-1"></i>
                        <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </div>
                    <div class="text-white text-sm">
                        <i class="fas fa-calendar mr-1"></i>
                        <?php echo date('d/m/Y H:i'); ?>
                    </div>
                    <a href="../auth/logout.php" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Today's Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="glass-effect rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-white"><?php echo number_format($today_stats['total_sales']); ?></div>
                <div class="text-white opacity-75 text-sm">Today's Sales</div>
            </div>
            <div class="glass-effect rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-white"><?php echo format_currency($today_stats['total_revenue']); ?></div>
                <div class="text-white opacity-75 text-sm">Today's Revenue</div>
            </div>
            <div class="glass-effect rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-green-400"><?php echo number_format($today_stats['synced_sales']); ?></div>
                <div class="text-white opacity-75 text-sm">FBR Synced</div>
            </div>
            <div class="glass-effect rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-yellow-400"><?php echo number_format($today_stats['pending_sales']); ?></div>
                <div class="text-white opacity-75 text-sm">Pending Sync</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Products Grid -->
            <div class="lg:col-span-2">
                <div class="glass-effect rounded-xl p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white">Products</h2>
                        <div class="flex space-x-2">
                            <select id="categoryFilter" class="px-3 py-2 rounded-lg border-0 bg-white bg-opacity-20 text-white">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="productSearch" placeholder="Search products..." 
                                   class="px-3 py-2 rounded-lg border-0 bg-white bg-opacity-20 text-white placeholder-white placeholder-opacity-75">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="productsGrid">
                        <?php foreach ($products as $product): ?>
                        <div class="product-card bg-white bg-opacity-10 rounded-lg p-4 cursor-pointer" 
                             data-product-id="<?php echo $product['id']; ?>"
                             data-name="<?php echo htmlspecialchars($product['name']); ?>"
                             data-price="<?php echo $product['price']; ?>"
                             data-category="<?php echo $product['category_id']; ?>">
                            <div class="text-center">
                                <?php if ($product['image']): ?>
                                <img src="../uploads/products/<?php echo $product['image']; ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     class="w-16 h-16 object-cover rounded-lg mx-auto mb-2">
                                <?php else: ?>
                                <div class="w-16 h-16 bg-white bg-opacity-20 rounded-lg mx-auto mb-2 flex items-center justify-center">
                                    <i class="fas fa-box text-2xl text-white"></i>
                                </div>
                                <?php endif; ?>
                                <div class="text-white font-semibold text-sm mb-1"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="text-white opacity-75 text-xs"><?php echo format_currency($product['price']); ?></div>
                                <div class="text-white opacity-50 text-xs">Stock: <?php echo $product['stock_quantity']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Shopping Cart -->
            <div class="lg:col-span-1">
                <div class="glass-effect rounded-xl p-6">
                    <h2 class="text-xl font-bold text-white mb-6">Shopping Cart</h2>
                    
                    <div id="cartItems" class="space-y-3 mb-6 max-h-96 overflow-y-auto">
                        <div class="text-center text-white opacity-75 py-8">
                            <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                            <p>Your cart is empty</p>
                        </div>
                    </div>

                    <div class="border-t border-white border-opacity-20 pt-4">
                        <div class="flex justify-between text-white mb-2">
                            <span>Subtotal:</span>
                            <span id="cartSubtotal"><?php echo format_currency(0); ?></span>
                        </div>
                        <div class="flex justify-between text-white mb-2">
                            <span>Tax:</span>
                            <span id="cartTax"><?php echo format_currency(0); ?></span>
                        </div>
                        <div class="flex justify-between text-white font-bold text-lg mb-4">
                            <span>Total:</span>
                            <span id="cartTotal"><?php echo format_currency(0); ?></span>
                        </div>

                        <div class="space-y-3">
                            <button id="checkoutBtn" 
                                    class="w-full bg-white text-indigo-600 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled>
                                <i class="fas fa-credit-card mr-2"></i>Checkout
                            </button>
                            <button id="clearCartBtn" 
                                    class="w-full bg-red-500 text-white py-2 rounded-lg font-semibold hover:bg-red-600 transition-all">
                                <i class="fas fa-trash mr-2"></i>Clear Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="mt-8">
            <div class="glass-effect rounded-xl p-6">
                <h3 class="text-xl font-bold text-white mb-6">Today's Recent Sales</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-white">
                        <thead>
                            <tr class="border-b border-white border-opacity-20">
                                <th class="text-left py-3 px-4">Invoice #</th>
                                <th class="text-left py-3 px-4">Cashier</th>
                                <th class="text-right py-3 px-4">Amount</th>
                                <th class="text-center py-3 px-4">FBR Status</th>
                                <th class="text-left py-3 px-4">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_sales as $sale): ?>
                            <tr class="border-b border-white border-opacity-10">
                                <td class="py-3 px-4 font-semibold"><?php echo htmlspecialchars($sale['invoice_number']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                                <td class="py-3 px-4 text-right font-bold"><?php echo format_currency($sale['total_amount']); ?></td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full <?php 
                                        echo $sale['fbr_status'] === 'synced' ? 'bg-green-500 text-white' : 
                                            ($sale['fbr_status'] === 'pending' ? 'bg-yellow-500 text-white' : 'bg-red-500 text-white'); 
                                    ?>">
                                        <?php echo ucfirst($sale['fbr_status']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm opacity-75"><?php echo format_datetime($sale['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div id="checkoutModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="glass-effect rounded-xl p-8 max-w-md w-full mx-4">
            <h3 class="text-2xl font-bold text-white mb-6 text-center">Checkout</h3>
            
            <form id="checkoutForm" class="space-y-4">
                <div>
                    <label class="block text-white font-medium mb-2">Customer Name</label>
                    <input type="text" name="customer_name" id="customerName" 
                           class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500"
                           placeholder="Walk-in Customer">
                </div>

                <div>
                    <label class="block text-white font-medium mb-2">Customer Phone</label>
                    <input type="tel" name="customer_phone" id="customerPhone" 
                           class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500"
                           placeholder="+92 300 1234567">
                </div>

                <div>
                    <label class="block text-white font-medium mb-2">Payment Method</label>
                    <select name="payment_method" id="paymentMethod" 
                            class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="easypaisa">Easypaisa</option>
                        <option value="jazzcash">JazzCash</option>
                    </select>
                </div>

                <div>
                    <label class="block text-white font-medium mb-2">Notes</label>
                    <textarea name="notes" id="notes" rows="3" 
                              class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500"
                              placeholder="Additional notes..."></textarea>
                </div>

                <div class="flex space-x-4">
                    <button type="button" id="cancelCheckout" 
                            class="flex-1 bg-gray-500 text-white py-3 rounded-lg font-semibold hover:bg-gray-600 transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="processSale" 
                            class="flex-1 bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition-all">
                        <i class="fas fa-check mr-2"></i>Process Sale
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Shopping cart functionality
        let cart = [];
        let cartTotal = 0;
        let cartSubtotal = 0;
        let cartTax = 0;

        // Product click handler
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const name = this.dataset.name;
                const price = parseFloat(this.dataset.price);
                
                addToCart(productId, name, price);
            });
        });

        function addToCart(productId, name, price) {
            const existingItem = cart.find(item => item.productId === productId);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    productId: productId,
                    name: name,
                    price: price,
                    quantity: 1
                });
            }
            
            updateCartDisplay();
        }

        function removeFromCart(productId) {
            cart = cart.filter(item => item.productId !== productId);
            updateCartDisplay();
        }

        function updateQuantity(productId, quantity) {
            const item = cart.find(item => item.productId === productId);
            if (item) {
                if (quantity <= 0) {
                    removeFromCart(productId);
                } else {
                    item.quantity = quantity;
                    updateCartDisplay();
                }
            }
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="text-center text-white opacity-75 py-8">
                        <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                        <p>Your cart is empty</p>
                    </div>
                `;
                document.getElementById('checkoutBtn').disabled = true;
            } else {
                cartItems.innerHTML = cart.map(item => `
                    <div class="cart-item bg-white bg-opacity-10 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="text-white font-semibold text-sm">${item.name}</div>
                                <div class="text-white opacity-75 text-xs">${formatCurrency(item.price)} each</div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="updateQuantity('${item.productId}', ${item.quantity - 1})" 
                                        class="w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="text-white font-semibold w-8 text-center">${item.quantity}</span>
                                <button onclick="updateQuantity('${item.productId}', ${item.quantity + 1})" 
                                        class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-xs">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button onclick="removeFromCart('${item.productId}')" 
                                        class="w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs ml-2">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-white font-bold text-sm mt-2">
                            Total: ${formatCurrency(item.price * item.quantity)}
                        </div>
                    </div>
                `).join('');
                document.getElementById('checkoutBtn').disabled = false;
            }
            
            calculateTotals();
        }

        function calculateTotals() {
            cartSubtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
            cartTax = cartSubtotal * 0.18; // 18% tax
            cartTotal = cartSubtotal + cartTax;
            
            document.getElementById('cartSubtotal').textContent = formatCurrency(cartSubtotal);
            document.getElementById('cartTax').textContent = formatCurrency(cartTax);
            document.getElementById('cartTotal').textContent = formatCurrency(cartTotal);
        }

        function formatCurrency(amount) {
            return 'Rs. ' + amount.toFixed(2);
        }

        // Checkout functionality
        document.getElementById('checkoutBtn').addEventListener('click', function() {
            document.getElementById('checkoutModal').classList.remove('hidden');
            document.getElementById('checkoutModal').classList.add('flex');
        });

        document.getElementById('cancelCheckout').addEventListener('click', function() {
            document.getElementById('checkoutModal').classList.add('hidden');
            document.getElementById('checkoutModal').classList.remove('flex');
        });

        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            e.preventDefault();
            processSale();
        });

        function processSale() {
            const formData = new FormData(document.getElementById('checkoutForm'));
            const saleData = {
                customer_name: formData.get('customer_name'),
                customer_phone: formData.get('customer_phone'),
                payment_method: formData.get('payment_method'),
                notes: formData.get('notes'),
                items: cart,
                subtotal: cartSubtotal,
                tax: cartTax,
                total: cartTotal
            };

            // Show loading state
            document.getElementById('processSale').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            document.getElementById('processSale').disabled = true;

            // Send to server
            fetch('process_sale.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(saleData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear cart
                    cart = [];
                    updateCartDisplay();
                    
                    // Close modal
                    document.getElementById('checkoutModal').classList.add('hidden');
                    document.getElementById('checkoutModal').classList.remove('flex');
                    
                    // Show success message
                    alert('Sale processed successfully! Invoice: ' + data.invoice_number);
                    
                    // Refresh page to show updated data
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the sale');
            })
            .finally(() => {
                // Reset button
                document.getElementById('processSale').innerHTML = '<i class="fas fa-check mr-2"></i>Process Sale';
                document.getElementById('processSale').disabled = false;
            });
        }

        // Clear cart
        document.getElementById('clearCartBtn').addEventListener('click', function() {
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                updateCartDisplay();
            }
        });

        // Product search and filter
        document.getElementById('productSearch').addEventListener('input', function() {
            filterProducts();
        });

        document.getElementById('categoryFilter').addEventListener('change', function() {
            filterProducts();
        });

        function filterProducts() {
            const searchTerm = document.getElementById('productSearch').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            
            document.querySelectorAll('.product-card').forEach(card => {
                const name = card.dataset.name.toLowerCase();
                const category = card.dataset.category;
                
                const matchesSearch = name.includes(searchTerm);
                const matchesCategory = !categoryFilter || category === categoryFilter;
                
                if (matchesSearch && matchesCategory) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Initialize
        updateCartDisplay();
    </script>
</body>
</html>