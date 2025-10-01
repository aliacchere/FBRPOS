<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiUrl;
    protected $businessNumber;

    public function __construct()
    {
        $this->apiUrl = config('whatsapp.api_url', 'https://api.whatsapp.com/send');
        $this->businessNumber = config('whatsapp.business_number');
    }

    /**
     * Send receipt via WhatsApp
     */
    public function sendReceipt(string $phoneNumber, Sale $sale)
    {
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        
        if (!$phoneNumber) {
            return [
                'success' => false,
                'error' => 'Invalid phone number format'
            ];
        }

        $message = $this->buildReceiptMessage($sale);
        $url = $this->buildWhatsAppURL($phoneNumber, $message);

        return [
            'success' => true,
            'url' => $url,
            'message' => $message
        ];
    }

    /**
     * Send admin notification
     */
    public function sendAdminNotification(string $phoneNumber, string $type, array $data)
    {
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        
        if (!$phoneNumber) {
            return [
                'success' => false,
                'error' => 'Invalid phone number format'
            ];
        }

        $message = $this->buildNotificationMessage($type, $data);
        $url = $this->buildWhatsAppURL($phoneNumber, $message);

        return [
            'success' => true,
            'url' => $url,
            'message' => $message
        ];
    }

    /**
     * Send daily summary to admin
     */
    public function sendDailySummary($tenantId)
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        
        if (!$tenant || empty($tenant->phone)) {
            return [
                'success' => false,
                'error' => 'Tenant phone not configured'
            ];
        }

        // Get today's statistics
        $stats = \DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('sale_date', now()->toDateString())
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(total_amount) as total_revenue,
                SUM(CASE WHEN fbr_status = "synced" THEN 1 ELSE 0 END) as fbr_synced,
                SUM(CASE WHEN fbr_status = "pending" THEN 1 ELSE 0 END) as fbr_pending
            ')
            ->first();

        $data = [
            'business_name' => $tenant->business_name,
            'date' => now()->format('d/m/Y'),
            'total_sales' => $stats->total_sales ?? 0,
            'total_revenue' => $stats->total_revenue ?? 0,
            'fbr_synced' => $stats->fbr_synced ?? 0,
            'fbr_pending' => $stats->fbr_pending ?? 0
        ];

        return $this->sendAdminNotification($tenant->phone, 'daily_summary', $data);
    }

    /**
     * Send low stock alert
     */
    public function sendLowStockAlert($tenantId)
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        
        if (!$tenant || empty($tenant->phone)) {
            return [
                'success' => false,
                'error' => 'Tenant phone not configured'
            ];
        }

        // Get low stock products
        $products = \DB::table('products')
            ->where('tenant_id', $tenantId)
            ->whereRaw('stock_quantity <= min_stock_level')
            ->where('is_active', true)
            ->orderBy('stock_quantity', 'asc')
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            return [
                'success' => false,
                'error' => 'No low stock products'
            ];
        }

        $data = [
            'business_name' => $tenant->business_name,
            'products' => $products->map(function ($product) {
                return [
                    'name' => $product->name,
                    'stock' => $product->stock_quantity
                ];
            })->toArray()
        ];

        return $this->sendAdminNotification($tenant->phone, 'low_stock', $data);
    }

    /**
     * Send FBR sync status update
     */
    public function sendFbrStatusUpdate($tenantId)
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        
        if (!$tenant || empty($tenant->phone)) {
            return [
                'success' => false,
                'error' => 'Tenant phone not configured'
            ];
        }

        // Get FBR statistics
        $stats = \DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(CASE WHEN fbr_status = "synced" THEN 1 ELSE 0 END) as synced,
                SUM(CASE WHEN fbr_status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN fbr_status = "failed" THEN 1 ELSE 0 END) as failed
            ')
            ->first();

        $syncRate = $stats->total_sales > 0 
            ? round(($stats->synced / $stats->total_sales) * 100, 1)
            : 0;

        $data = [
            'business_name' => $tenant->business_name,
            'sync_rate' => $syncRate,
            'synced' => $stats->synced ?? 0,
            'pending' => $stats->pending ?? 0,
            'failed' => $stats->failed ?? 0
        ];

        return $this->sendAdminNotification($tenant->phone, 'fbr_sync_status', $data);
    }

    /**
     * Format phone number for WhatsApp
     */
    private function formatPhoneNumber(string $phone): ?string
    {
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
        
        return null;
    }

    /**
     * Build receipt message
     */
    private function buildReceiptMessage(Sale $sale): string
    {
        $message = "🛍️ *Thank you for your purchase!*\n\n";
        $message .= "📄 *Invoice:* " . $sale->invoice_number . "\n";
        $message .= "📅 *Date:* " . $sale->sale_date . "\n";
        $message .= "💰 *Total:* " . $this->formatCurrency($sale->total_amount) . "\n\n";
        
        if ($sale->fbr_invoice_number) {
            $message .= "✅ *FBR Verified*\n";
            $message .= "🔗 *FBR Invoice:* " . $sale->fbr_invoice_number . "\n\n";
        }
        
        $message .= "📱 *View Receipt:* " . config('app.url') . "/receipt/" . $sale->id . "\n\n";
        $message .= "Thank you for choosing us! 🙏";
        
        return $message;
    }

    /**
     * Build notification message
     */
    private function buildNotificationMessage(string $type, array $data): string
    {
        switch ($type) {
            case 'daily_summary':
                $message = "📊 *Daily Sales Summary*\n\n";
                $message .= "🏪 *Business:* " . $data['business_name'] . "\n";
                $message .= "📅 *Date:* " . $data['date'] . "\n";
                $message .= "🛒 *Total Sales:* " . $data['total_sales'] . "\n";
                $message .= "💰 *Total Revenue:* " . $this->formatCurrency($data['total_revenue']) . "\n";
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
                $message = "📱 *DPS POS Notification*\n\n" . ($data['message'] ?? '');
        }
        
        return $message;
    }

    /**
     * Build WhatsApp URL
     */
    private function buildWhatsAppURL(string $phoneNumber, string $message): string
    {
        return $this->apiUrl . "?phone=" . $phoneNumber . "&text=" . urlencode($message);
    }

    /**
     * Format currency
     */
    private function formatCurrency(float $amount): string
    {
        return 'Rs. ' . number_format($amount, 2);
    }
}