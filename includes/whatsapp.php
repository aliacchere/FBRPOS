<?php
/**
 * DPS POS FBR Integrated - WhatsApp Integration
 */

class WhatsAppIntegration {
    
    /**
     * Send receipt via WhatsApp
     */
    public static function sendReceipt($phone_number, $sale_data) {
        $phone_number = self::formatPhoneNumber($phone_number);
        
        if (!$phone_number) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }
        
        $message = self::buildReceiptMessage($sale_data);
        $url = self::buildWhatsAppURL($phone_number, $message);
        
        return [
            'success' => true,
            'url' => $url,
            'message' => $message
        ];
    }
    
    /**
     * Send admin notification
     */
    public static function sendAdminNotification($phone_number, $type, $data) {
        $phone_number = self::formatPhoneNumber($phone_number);
        
        if (!$phone_number) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }
        
        $message = self::buildNotificationMessage($type, $data);
        $url = self::buildWhatsAppURL($phone_number, $message);
        
        return [
            'success' => true,
            'url' => $url,
            'message' => $message
        ];
    }
    
    /**
     * Format phone number for WhatsApp
     */
    private static function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if missing
        if (strlen($phone) == 10) {
            $phone = '92' . $phone;
        } elseif (strlen($phone) == 11 && substr($phone, 0, 1) == '0') {
            $phone = '92' . substr($phone, 1);
        }
        
        // Validate Pakistani phone number
        if (strlen($phone) == 12 && substr($phone, 0, 2) == '92') {
            return $phone;
        }
        
        return false;
    }
    
    /**
     * Build receipt message
     */
    private static function buildReceiptMessage($sale_data) {
        $message = "🛍️ *Thank you for your purchase!*\n\n";
        $message .= "📄 *Invoice:* " . $sale_data['invoice_number'] . "\n";
        $message .= "📅 *Date:* " . format_date($sale_data['created_at']) . "\n";
        $message .= "💰 *Total:* " . format_currency($sale_data['total_amount']) . "\n\n";
        
        if (isset($sale_data['fbr_invoice_number'])) {
            $message .= "✅ *FBR Verified*\n";
            $message .= "🔗 *FBR Invoice:* " . $sale_data['fbr_invoice_number'] . "\n\n";
        }
        
        $message .= "📱 *View Receipt:* " . APP_URL . "/receipt.php?id=" . $sale_data['id'] . "\n\n";
        $message .= "Thank you for choosing us! 🙏";
        
        return $message;
    }
    
    /**
     * Build notification message
     */
    private static function buildNotificationMessage($type, $data) {
        switch ($type) {
            case 'daily_summary':
                $message = "📊 *Daily Sales Summary*\n\n";
                $message .= "🏪 *Business:* " . $data['business_name'] . "\n";
                $message .= "📅 *Date:* " . $data['date'] . "\n";
                $message .= "🛒 *Total Sales:* " . $data['total_sales'] . "\n";
                $message .= "💰 *Total Revenue:* " . format_currency($data['total_revenue']) . "\n";
                $message .= "✅ *FBR Synced:* " . $data['fbr_synced'] . "\n";
                $message .= "⏳ *Pending Sync:* " . $data['fbr_pending'] . "\n\n";
                $message .= "Great job! 🎉";
                break;
                
            case 'low_stock':
                $message = "⚠️ *Low Stock Alert*\n\n";
                $message .= "🏪 *Business:* " . $data['business_name'] . "\n\n";
                $message .= "📦 *Low Stock Products:*\n";
                foreach ($data['products'] as $product) {
                    $message .= "• " . $product['name'] . " - " . $product['stock'] . " left\n";
                }
                $message .= "\nPlease restock soon! 📈";
                break;
                
            case 'fbr_sync_status':
                $message = "🔄 *FBR Sync Status Update*\n\n";
                $message .= "🏪 *Business:* " . $data['business_name'] . "\n";
                $message .= "📊 *Sync Rate:* " . $data['sync_rate'] . "%\n";
                $message .= "✅ *Synced:* " . $data['synced'] . "\n";
                $message .= "⏳ *Pending:* " . $data['pending'] . "\n";
                $message .= "❌ *Failed:* " . $data['failed'] . "\n\n";
                
                if ($data['sync_rate'] < 90) {
                    $message .= "⚠️ Sync rate is below 90%. Please check FBR settings.";
                } else {
                    $message .= "✅ FBR sync is working well!";
                }
                break;
                
            default:
                $message = "📱 *DPS POS Notification*\n\n" . $data['message'];
        }
        
        return $message;
    }
    
    /**
     * Build WhatsApp URL
     */
    private static function buildWhatsAppURL($phone_number, $message) {
        return WHATSAPP_API_URL . "?phone=" . $phone_number . "&text=" . urlencode($message);
    }
    
    /**
     * Send daily summary to admin
     */
    public static function sendDailySummary($tenant_id) {
        $tenant = db_fetch("SELECT * FROM tenants WHERE id = ?", [$tenant_id]);
        
        if (!$tenant || empty($tenant['phone'])) {
            return ['success' => false, 'error' => 'Tenant phone not configured'];
        }
        
        // Get today's statistics
        $stats = db_fetch("
            SELECT 
                COUNT(*) as total_sales,
                SUM(total_amount) as total_revenue,
                SUM(CASE WHEN fbr_status = 'synced' THEN 1 ELSE 0 END) as fbr_synced,
                SUM(CASE WHEN fbr_status = 'pending' THEN 1 ELSE 0 END) as fbr_pending
            FROM sales 
            WHERE tenant_id = ? AND DATE(created_at) = CURDATE()
        ", [$tenant_id]);
        
        $data = [
            'business_name' => $tenant['business_name'],
            'date' => date('d/m/Y'),
            'total_sales' => $stats['total_sales'],
            'total_revenue' => $stats['total_revenue'],
            'fbr_synced' => $stats['fbr_synced'],
            'fbr_pending' => $stats['fbr_pending']
        ];
        
        return self::sendAdminNotification($tenant['phone'], 'daily_summary', $data);
    }
    
    /**
     * Send low stock alert
     */
    public static function sendLowStockAlert($tenant_id) {
        $tenant = db_fetch("SELECT * FROM tenants WHERE id = ?", [$tenant_id]);
        
        if (!$tenant || empty($tenant['phone'])) {
            return ['success' => false, 'error' => 'Tenant phone not configured'];
        }
        
        // Get low stock products
        $products = db_fetch_all("
            SELECT name, stock_quantity 
            FROM products 
            WHERE tenant_id = ? AND stock_quantity <= min_stock_level AND is_active = 1
            ORDER BY stock_quantity ASC
            LIMIT 5
        ", [$tenant_id]);
        
        if (empty($products)) {
            return ['success' => false, 'error' => 'No low stock products'];
        }
        
        $data = [
            'business_name' => $tenant['business_name'],
            'products' => array_map(function($product) {
                return [
                    'name' => $product['name'],
                    'stock' => $product['stock_quantity']
                ];
            }, $products)
        ];
        
        return self::sendAdminNotification($tenant['phone'], 'low_stock', $data);
    }
    
    /**
     * Send FBR sync status update
     */
    public static function sendFBRStatusUpdate($tenant_id) {
        $tenant = db_fetch("SELECT * FROM tenants WHERE id = ?", [$tenant_id]);
        
        if (!$tenant || empty($tenant['phone'])) {
            return ['success' => false, 'error' => 'Tenant phone not configured'];
        }
        
        // Get FBR statistics
        $stats = db_fetch("
            SELECT 
                COUNT(*) as total_sales,
                SUM(CASE WHEN fbr_status = 'synced' THEN 1 ELSE 0 END) as synced,
                SUM(CASE WHEN fbr_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN fbr_status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM sales 
            WHERE tenant_id = ?
        ", [$tenant_id]);
        
        $sync_rate = $stats['total_sales'] > 0 ? 
            round(($stats['synced'] / $stats['total_sales']) * 100, 1) : 0;
        
        $data = [
            'business_name' => $tenant['business_name'],
            'sync_rate' => $sync_rate,
            'synced' => $stats['synced'],
            'pending' => $stats['pending'],
            'failed' => $stats['failed']
        ];
        
        return self::sendAdminNotification($tenant['phone'], 'fbr_sync_status', $data);
    }
}
?>