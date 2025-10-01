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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'search_products':
            $search = $_POST['search'] ?? '';
            $stmt = $pdo->prepare("
                SELECT id, name, sku, barcode, price, stock_quantity, description 
                FROM products 
                WHERE tenant_id = ? AND is_active = 1 
                AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ?)
                ORDER BY name LIMIT 20
            ");
            $searchTerm = "%{$search}%";
            $stmt->execute([$user['tenant_id'], $searchTerm, $searchTerm, $searchTerm]);
            $products = $stmt->fetchAll();
            echo json_encode(['success' => true, 'products' => $products]);
            exit;
            
        case 'get_product':
            $productId = $_POST['product_id'];
            $stmt = $pdo->prepare("
                SELECT id, name, sku, barcode, price, stock_quantity, description 
                FROM products 
                WHERE id = ? AND tenant_id = ? AND is_active = 1
            ");
            $stmt->execute([$productId, $user['tenant_id']]);
            $product = $stmt->fetch();
            echo json_encode(['success' => true, 'product' => $product]);
            exit;
            
        case 'create_sale':
            $saleData = json_decode($_POST['sale_data'], true);
            
            try {
                $pdo->beginTransaction();
                
                // Generate invoice number
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Create sale record
                $stmt = $pdo->prepare("
                    INSERT INTO sales (tenant_id, invoice_number, cashier_id, subtotal, tax_amount, discount_amount, total_amount, payment_method, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
                ");
                $stmt->execute([
                    $user['tenant_id'],
                    $invoiceNumber,
                    $user['id'],
                    $saleData['subtotal'],
                    $saleData['tax_amount'],
                    $saleData['discount_amount'],
                    $saleData['total_amount'],
                    $saleData['payment_method']
                ]);
                
                $saleId = $pdo->lastInsertId();
                
                // Create sale items
                foreach ($saleData['items'] as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO sale_items (tenant_id, sale_id, product_id, quantity, unit_price, total_price, tax_rate, tax_amount)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user['tenant_id'],
                        $saleId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['total_price'],
                        $item['tax_rate'],
                        $item['tax_amount']
                    ]);
                    
                    // Update stock
                    $stmt = $pdo->prepare("
                        UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND tenant_id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['product_id'], $user['tenant_id']]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'sale_id' => $saleId, 'invoice_number' => $invoiceNumber]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
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
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
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
        .product-card:hover {
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        .cart-item {
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
    </style>
</head>
<body>
    <div id="app" class="min-h-screen">
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
                        <button @click="showSettings = true" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-cog mr-2"></i>Settings
                        </button>
                        <a href="/admin/" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Product Search & Grid -->
                <div class="lg:col-span-2">
                    <div class="glass-effect rounded-lg p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-semibold text-white">Products</h3>
                            <div class="flex space-x-2">
                                <button @click="showCategories = !showCategories" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg hover:bg-opacity-30 transition-all">
                                    <i class="fas fa-list mr-2"></i>Categories
                                </button>
                                <button @click="showAddProduct = true" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-all">
                                    <i class="fas fa-plus mr-2"></i>Add Product
                                </button>
                            </div>
                        </div>
                        
                        <!-- Search Bar -->
                        <div class="relative mb-6">
                            <input v-model="searchQuery" @input="searchProducts" 
                                   type="text" 
                                   placeholder="Search products by name, SKU, or barcode..." 
                                   class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            <button class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white opacity-75">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        
                        <!-- Categories Filter -->
                        <div v-if="showCategories" class="mb-6">
                            <div class="flex flex-wrap gap-2">
                                <button @click="selectedCategory = null" 
                                        :class="selectedCategory === null ? 'bg-indigo-500' : 'bg-white bg-opacity-20'"
                                        class="text-white px-4 py-2 rounded-lg transition-all">
                                    All Categories
                                </button>
                                <button v-for="category in categories" :key="category.id"
                                        @click="selectedCategory = category.id"
                                        :class="selectedCategory === category.id ? 'bg-indigo-500' : 'bg-white bg-opacity-20'"
                                        class="text-white px-4 py-2 rounded-lg transition-all">
                                    {{ category.name }}
                                </button>
                            </div>
                        </div>
                        
                        <!-- Product Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 max-h-96 overflow-y-auto">
                            <div v-for="product in filteredProducts" :key="product.id" 
                                 @click="addToCart(product)"
                                 class="product-card bg-white bg-opacity-10 rounded-lg p-4 text-center cursor-pointer hover:bg-opacity-20 transition-all">
                                <div class="text-2xl text-white mb-2">📦</div>
                                <div class="text-white font-medium text-sm mb-1">{{ product.name }}</div>
                                <div class="text-white opacity-75 text-xs mb-2">{{ product.sku }}</div>
                                <div class="text-green-400 font-bold">Rs. {{ formatPrice(product.price) }}</div>
                                <div class="text-white opacity-60 text-xs">Stock: {{ product.stock_quantity }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cart & Checkout -->
                <div class="lg:col-span-1">
                    <div class="glass-effect rounded-lg p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-semibold text-white">Shopping Cart</h3>
                            <button @click="clearCart" class="text-red-400 hover:text-red-300">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        
                        <!-- Cart Items -->
                        <div class="space-y-3 mb-6 max-h-64 overflow-y-auto">
                            <div v-for="(item, index) in cart" :key="index" 
                                 class="cart-item flex justify-between items-center text-white bg-white bg-opacity-10 rounded-lg p-3">
                                <div class="flex-1">
                                    <div class="font-medium text-sm">{{ item.name }}</div>
                                    <div class="text-xs opacity-75">Rs. {{ formatPrice(item.price) }} x {{ item.quantity }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button @click="updateQuantity(index, item.quantity - 1)" 
                                            class="bg-red-500 hover:bg-red-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs">
                                        -
                                    </button>
                                    <span class="text-sm font-medium">{{ item.quantity }}</span>
                                    <button @click="updateQuantity(index, item.quantity + 1)" 
                                            class="bg-green-500 hover:bg-green-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs">
                                        +
                                    </button>
                                    <button @click="removeFromCart(index)" 
                                            class="bg-red-500 hover:bg-red-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs ml-2">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div v-if="cart.length === 0" class="text-center text-white opacity-75 py-8">
                                <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                                <div>Cart is empty</div>
                            </div>
                        </div>
                        
                        <!-- Cart Summary -->
                        <div v-if="cart.length > 0" class="border-t border-white border-opacity-20 pt-4 mb-6">
                            <div class="flex justify-between items-center text-white mb-2">
                                <span>Subtotal:</span>
                                <span>Rs. {{ formatPrice(cartSubtotal) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-white mb-2">
                                <span>Tax ({{ taxRate }}%):</span>
                                <span>Rs. {{ formatPrice(cartTax) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-white mb-2">
                                <span>Discount:</span>
                                <span>-Rs. {{ formatPrice(discount) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-white font-bold text-lg border-t border-white border-opacity-20 pt-2">
                                <span>Total:</span>
                                <span>Rs. {{ formatPrice(cartTotal) }}</span>
                            </div>
                        </div>
                        
                        <!-- Discount Input -->
                        <div v-if="cart.length > 0" class="mb-4">
                            <input v-model="discountCode" 
                                   type="text" 
                                   placeholder="Discount code" 
                                   class="w-full px-3 py-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500 text-sm">
                        </div>
                        
                        <!-- Payment Buttons -->
                        <div v-if="cart.length > 0" class="space-y-3">
                            <button @click="processPayment('cash')" 
                                    class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg font-semibold transition-all">
                                <i class="fas fa-money-bill-wave mr-2"></i>Cash Payment
                            </button>
                            <button @click="processPayment('card')" 
                                    class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg font-semibold transition-all">
                                <i class="fas fa-credit-card mr-2"></i>Card Payment
                            </button>
                            <button @click="processPayment('mobile')" 
                                    class="w-full bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-lg font-semibold transition-all">
                                <i class="fas fa-mobile-alt mr-2"></i>Mobile Payment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Modal -->
        <div v-if="showPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-md w-full mx-4">
                <h3 class="text-xl font-semibold text-white mb-6">Complete Payment</h3>
                
                <div class="mb-6">
                    <div class="text-white text-center">
                        <div class="text-2xl font-bold">Rs. {{ formatPrice(cartTotal) }}</div>
                        <div class="opacity-75">Payment Method: {{ paymentMethod.toUpperCase() }}</div>
                    </div>
                </div>
                
                <div v-if="paymentMethod === 'cash'" class="mb-6">
                    <label class="block text-white font-medium mb-2">Amount Received</label>
                    <input v-model="amountReceived" 
                           type="number" 
                           step="0.01"
                           class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                    <div v-if="amountReceived > 0" class="mt-2 text-white">
                        Change: Rs. {{ formatPrice(amountReceived - cartTotal) }}
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <button @click="showPaymentModal = false" 
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-3 rounded-lg transition-all">
                        Cancel
                    </button>
                    <button @click="confirmPayment" 
                            :disabled="paymentMethod === 'cash' && amountReceived < cartTotal"
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg transition-all disabled:opacity-50">
                        Confirm Payment
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Receipt Modal -->
        <div v-if="showReceiptModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="glass-effect rounded-lg p-8 max-w-md w-full mx-4">
                <h3 class="text-xl font-semibold text-white mb-6 text-center">Receipt</h3>
                
                <div class="text-white text-center mb-6">
                    <div class="text-lg font-bold">DPS POS FBR Integrated</div>
                    <div class="text-sm opacity-75">Invoice #{{ lastInvoiceNumber }}</div>
                    <div class="text-sm opacity-75">{{ new Date().toLocaleString() }}</div>
                </div>
                
                <div class="space-y-2 mb-6">
                    <div v-for="item in lastSaleItems" :key="item.id" class="flex justify-between text-white text-sm">
                        <span>{{ item.name }} x{{ item.quantity }}</span>
                        <span>Rs. {{ formatPrice(item.total_price) }}</span>
                    </div>
                </div>
                
                <div class="border-t border-white border-opacity-20 pt-4 mb-6">
                    <div class="flex justify-between text-white font-bold">
                        <span>Total:</span>
                        <span>Rs. {{ formatPrice(lastSaleTotal) }}</span>
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <button @click="printReceipt" 
                            class="flex-1 bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg transition-all">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                    <button @click="showReceiptModal = false" 
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-3 rounded-lg transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    searchQuery: '',
                    products: [],
                    filteredProducts: [],
                    categories: [],
                    selectedCategory: null,
                    showCategories: false,
                    showAddProduct: false,
                    showSettings: false,
                    cart: [],
                    discountCode: '',
                    discount: 0,
                    taxRate: 16, // 16% tax rate
                    showPaymentModal: false,
                    showReceiptModal: false,
                    paymentMethod: 'cash',
                    amountReceived: 0,
                    lastInvoiceNumber: '',
                    lastSaleItems: [],
                    lastSaleTotal: 0
                }
            },
            computed: {
                cartSubtotal() {
                    return this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                },
                cartTax() {
                    return this.cartSubtotal * (this.taxRate / 100);
                },
                cartTotal() {
                    return this.cartSubtotal + this.cartTax - this.discount;
                }
            },
            mounted() {
                this.loadProducts();
                this.loadCategories();
            },
            methods: {
                async loadProducts() {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=search_products&search='
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.products = data.products;
                            this.filteredProducts = data.products;
                        }
                    } catch (error) {
                        console.error('Error loading products:', error);
                    }
                },
                
                async loadCategories() {
                    // Mock categories for now
                    this.categories = [
                        { id: 1, name: 'Electronics' },
                        { id: 2, name: 'Clothing' },
                        { id: 3, name: 'Food' },
                        { id: 4, name: 'Books' }
                    ];
                },
                
                async searchProducts() {
                    if (this.searchQuery.length < 2) {
                        this.filteredProducts = this.products;
                        return;
                    }
                    
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=search_products&search=${encodeURIComponent(this.searchQuery)}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.filteredProducts = data.products;
                        }
                    } catch (error) {
                        console.error('Error searching products:', error);
                    }
                },
                
                addToCart(product) {
                    if (product.stock_quantity <= 0) {
                        alert('Product out of stock!');
                        return;
                    }
                    
                    const existingItem = this.cart.find(item => item.id === product.id);
                    if (existingItem) {
                        existingItem.quantity += 1;
                    } else {
                        this.cart.push({
                            id: product.id,
                            name: product.name,
                            price: product.price,
                            quantity: 1,
                            stock_quantity: product.stock_quantity
                        });
                    }
                },
                
                updateQuantity(index, newQuantity) {
                    if (newQuantity <= 0) {
                        this.cart.splice(index, 1);
                    } else {
                        this.cart[index].quantity = newQuantity;
                    }
                },
                
                removeFromCart(index) {
                    this.cart.splice(index, 1);
                },
                
                clearCart() {
                    this.cart = [];
                    this.discount = 0;
                    this.discountCode = '';
                },
                
                processPayment(method) {
                    this.paymentMethod = method;
                    this.amountReceived = this.cartTotal;
                    this.showPaymentModal = true;
                },
                
                async confirmPayment() {
                    if (this.paymentMethod === 'cash' && this.amountReceived < this.cartTotal) {
                        alert('Amount received is less than total amount!');
                        return;
                    }
                    
                    try {
                        const saleData = {
                            items: this.cart.map(item => ({
                                product_id: item.id,
                                quantity: item.quantity,
                                unit_price: item.price,
                                total_price: item.price * item.quantity,
                                tax_rate: this.taxRate,
                                tax_amount: (item.price * item.quantity) * (this.taxRate / 100)
                            })),
                            subtotal: this.cartSubtotal,
                            tax_amount: this.cartTax,
                            discount_amount: this.discount,
                            total_amount: this.cartTotal,
                            payment_method: this.paymentMethod
                        };
                        
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=create_sale&sale_data=${encodeURIComponent(JSON.stringify(saleData))}`
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.lastInvoiceNumber = data.invoice_number;
                            this.lastSaleItems = this.cart;
                            this.lastSaleTotal = this.cartTotal;
                            
                            this.showPaymentModal = false;
                            this.showReceiptModal = true;
                            this.clearCart();
                        } else {
                            alert('Payment failed: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error processing payment:', error);
                        alert('Payment failed: ' + error.message);
                    }
                },
                
                printReceipt() {
                    // Create a printable receipt
                    const printWindow = window.open('', '_blank');
                    const receiptContent = `
                        <html>
                        <head>
                            <title>Receipt - ${this.lastInvoiceNumber}</title>
                            <style>
                                body { font-family: monospace; font-size: 12px; margin: 20px; }
                                .header { text-align: center; margin-bottom: 20px; }
                                .item { display: flex; justify-content: space-between; margin: 5px 0; }
                                .total { border-top: 1px solid #000; padding-top: 10px; font-weight: bold; }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <h2>DPS POS FBR Integrated</h2>
                                <p>Invoice #${this.lastInvoiceNumber}</p>
                                <p>${new Date().toLocaleString()}</p>
                            </div>
                            ${this.lastSaleItems.map(item => `
                                <div class="item">
                                    <span>${item.name} x${item.quantity}</span>
                                    <span>Rs. ${this.formatPrice(item.total_price)}</span>
                                </div>
                            `).join('')}
                            <div class="item total">
                                <span>Total:</span>
                                <span>Rs. ${this.formatPrice(this.lastSaleTotal)}</span>
                            </div>
                        </body>
                        </html>
                    `;
                    printWindow.document.write(receiptContent);
                    printWindow.document.close();
                    printWindow.print();
                },
                
                formatPrice(price) {
                    return parseFloat(price).toFixed(2);
                }
            }
        }).mount('#app');
    </script>
</body>
</html>