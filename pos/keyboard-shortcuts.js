/**
 * DPS POS FBR Integrated - Keyboard Shortcuts System
 * Comprehensive keyboard shortcuts for efficient POS operations
 */

class KeyboardShortcuts {
    constructor(posApp) {
        this.app = posApp;
        this.shortcuts = new Map();
        this.isEnabled = true;
        this.lastKeyTime = 0;
        this.keySequence = [];
        this.sequenceTimeout = null;
        
        this.initializeShortcuts();
        this.bindEvents();
    }
    
    initializeShortcuts() {
        // Single key shortcuts
        this.shortcuts.set('F1', { action: 'focus_search', description: 'Focus product search' });
        this.shortcuts.set('F2', { action: 'add_customer', description: 'Add new customer' });
        this.shortcuts.set('F3', { action: 'open_inventory', description: 'Open inventory management' });
        this.shortcuts.set('F4', { action: 'process_payment', description: 'Process payment' });
        this.shortcuts.set('F5', { action: 'refresh_products', description: 'Refresh product list' });
        this.shortcuts.set('F6', { action: 'open_reports', description: 'Open reports' });
        this.shortcuts.set('F7', { action: 'open_settings', description: 'Open settings' });
        this.shortcuts.set('F8', { action: 'print_receipt', description: 'Print receipt' });
        this.shortcuts.set('F9', { action: 'open_customers', description: 'Open customer management' });
        this.shortcuts.set('F10', { action: 'open_users', description: 'Open user management' });
        this.shortcuts.set('F11', { action: 'toggle_fullscreen', description: 'Toggle fullscreen' });
        this.shortcuts.set('F12', { action: 'open_help', description: 'Open help' });
        
        // Ctrl combinations
        this.shortcuts.set('Ctrl+N', { action: 'new_sale', description: 'Start new sale' });
        this.shortcuts.set('Ctrl+S', { action: 'save_sale', description: 'Save current sale' });
        this.shortcuts.set('Ctrl+P', { action: 'print_receipt', description: 'Print receipt' });
        this.shortcuts.set('Ctrl+Z', { action: 'undo_last_item', description: 'Undo last item' });
        this.shortcuts.set('Ctrl+Y', { action: 'redo_last_item', description: 'Redo last item' });
        this.shortcuts.set('Ctrl+A', { action: 'select_all_items', description: 'Select all cart items' });
        this.shortcuts.set('Ctrl+D', { action: 'delete_selected', description: 'Delete selected items' });
        this.shortcuts.set('Ctrl+F', { action: 'focus_search', description: 'Focus search' });
        this.shortcuts.set('Ctrl+H', { action: 'open_history', description: 'Open sales history' });
        this.shortcuts.set('Ctrl+L', { action: 'open_logout', description: 'Logout' });
        this.shortcuts.set('Ctrl+R', { action: 'refresh_data', description: 'Refresh all data' });
        this.shortcuts.set('Ctrl+T', { action: 'open_tax_settings', description: 'Open tax settings' });
        this.shortcuts.set('Ctrl+U', { action: 'open_users', description: 'Open users' });
        this.shortcuts.set('Ctrl+I', { action: 'open_inventory', description: 'Open inventory' });
        this.shortcuts.set('Ctrl+O', { action: 'open_reports', description: 'Open reports' });
        this.shortcuts.set('Ctrl+W', { action: 'close_current_tab', description: 'Close current tab' });
        this.shortcuts.set('Ctrl+Q', { action: 'quit_application', description: 'Quit application' });
        
        // Alt combinations
        this.shortcuts.set('Alt+1', { action: 'payment_cash', description: 'Cash payment' });
        this.shortcuts.set('Alt+2', { action: 'payment_card', description: 'Card payment' });
        this.shortcuts.set('Alt+3', { action: 'payment_mobile', description: 'Mobile payment' });
        this.shortcuts.set('Alt+4', { action: 'payment_credit', description: 'Credit payment' });
        this.shortcuts.set('Alt+C', { action: 'clear_cart', description: 'Clear cart' });
        this.shortcuts.set('Alt+D', { action: 'apply_discount', description: 'Apply discount' });
        this.shortcuts.set('Alt+T', { action: 'apply_tax', description: 'Apply tax' });
        this.shortcuts.set('Alt+R', { action: 'remove_tax', description: 'Remove tax' });
        this.shortcuts.set('Alt+S', { action: 'save_draft', description: 'Save as draft' });
        this.shortcuts.set('Alt+L', { action: 'load_draft', description: 'Load draft' });
        this.shortcuts.set('Alt+H', { action: 'open_help', description: 'Open help' });
        this.shortcuts.set('Alt+E', { action: 'export_data', description: 'Export data' });
        this.shortcuts.set('Alt+I', { action: 'import_data', description: 'Import data' });
        this.shortcuts.set('Alt+B', { action: 'open_backup', description: 'Open backup' });
        this.shortcuts.set('Alt+U', { action: 'open_users', description: 'Open users' });
        this.shortcuts.set('Alt+M', { action: 'open_messages', description: 'Open messages' });
        this.shortcuts.set('Alt+N', { action: 'open_notifications', description: 'Open notifications' });
        
        // Number key shortcuts (for quick product selection)
        for (let i = 0; i <= 9; i++) {
            this.shortcuts.set(i.toString(), { 
                action: 'select_product_by_number', 
                description: `Select product ${i}`,
                data: { number: i }
            });
        }
        
        // Arrow key shortcuts
        this.shortcuts.set('ArrowUp', { action: 'navigate_up', description: 'Navigate up' });
        this.shortcuts.set('ArrowDown', { action: 'navigate_down', description: 'Navigate down' });
        this.shortcuts.set('ArrowLeft', { action: 'navigate_left', description: 'Navigate left' });
        this.shortcuts.set('ArrowRight', { action: 'navigate_right', description: 'Navigate right' });
        
        // Special key combinations
        this.shortcuts.set('Escape', { action: 'cancel_operation', description: 'Cancel current operation' });
        this.shortcuts.set('Enter', { action: 'confirm_operation', description: 'Confirm current operation' });
        this.shortcuts.set('Tab', { action: 'next_field', description: 'Move to next field' });
        this.shortcuts.set('Shift+Tab', { action: 'previous_field', description: 'Move to previous field' });
        this.shortcuts.set('Delete', { action: 'delete_selected', description: 'Delete selected items' });
        this.shortcuts.set('Backspace', { action: 'backspace', description: 'Backspace' });
        
        // Quick access shortcuts
        this.shortcuts.set('Space', { action: 'toggle_pause', description: 'Toggle pause/resume' });
        this.shortcuts.set('Home', { action: 'go_to_start', description: 'Go to start' });
        this.shortcuts.set('End', { action: 'go_to_end', description: 'Go to end' });
        this.shortcuts.set('PageUp', { action: 'page_up', description: 'Page up' });
        this.shortcuts.set('PageDown', { action: 'page_down', description: 'Page down' });
        
        // Barcode scanning shortcuts
        this.shortcuts.set('Ctrl+B', { action: 'open_barcode_scanner', description: 'Open barcode scanner' });
        this.shortcuts.set('Ctrl+Shift+B', { action: 'toggle_barcode_mode', description: 'Toggle barcode mode' });
        
        // Quick product shortcuts
        this.shortcuts.set('Ctrl+1', { action: 'quick_product_1', description: 'Quick product 1' });
        this.shortcuts.set('Ctrl+2', { action: 'quick_product_2', description: 'Quick product 2' });
        this.shortcuts.set('Ctrl+3', { action: 'quick_product_3', description: 'Quick product 3' });
        this.shortcuts.set('Ctrl+4', { action: 'quick_product_4', description: 'Quick product 4' });
        this.shortcuts.set('Ctrl+5', { action: 'quick_product_5', description: 'Quick product 5' });
    }
    
