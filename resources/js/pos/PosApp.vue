<template>
  <div id="pos-app" class="pos-container">
    <!-- Header -->
    <div class="pos-header">
      <div class="header-left">
        <h1 class="business-name">{{ tenant.business_name }}</h1>
        <div class="user-info">
          <i class="fas fa-user"></i>
          {{ user.name }} | {{ formatTime(new Date()) }}
        </div>
      </div>
      <div class="header-right">
        <div class="stats">
          <div class="stat-item">
            <span class="stat-label">Today's Sales:</span>
            <span class="stat-value">{{ formatCurrency(todayStats.total_sales) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">FBR Sync:</span>
            <span class="stat-value" :class="fbrSyncClass">{{ todayStats.fbr_sync_rate }}%</span>
          </div>
        </div>
        <button @click="logout" class="logout-btn">
          <i class="fas fa-sign-out-alt"></i> Logout
        </button>
      </div>
    </div>

    <!-- Main Content -->
    <div class="pos-main">
      <!-- Left Panel - Products -->
      <div class="products-panel">
        <div class="search-bar">
          <input 
            ref="searchInput"
            v-model="searchQuery" 
            @keydown="handleSearchKeydown"
            placeholder="Search products... (F1 or /)"
            class="search-input"
          >
          <button @click="focusSearch" class="search-btn">
            <i class="fas fa-search"></i>
          </button>
        </div>

        <div class="category-filters">
          <button 
            v-for="category in categories" 
            :key="category.id"
            @click="filterByCategory(category.id)"
            :class="['category-btn', { active: selectedCategory === category.id }]"
          >
            {{ category.name }}
          </button>
        </div>

        <div class="products-grid" ref="productsGrid">
          <div 
            v-for="product in filteredProducts" 
            :key="product.id"
            @click="addToCart(product)"
            :class="['product-card', { 'out-of-stock': product.stock_quantity <= 0 }]"
          >
            <div class="product-image">
              <img v-if="product.image" :src="product.image_url" :alt="product.name">
              <div v-else class="placeholder-image">
                <i class="fas fa-box"></i>
              </div>
            </div>
            <div class="product-info">
              <h3 class="product-name">{{ product.name }}</h3>
              <p class="product-sku">{{ product.sku }}</p>
              <div class="product-price">{{ formatCurrency(product.price) }}</div>
              <div class="product-stock">Stock: {{ product.stock_quantity }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel - Cart -->
      <div class="cart-panel">
        <div class="cart-header">
          <h2>Shopping Cart</h2>
          <button @click="clearCart" class="clear-btn">
            <i class="fas fa-trash"></i> Clear
          </button>
        </div>

        <div class="cart-items" ref="cartItems">
          <div v-if="cart.length === 0" class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <p>Your cart is empty</p>
          </div>
          <div v-else>
            <div 
              v-for="item in cart" 
              :key="item.product_id"
              class="cart-item"
            >
              <div class="item-info">
                <h4>{{ item.name }}</h4>
                <p>{{ formatCurrency(item.unit_price) }} each</p>
              </div>
              <div class="item-controls">
                <button @click="updateQuantity(item.product_id, item.quantity - 1)" class="qty-btn">
                  <i class="fas fa-minus"></i>
                </button>
                <input 
                  v-model.number="item.quantity" 
                  @change="updateQuantity(item.product_id, item.quantity)"
                  class="qty-input"
                  type="number"
                  min="1"
                >
                <button @click="updateQuantity(item.product_id, item.quantity + 1)" class="qty-btn">
                  <i class="fas fa-plus"></i>
                </button>
                <button @click="removeFromCart(item.product_id)" class="remove-btn">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div class="item-total">
                {{ formatCurrency(item.total_price) }}
              </div>
            </div>
          </div>
        </div>

        <div class="cart-summary">
          <div class="summary-row">
            <span>Subtotal:</span>
            <span>{{ formatCurrency(cartSubtotal) }}</span>
          </div>
          <div class="summary-row">
            <span>Tax (18%):</span>
            <span>{{ formatCurrency(cartTax) }}</span>
          </div>
          <div class="summary-row total">
            <span>Total:</span>
            <span>{{ formatCurrency(cartTotal) }}</span>
          </div>
        </div>

        <div class="cart-actions">
          <button 
            @click="finalizeSale" 
            :disabled="cart.length === 0"
            class="finalize-btn"
          >
            <i class="fas fa-credit-card"></i> Finalize Sale (F4)
          </button>
        </div>
      </div>
    </div>

    <!-- Payment Modal -->
    <div v-if="showPaymentModal" class="modal-overlay" @click="closePaymentModal">
      <div class="payment-modal" @click.stop>
        <div class="modal-header">
          <h3>Payment Details</h3>
          <button @click="closePaymentModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="payment-amount">
            <label>Total Amount:</label>
            <div class="amount-display">{{ formatCurrency(cartTotal) }}</div>
          </div>
          
          <div class="payment-method">
            <label>Payment Method:</label>
            <select v-model="paymentMethod" class="payment-select">
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="easypaisa">Easypaisa</option>
              <option value="jazzcash">JazzCash</option>
            </select>
          </div>
          
          <div class="amount-received">
            <label>Amount Received:</label>
            <input 
              v-model.number="amountReceived" 
              type="number" 
              step="0.01"
              class="amount-input"
              @keydown.enter="processPayment"
            >
          </div>
          
          <div v-if="amountReceived > 0" class="change-amount">
            <label>Change:</label>
            <div class="change-display">{{ formatCurrency(changeAmount) }}</div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button @click="closePaymentModal" class="cancel-btn">Cancel</button>
          <button @click="processPayment" class="process-btn" :disabled="amountReceived < cartTotal">
            Process Payment
          </button>
        </div>
      </div>
    </div>

    <!-- FBR Processing Overlay -->
    <div v-if="showFbrOverlay" class="fbr-overlay">
      <div class="fbr-content">
        <div class="fbr-spinner">
          <i class="fas fa-sync fa-spin"></i>
        </div>
        <h3>{{ fbrStatus }}</h3>
        <p>{{ fbrMessage }}</p>
        <div v-if="fbrError" class="fbr-actions">
          <button @click="saveAsDraft" class="draft-btn">Save as Draft</button>
          <button @click="cancelSale" class="cancel-btn">Cancel Sale</button>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="modal-overlay" @click="closeSuccessModal">
      <div class="success-modal" @click.stop>
        <div class="success-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <h3>Sale Completed Successfully!</h3>
        <p>Invoice #: {{ lastInvoiceNumber }}</p>
        <p v-if="lastFbrInvoiceNumber">FBR Invoice #: {{ lastFbrInvoiceNumber }}</p>
        
        <div class="success-actions">
          <button @click="printReceipt" class="action-btn">
            <i class="fas fa-print"></i> Print Receipt
          </button>
          <button @click="sendWhatsApp" class="action-btn">
            <i class="fab fa-whatsapp"></i> WhatsApp
          </button>
          <button @click="sendEmail" class="action-btn">
            <i class="fas fa-envelope"></i> Email
          </button>
          <button @click="newSale" class="action-btn primary">
            <i class="fas fa-plus"></i> New Sale
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PosApp',
  data() {
    return {
      // User and tenant data
      user: {},
      tenant: {},
      
      // Products and categories
      products: [],
      categories: [],
      searchQuery: '',
      selectedCategory: null,
      
      // Cart
      cart: [],
      
      // Payment
      showPaymentModal: false,
      paymentMethod: 'cash',
      amountReceived: 0,
      
      // FBR Processing
      showFbrOverlay: false,
      fbrStatus: '',
      fbrMessage: '',
      fbrError: false,
      
      // Success
      showSuccessModal: false,
      lastInvoiceNumber: '',
      lastFbrInvoiceNumber: '',
      
      // Stats
      todayStats: {
        total_sales: 0,
        fbr_sync_rate: 0
      }
    }
  },
  
  computed: {
    filteredProducts() {
      let filtered = this.products;
      
      // Filter by category
      if (this.selectedCategory) {
        filtered = filtered.filter(p => p.category_id === this.selectedCategory);
      }
      
      // Filter by search query
      if (this.searchQuery) {
        const query = this.searchQuery.toLowerCase();
        filtered = filtered.filter(p => 
          p.name.toLowerCase().includes(query) ||
          p.sku.toLowerCase().includes(query) ||
          p.barcode.toLowerCase().includes(query)
        );
      }
      
      return filtered;
    },
    
    cartSubtotal() {
      return this.cart.reduce((sum, item) => sum + item.total_price, 0);
    },
    
    cartTax() {
      return this.cartSubtotal * 0.18; // 18% tax
    },
    
    cartTotal() {
      return this.cartSubtotal + this.cartTax;
    },
    
    changeAmount() {
      return Math.max(0, this.amountReceived - this.cartTotal);
    },
    
    fbrSyncClass() {
      if (this.todayStats.fbr_sync_rate >= 90) return 'text-green-500';
      if (this.todayStats.fbr_sync_rate >= 70) return 'text-yellow-500';
      return 'text-red-500';
    }
  },
  
  mounted() {
    this.loadData();
    this.setupKeyboardShortcuts();
    this.loadTodayStats();
  },
  
  methods: {
    async loadData() {
      try {
        // Load products and categories
        const [productsRes, categoriesRes] = await Promise.all([
          fetch('/api/products'),
          fetch('/api/categories')
        ]);
        
        this.products = await productsRes.json();
        this.categories = await categoriesRes.json();
        
        // Load user and tenant data
        const userRes = await fetch('/api/user');
        this.user = await userRes.json();
        
        const tenantRes = await fetch('/api/tenant');
        this.tenant = await tenantRes.json();
        
      } catch (error) {
        console.error('Error loading data:', error);
      }
    },
    
    setupKeyboardShortcuts() {
      document.addEventListener('keydown', (e) => {
        // F1 or / - Focus search
        if (e.key === 'F1' || e.key === '/') {
          e.preventDefault();
          this.focusSearch();
        }
        
        // F4 - Finalize sale
        if (e.key === 'F4') {
          e.preventDefault();
          this.finalizeSale();
        }
        
        // Ctrl+P - Print
        if (e.ctrlKey && e.key === 'p') {
          e.preventDefault();
          this.printReceipt();
        }
        
        // Ctrl+H - Hold sale
        if (e.ctrlKey && e.key === 'h') {
          e.preventDefault();
          this.holdSale();
        }
        
        // Ctrl+R - Resume sale
        if (e.ctrlKey && e.key === 'r') {
          e.preventDefault();
          this.resumeSale();
        }
      });
    },
    
    focusSearch() {
      this.$refs.searchInput.focus();
    },
    
    handleSearchKeydown(e) {
      if (e.key === 'Enter') {
        // Add first filtered product to cart
        if (this.filteredProducts.length > 0) {
          this.addToCart(this.filteredProducts[0]);
          this.searchQuery = '';
        }
      }
    },
    
    filterByCategory(categoryId) {
      this.selectedCategory = this.selectedCategory === categoryId ? null : categoryId;
    },
    
    addToCart(product) {
      if (product.stock_quantity <= 0) {
        this.showNotification('Product is out of stock', 'error');
        return;
      }
      
      const existingItem = this.cart.find(item => item.product_id === product.id);
      
      if (existingItem) {
        existingItem.quantity += 1;
        existingItem.total_price = existingItem.quantity * existingItem.unit_price;
      } else {
        this.cart.push({
          product_id: product.id,
          name: product.name,
          unit_price: product.price,
          quantity: 1,
          total_price: product.price
        });
      }
      
      this.showNotification(`${product.name} added to cart`, 'success');
    },
    
    updateQuantity(productId, newQuantity) {
      if (newQuantity <= 0) {
        this.removeFromCart(productId);
        return;
      }
      
      const item = this.cart.find(item => item.product_id === productId);
      if (item) {
        item.quantity = newQuantity;
        item.total_price = item.quantity * item.unit_price;
      }
    },
    
    removeFromCart(productId) {
      this.cart = this.cart.filter(item => item.product_id !== productId);
    },
    
    clearCart() {
      if (this.cart.length > 0 && confirm('Are you sure you want to clear the cart?')) {
        this.cart = [];
      }
    },
    
    finalizeSale() {
      if (this.cart.length === 0) {
        this.showNotification('Cart is empty', 'error');
        return;
      }
      
      this.showPaymentModal = true;
      this.amountReceived = this.cartTotal;
    },
    
    closePaymentModal() {
      this.showPaymentModal = false;
      this.amountReceived = 0;
    },
    
    async processPayment() {
      if (this.amountReceived < this.cartTotal) {
        this.showNotification('Amount received is less than total', 'error');
        return;
      }
      
      this.closePaymentModal();
      await this.processSale();
    },
    
    async processSale() {
      this.showFbrOverlay = true;
      this.fbrStatus = 'Verifying with FBR, please wait...';
      this.fbrMessage = 'This may take a few seconds';
      this.fbrError = false;
      
      try {
        const saleData = {
          items: this.cart,
          payment_method: this.paymentMethod,
          amount_received: this.amountReceived,
          subtotal: this.cartSubtotal,
          tax: this.cartTax,
          total: this.cartTotal
        };
        
        const response = await fetch('/api/sales', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(saleData)
        });
        
        const result = await response.json();
        
        if (result.success) {
          this.fbrStatus = 'Verified! FBR Invoice # ' + result.fbr_invoice_number;
          this.fbrMessage = 'Sale processed successfully';
          this.lastInvoiceNumber = result.invoice_number;
          this.lastFbrInvoiceNumber = result.fbr_invoice_number;
          
          setTimeout(() => {
            this.showFbrOverlay = false;
            this.showSuccessModal = true;
            this.cart = [];
            this.loadTodayStats();
          }, 2000);
          
        } else {
          this.fbrStatus = 'FBR Validation Failed';
          this.fbrMessage = result.error;
          this.fbrError = true;
        }
        
      } catch (error) {
        this.fbrStatus = 'Error';
        this.fbrMessage = 'An error occurred while processing the sale';
        this.fbrError = true;
        console.error('Sale processing error:', error);
      }
    },
    
    saveAsDraft() {
      // Save sale as draft for later processing
      this.showFbrOverlay = false;
      this.showNotification('Sale saved as draft', 'info');
    },
    
    cancelSale() {
      this.showFbrOverlay = false;
      this.showNotification('Sale cancelled', 'info');
    },
    
    closeSuccessModal() {
      this.showSuccessModal = false;
    },
    
    newSale() {
      this.showSuccessModal = false;
      this.cart = [];
    },
    
    printReceipt() {
      // Implement receipt printing
      window.print();
    },
    
    sendWhatsApp() {
      // Implement WhatsApp integration
      const message = `Thank you for your purchase!\n\nInvoice: ${this.lastInvoiceNumber}\nTotal: ${this.formatCurrency(this.cartTotal)}`;
      const url = `https://wa.me/?text=${encodeURIComponent(message)}`;
      window.open(url, '_blank');
    },
    
    sendEmail() {
      // Implement email integration
      this.showNotification('Email feature coming soon', 'info');
    },
    
    holdSale() {
      // Implement hold sale functionality
      this.showNotification('Hold sale feature coming soon', 'info');
    },
    
    resumeSale() {
      // Implement resume sale functionality
      this.showNotification('Resume sale feature coming soon', 'info');
    },
    
    async loadTodayStats() {
      try {
        const response = await fetch('/api/stats/today');
        this.todayStats = await response.json();
      } catch (error) {
        console.error('Error loading stats:', error);
      }
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        minimumFractionDigits: 2
      }).format(amount);
    },
    
    formatTime(date) {
      return date.toLocaleTimeString('en-PK', {
        hour: '2-digit',
        minute: '2-digit'
      });
    },
    
    showNotification(message, type = 'info') {
      // Implement notification system
      console.log(`${type.toUpperCase()}: ${message}`);
    },
    
    logout() {
      window.location.href = '/logout';
    }
  }
}
</script>

<style scoped>
.pos-container {
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f8fafc;
}

.pos-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.business-name {
  font-size: 1.5rem;
  font-weight: bold;
  margin: 0;
}

.user-info {
  font-size: 0.9rem;
  opacity: 0.9;
}

.stats {
  display: flex;
  gap: 2rem;
  margin-right: 2rem;
}

.stat-item {
  text-align: center;
}

.stat-label {
  display: block;
  font-size: 0.8rem;
  opacity: 0.8;
}

.stat-value {
  font-size: 1.1rem;
  font-weight: bold;
}

.logout-btn {
  background: rgba(255,255,255,0.2);
  border: none;
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: background 0.3s;
}

.logout-btn:hover {
  background: rgba(255,255,255,0.3);
}

.pos-main {
  flex: 1;
  display: flex;
  overflow: hidden;
}

.products-panel {
  flex: 2;
  padding: 1rem;
  overflow-y: auto;
}

.search-bar {
  display: flex;
  margin-bottom: 1rem;
  gap: 0.5rem;
}

.search-input {
  flex: 1;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 1rem;
  transition: border-color 0.3s;
}

.search-input:focus {
  outline: none;
  border-color: #667eea;
}

.search-btn {
  padding: 0.75rem 1rem;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
}

.category-filters {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.category-btn {
  padding: 0.5rem 1rem;
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.3s;
}

.category-btn:hover,
.category-btn.active {
  background: #667eea;
  color: white;
  border-color: #667eea;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.product-card {
  background: white;
  border-radius: 0.5rem;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.3s;
  border: 2px solid transparent;
}

.product-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  border-color: #667eea;
}