    bindEvents() {
        document.addEventListener('keydown', (e) => {
            if (!this.isEnabled) return;
            
            // Prevent default behavior for our shortcuts
            if (this.handleKeyDown(e)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
        
        // Handle key sequences
        document.addEventListener('keyup', (e) => {
            if (!this.isEnabled) return;
            this.handleKeyUp(e);
        });
    }
    
    handleKeyDown(e) {
        const key = this.getKeyString(e);
        const shortcut = this.shortcuts.get(key);
        
        if (shortcut) {
            this.executeShortcut(shortcut, e);
            return true;
        }
        
        // Handle key sequences
        this.keySequence.push(key);
        this.resetSequenceTimeout();
        
        return false;
    }
    
    handleKeyUp(e) {
        // Handle special cases for key up events
        const key = this.getKeyString(e);
        
        if (key === 'Escape') {
            this.cancelCurrentOperation();
        }
    }
    
    getKeyString(e) {
        const parts = [];
        
        if (e.ctrlKey) parts.push('Ctrl');
        if (e.altKey) parts.push('Alt');
        if (e.shiftKey) parts.push('Shift');
        if (e.metaKey) parts.push('Meta');
        
        // Handle special keys
        const specialKeys = {
            ' ': 'Space',
            'ArrowUp': 'ArrowUp',
            'ArrowDown': 'ArrowDown',
            'ArrowLeft': 'ArrowLeft',
            'ArrowRight': 'ArrowRight',
            'Escape': 'Escape',
            'Enter': 'Enter',
            'Tab': 'Tab',
            'Delete': 'Delete',
            'Backspace': 'Backspace',
            'Home': 'Home',
            'End': 'End',
            'PageUp': 'PageUp',
            'PageDown': 'PageDown'
        };
        
        const key = specialKeys[e.key] || e.key;
        parts.push(key);
        
        return parts.join('+');
    }
    
    executeShortcut(shortcut, event) {
        console.log(`Executing shortcut: ${shortcut.action}`);
        
        try {
            switch (shortcut.action) {
                case 'focus_search':
                    this.app.focusProductSearch();
                    break;
                case 'add_customer':
                    this.app.addCustomer();
                    break;
                case 'open_inventory':
                    this.app.openInventory();
                    break;
                case 'process_payment':
                    this.app.processPayment();
                    break;
                case 'refresh_products':
                    this.app.refreshProducts();
                    break;
                case 'open_reports':
                    this.app.openReports();
                    break;
                case 'open_settings':
                    this.app.openSettings();
                    break;
                case 'print_receipt':
                    this.app.printReceipt();
                    break;
                case 'open_customers':
                    this.app.openCustomers();
                    break;
                case 'open_users':
                    this.app.openUsers();
                    break;
                case 'toggle_fullscreen':
                    this.app.toggleFullscreen();
                    break;
                case 'open_help':
                    this.app.openHelp();
                    break;
                case 'new_sale':
                    this.app.startNewSale();
                    break;
                case 'save_sale':
                    this.app.saveCurrentSale();
                    break;
                case 'undo_last_item':
                    this.app.undoLastItem();
                    break;
                case 'redo_last_item':
                    this.app.redoLastItem();
                    break;
                case 'select_all_items':
                    this.app.selectAllCartItems();
                    break;
                case 'delete_selected':
                    this.app.deleteSelectedItems();
                    break;
                case 'open_history':
                    this.app.openSalesHistory();
                    break;
                case 'open_logout':
                    this.app.logout();
                    break;
                case 'refresh_data':
                    this.app.refreshAllData();
                    break;
                case 'open_tax_settings':
                    this.app.openTaxSettings();
                    break;
                case 'close_current_tab':
                    this.app.closeCurrentTab();
                    break;
                case 'quit_application':
                    this.app.quitApplication();
                    break;
                case 'payment_cash':
                    this.app.setPaymentMethod('cash');
                    break;
                case 'payment_card':
                    this.app.setPaymentMethod('card');
                    break;
                case 'payment_mobile':
                    this.app.setPaymentMethod('mobile');
                    break;
                case 'payment_credit':
                    this.app.setPaymentMethod('credit');
                    break;
                case 'clear_cart':
                    this.app.clearCart();
                    break;
                case 'apply_discount':
                    this.app.applyDiscount();
                    break;
                case 'apply_tax':
                    this.app.applyTax();
                    break;
                case 'remove_tax':
                    this.app.removeTax();
                    break;
                case 'save_draft':
                    this.app.saveAsDraft();
                    break;
                case 'load_draft':
                    this.app.loadDraft();
                    break;
                case 'export_data':
                    this.app.exportData();
                    break;
                case 'import_data':
                    this.app.importData();
                    break;
                case 'open_backup':
                    this.app.openBackup();
                    break;
                case 'open_messages':
                    this.app.openMessages();
                    break;
                case 'open_notifications':
                    this.app.openNotifications();
                    break;
                case 'select_product_by_number':
                    this.app.selectProductByNumber(shortcut.data.number);
                    break;
                case 'navigate_up':
                    this.app.navigateUp();
                    break;
                case 'navigate_down':
                    this.app.navigateDown();
                    break;
                case 'navigate_left':
                    this.app.navigateLeft();
                    break;
                case 'navigate_right':
                    this.app.navigateRight();
                    break;
                case 'cancel_operation':
                    this.app.cancelCurrentOperation();
                    break;
                case 'confirm_operation':
                    this.app.confirmCurrentOperation();
                    break;
                case 'next_field':
                    this.app.moveToNextField();
                    break;
                case 'previous_field':
                    this.app.moveToPreviousField();
                    break;
                case 'backspace':
                    this.app.handleBackspace();
                    break;
                case 'toggle_pause':
                    this.app.togglePause();
                    break;
                case 'go_to_start':
                    this.app.goToStart();
                    break;
                case 'go_to_end':
                    this.app.goToEnd();
                    break;
                case 'page_up':
                    this.app.pageUp();
                    break;
                case 'page_down':
                    this.app.pageDown();
                    break;
                case 'open_barcode_scanner':
                    this.app.openBarcodeScanner();
                    break;
                case 'toggle_barcode_mode':
                    this.app.toggleBarcodeMode();
                    break;
                case 'quick_product_1':
                case 'quick_product_2':
                case 'quick_product_3':
                case 'quick_product_4':
                case 'quick_product_5':
                    const productNumber = parseInt(shortcut.action.split('_')[2]);
                    this.app.selectQuickProduct(productNumber);
                    break;
                default:
                    console.log(`Unknown shortcut action: ${shortcut.action}`);
            }
        } catch (error) {
            console.error('Error executing shortcut:', error);
            this.app.showError('Error executing shortcut: ' + error.message);
        }
    }
    
    resetSequenceTimeout() {
        if (this.sequenceTimeout) {
            clearTimeout(this.sequenceTimeout);
        }
        
        this.sequenceTimeout = setTimeout(() => {
            this.keySequence = [];
        }, 1000); // Reset sequence after 1 second of inactivity
    }
    
    cancelCurrentOperation() {
        // Cancel any ongoing operations
        this.app.cancelCurrentOperation();
    }
    
    enable() {
        this.isEnabled = true;
    }
    
    disable() {
        this.isEnabled = false;
    }
    
    getShortcutsList() {
        const list = [];
        for (const [key, shortcut] of this.shortcuts) {
            list.push({
                key: key,
                action: shortcut.action,
                description: shortcut.description
            });
        }
        return list;
    }
    
    showShortcutsHelp() {
        const shortcuts = this.getShortcutsList();
        const helpWindow = window.open('', 'shortcuts_help', 'width=800,height=600,scrollbars=yes');
        
        let html = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Keyboard Shortcuts Help</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #333; }
                    .shortcut-group { margin-bottom: 30px; }
                    .shortcut-group h2 { color: #666; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                    .shortcut-item { display: flex; justify-content: space-between; margin: 8px 0; padding: 8px; background: #f9f9f9; border-radius: 4px; }
                    .shortcut-key { font-weight: bold; color: #007bff; background: #e7f3ff; padding: 2px 8px; border-radius: 3px; }
                    .shortcut-desc { color: #333; }
                </style>
            </head>
            <body>
                <h1>DPS POS FBR Integrated - Keyboard Shortcuts</h1>
        `;
        
        // Group shortcuts by type
        const groups = {
            'Function Keys': [],
            'Ctrl Combinations': [],
            'Alt Combinations': [],
            'Navigation': [],
            'Quick Access': []
        };
        
        shortcuts.forEach(shortcut => {
            if (shortcut.key.startsWith('F')) {
                groups['Function Keys'].push(shortcut);
            } else if (shortcut.key.startsWith('Ctrl')) {
                groups['Ctrl Combinations'].push(shortcut);
            } else if (shortcut.key.startsWith('Alt')) {
                groups['Alt Combinations'].push(shortcut);
            } else if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Tab', 'Shift+Tab'].includes(shortcut.key)) {
                groups['Navigation'].push(shortcut);
            } else {
                groups['Quick Access'].push(shortcut);
            }
        });
        
        Object.keys(groups).forEach(groupName => {
            if (groups[groupName].length > 0) {
                html += `<div class="shortcut-group"><h2>${groupName}</h2>`;
                groups[groupName].forEach(shortcut => {
                    html += `
                        <div class="shortcut-item">
                            <span class="shortcut-key">${shortcut.key}</span>
                            <span class="shortcut-desc">${shortcut.description}</span>
                        </div>
                    `;
                });
                html += '</div>';
            }
        });
        
        html += '</body></html>';
        
        helpWindow.document.write(html);
        helpWindow.document.close();
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = KeyboardShortcuts;
}