.product-card.out-of-stock {
  opacity: 0.5;
  cursor: not-allowed;
}

.product-image {
  width: 100%;
  height: 120px;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  border-radius: 0.25rem;
}

.product-image img {
  max-width: 100%;
  max-height: 100%;
  object-fit: cover;
}

.placeholder-image {
  font-size: 2rem;
  color: #cbd5e0;
}

.product-name {
  font-size: 0.9rem;
  font-weight: bold;
  margin: 0 0 0.25rem 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.product-sku {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0 0 0.5rem 0;
}

.product-price {
  font-size: 1rem;
  font-weight: bold;
  color: #059669;
  margin-bottom: 0.25rem;
}

.product-stock {
  font-size: 0.8rem;
  color: #64748b;
}

.cart-panel {
  flex: 1;
  background: white;
  border-left: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
}

.cart-header {
  padding: 1rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.cart-header h2 {
  margin: 0;
  font-size: 1.25rem;
}

.clear-btn {
  background: #ef4444;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 0.25rem;
  cursor: pointer;
  font-size: 0.9rem;
}

.cart-items {
  flex: 1;
  padding: 1rem;
  overflow-y: auto;
}

.empty-cart {
  text-align: center;
  padding: 2rem;
  color: #64748b;
}

.empty-cart i {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.cart-item {
  display: flex;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid #f1f5f9;
}

.item-info {
  flex: 1;
}

.item-info h4 {
  margin: 0 0 0.25rem 0;
  font-size: 0.9rem;
}

.item-info p {
  margin: 0;
  font-size: 0.8rem;
  color: #64748b;
}

.item-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 1rem;
}

.qty-btn {
  width: 24px;
  height: 24px;
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}

.qty-input {
  width: 50px;
  text-align: center;
  border: 1px solid #e2e8f0;
  border-radius: 0.25rem;
  padding: 0.25rem;
}

.remove-btn {
  width: 24px;
  height: 24px;
  border: 1px solid #ef4444;
  background: #ef4444;
  color: white;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}

.item-total {
  font-weight: bold;
  color: #059669;
  min-width: 80px;
  text-align: right;
}

.cart-summary {
  padding: 1rem;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.summary-row.total {
  font-weight: bold;
  font-size: 1.1rem;
  border-top: 1px solid #e2e8f0;
  padding-top: 0.5rem;
  margin-top: 0.5rem;
}

.cart-actions {
  padding: 1rem;
  border-top: 1px solid #e2e8f0;
}

.finalize-btn {
  width: 100%;
  background: #059669;
  color: white;
  border: none;
  padding: 1rem;
  border-radius: 0.5rem;
  font-size: 1rem;
  font-weight: bold;
  cursor: pointer;
  transition: background 0.3s;
}

.finalize-btn:hover:not(:disabled) {
  background: #047857;
}

.finalize-btn:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.payment-modal,
.success-modal {
  background: white;
  border-radius: 0.5rem;
  padding: 2rem;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #64748b;
}

.modal-body {
  margin-bottom: 1.5rem;
}

.payment-amount,
.payment-method,
.amount-received,
.change-amount {
  margin-bottom: 1rem;
}

.payment-amount label,
.payment-method label,
.amount-received label,
.change-amount label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: bold;
}

.amount-display {
  font-size: 1.5rem;
  font-weight: bold;
  color: #059669;
  text-align: center;
  padding: 1rem;
  background: #f0fdf4;
  border-radius: 0.5rem;
}

.payment-select,
.amount-input {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 1rem;
}

.change-display {
  font-size: 1.25rem;
  font-weight: bold;
  color: #059669;
  text-align: center;
  padding: 0.75rem;
  background: #f0fdf4;
  border-radius: 0.5rem;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

.cancel-btn,
.process-btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  font-size: 1rem;
}

.cancel-btn {
  background: #64748b;
  color: white;
}

.process-btn {
  background: #059669;
  color: white;
}

.process-btn:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}

/* FBR Overlay */
.fbr-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.fbr-content {
  background: white;
  border-radius: 0.5rem;
  padding: 3rem;
  text-align: center;
  max-width: 400px;
  width: 90%;
}

.fbr-spinner {
  font-size: 3rem;
  color: #667eea;
  margin-bottom: 1rem;
}

.fbr-content h3 {
  margin: 0 0 1rem 0;
  color: #1f2937;
}

.fbr-content p {
  margin: 0 0 2rem 0;
  color: #64748b;
}

.fbr-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
}

.draft-btn {
  background: #f59e0b;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  cursor: pointer;
}

/* Success Modal */
.success-modal {
  text-align: center;
}

.success-icon {
  font-size: 4rem;
  color: #059669;
  margin-bottom: 1rem;
}

.success-actions {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-top: 2rem;
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  background: white;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.3s;
}

.action-btn:hover {
  border-color: #667eea;
  color: #667eea;
}

.action-btn.primary {
  background: #667eea;
  color: white;
  border-color: #667eea;
}

.action-btn.primary:hover {
  background: #5a67d8;
}

/* Responsive Design */
@media (max-width: 768px) {
  .pos-main {
    flex-direction: column;
  }
  
  .products-panel {
    flex: 1;
    max-height: 50vh;
  }
  
  .cart-panel {
    flex: 1;
    max-height: 50vh;
  }
  
  .stats {
    display: none;
  }
  
  .products-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  }
}
</style>