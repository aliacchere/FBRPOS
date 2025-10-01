<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\LowStockAlert;
use App\Mail\SalesSummary;
use App\Mail\FbrFailureAlert;
use App\Mail\BackupCompleted;
use App\Mail\BackupFailed;
use Carbon\Carbon;

class NotificationService
{
    protected $whatsappService;
    protected $smtpService;

    public function __construct(WhatsAppService $whatsappService, SmtpService $smtpService)
    {
        $this->whatsappService = $whatsappService;
        $this->smtpService = $smtpService;
    }

    /**
     * Send low stock alert
     */
    public function sendLowStockAlert(int $tenantId, $lowStockProducts)
    {
        $tenant = DB::table('tenants')->find($tenantId);
        $settings = $this->getNotificationSettings($tenantId);
        
        if (!$settings['low_stock_enabled']) {
            return;
        }

        $recipients = $this->getNotificationRecipients($tenantId, 'low_stock');
        
        foreach ($recipients as $recipient) {
            try {
                // Send email
                if ($settings['email_enabled'] && $recipient['email']) {
                    Mail::to($recipient['email'])->send(new LowStockAlert($tenant, $lowStockProducts));
                }
                
                // Send WhatsApp
                if ($settings['whatsapp_enabled'] && $recipient['phone']) {
                    $this->sendLowStockWhatsApp($tenant, $lowStockProducts, $recipient['phone']);
                }
                
            } catch (\Exception $e) {
                Log::error('Failed to send low stock alert', [
                    'tenant_id' => $tenantId,
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Send sales summary
     */
    public function sendSalesSummary(int $tenantId, string $period = 'daily')
    {
        $tenant = DB::table('tenants')->find($tenantId);
        $settings = $this->getNotificationSettings($tenantId);
        
        if (!$settings['sales_summary_enabled']) {
            return;
        }

        $summary = $this->getSalesSummary($tenantId, $period);
        $recipients = $this->getNotificationRecipients($tenantId, 'sales_summary');
        
        foreach ($recipients as $recipient) {
            try {
                // Send email
                if ($settings['email_enabled'] && $recipient['email']) {
                    Mail::to($recipient['email'])->send(new SalesSummary($tenant, $summary, $period));
                }
                
                // Send WhatsApp
                if ($settings['whatsapp_enabled'] && $recipient['phone']) {
                    $this->sendSalesSummaryWhatsApp($tenant, $summary, $period, $recipient['phone']);
                }
                
            } catch (\Exception $e) {
                Log::error('Failed to send sales summary', [
                    'tenant_id' => $tenantId,
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Send FBR failure alert
     */
    public function sendFbrFailureAlert(int $tenantId, array $failureData)
    {
        $tenant = DB::table('tenants')->find($tenantId);
        $settings = $this->getNotificationSettings($tenantId);
        
        if (!$settings['fbr_failure_enabled']) {
            return;
        }

        $recipients = $this->getNotificationRecipients($tenantId, 'fbr_failure');
        
        foreach ($recipients as $recipient) {
            try {
                // Send email
                if ($settings['email_enabled'] && $recipient['email']) {
                    Mail::to($recipient['email'])->send(new FbrFailureAlert($tenant, $failureData));
                }
                
                // Send WhatsApp
                if ($settings['whatsapp_enabled'] && $recipient['phone']) {
                    $this->sendFbrFailureWhatsApp($tenant, $failureData, $recipient['phone']);
                }
                
            } catch (\Exception $e) {
                Log::error('Failed to send FBR failure alert', [
                    'tenant_id' => $tenantId,
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Send backup completed notification
     */
    public function sendBackupCompleted(int $tenantId, array $backupData)
    {
        $tenant = DB::table('tenants')->find($tenantId);
        $settings = $this->getNotificationSettings($tenantId);
        
        if (!$settings['backup_enabled']) {
            return;
        }

        $recipients = $this->getNotificationRecipients($tenantId, 'backup');
        
        foreach ($recipients as $recipient) {
            try {
                // Send email
                if ($settings['email_enabled'] && $recipient['email']) {
                    Mail::to($recipient['email'])->send(new BackupCompleted($tenant, $backupData));
                }
                
            } catch (\Exception $e) {
                Log::error('Failed to send backup completed notification', [
                    'tenant_id' => $tenantId,
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Send backup failed notification
     */
    public function sendBackupFailed(int $tenantId, array $failureData)
    {
        $tenant = DB::table('tenants')->find($tenantId);
        $settings = $this->getNotificationSettings($tenantId);
        
        if (!$settings['backup_enabled']) {
            return;
        }

        $recipients = $this->getNotificationRecipients($tenantId, 'backup');
        
        foreach ($recipients as $recipient) {
            try {
                // Send email
                if ($settings['email_enabled'] && $recipient['email']) {
                    Mail::to($recipient['email'])->send(new BackupFailed($tenant, $failureData));
                }
                
            } catch (\Exception $e) {
                Log::error('Failed to send backup failed notification', [
                    'tenant_id' => $tenantId,
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Send custom notification
     */
    public function sendCustomNotification(int $tenantId, string $type, array $data, array $recipients = [])
    {
        $tenant = DB::table('tenants')->find($tenantId);
        $settings = $this->getNotificationSettings($tenantId);
        
        if (empty($recipients)) {
            $recipients = $this->getNotificationRecipients($tenantId, $type);
        }
        
        foreach ($recipients as $recipient) {
            try {
                // Send email
                if ($settings['email_enabled'] && $recipient['email']) {
                    $this->sendCustomEmail($tenant, $type, $data, $recipient['email']);
                }
                
                // Send WhatsApp
                if ($settings['whatsapp_enabled'] && $recipient['phone']) {
                    $this->sendCustomWhatsApp($tenant, $type, $data, $recipient['phone']);
                }
                
            } catch (\Exception $e) {
                Log::error('Failed to send custom notification', [
                    'tenant_id' => $tenantId,
                    'type' => $type,
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Send low stock WhatsApp message
     */
    private function sendLowStockWhatsApp($tenant, $lowStockProducts, string $phone)
    {
        $message = "🚨 *Low Stock Alert*\n\n";
        $message .= "Business: {$tenant->business_name}\n";
        $message .= "Date: " . now()->format('Y-m-d H:i:s') . "\n\n";
        $message .= "The following products are running low on stock:\n\n";
        
        foreach ($lowStockProducts as $product) {
            $message .= "• {$product->name} (SKU: {$product->sku})\n";
            $message .= "  Current Stock: {$product->stock_quantity}\n";
            $message .= "  Min Level: {$product->min_stock_level}\n\n";
        }
        
        $message .= "Please restock these items as soon as possible.";
        
        $this->whatsappService->sendMessage($phone, $message);
    }

    /**
     * Send sales summary WhatsApp message
     */
    private function sendSalesSummaryWhatsApp($tenant, array $summary, string $period, string $phone)
    {
        $message = "📊 *Sales Summary - " . ucfirst($period) . "*\n\n";
        $message .= "Business: {$tenant->business_name}\n";
        $message .= "Period: " . $summary['period'] . "\n\n";
        $message .= "Total Sales: {$summary['total_sales']}\n";
        $message .= "Total Revenue: ₨" . number_format($summary['total_revenue'], 2) . "\n";
        $message .= "Average Sale: ₨" . number_format($summary['average_sale'], 2) . "\n";
        $message .= "Top Product: {$summary['top_product']}\n\n";
        $message .= "Keep up the great work! 💪";
        
        $this->whatsappService->sendMessage($phone, $message);
    }

    /**
     * Send FBR failure WhatsApp message
     */
    private function sendFbrFailureWhatsApp($tenant, array $failureData, string $phone)
    {
        $message = "⚠️ *FBR Integration Failure*\n\n";
        $message .= "Business: {$tenant->business_name}\n";
        $message .= "Time: " . now()->format('Y-m-d H:i:s') . "\n\n";
        $message .= "Invoice: {$failureData['invoice_number']}\n";
        $message .= "Error: {$failureData['error']}\n";
        $message .= "Status: {$failureData['status']}\n\n";
        $message .= "Please check the FBR integration settings.";
        
        $this->whatsappService->sendMessage($phone, $message);
    }

    /**
     * Send custom email
     */
    private function sendCustomEmail($tenant, string $type, array $data, string $email)
    {
        $subject = $this->getEmailSubject($type, $tenant);
        $template = $this->getEmailTemplate($type);
        
        Mail::send($template, [
            'tenant' => $tenant,
            'data' => $data,
            'type' => $type
        ], function($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }

    /**
     * Send custom WhatsApp message
     */
    private function sendCustomWhatsApp($tenant, string $type, array $data, string $phone)
    {
        $message = $this->getWhatsAppMessage($type, $tenant, $data);
        $this->whatsappService->sendMessage($phone, $message);
    }

    /**
     * Get notification settings
     */
    private function getNotificationSettings(int $tenantId)
    {
        $settings = DB::table('notification_settings')
            ->where('tenant_id', $tenantId)
            ->first();
        
        if (!$settings) {
            return [
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'low_stock_enabled' => true,
                'sales_summary_enabled' => true,
                'fbr_failure_enabled' => true,
                'backup_enabled' => true
            ];
        }
        
        return json_decode($settings->settings, true);
    }

    /**
     * Get notification recipients
     */
    private function getNotificationRecipients(int $tenantId, string $type)
    {
        $recipients = DB::table('notification_recipients')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereJsonContains('notification_types', $type)
            ->get();
        
        return $recipients->map(function($recipient) {
            return [
                'name' => $recipient->name,
                'email' => $recipient->email,
                'phone' => $recipient->phone
            ];
        })->toArray();
    }

    /**
     * Get sales summary data
     */
    private function getSalesSummary(int $tenantId, string $period)
    {
        $startDate = $this->getPeriodStartDate($period);
        $endDate = $this->getPeriodEndDate($period);
        
        $summary = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as average_sale
            ')
            ->first();
        
        $topProduct = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->selectRaw('products.name, SUM(sale_items.quantity) as total_quantity')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->first();
        
        return [
            'period' => $this->getPeriodDescription($period),
            'total_sales' => $summary->total_sales ?? 0,
            'total_revenue' => $summary->total_revenue ?? 0,
            'average_sale' => $summary->average_sale ?? 0,
            'top_product' => $topProduct->name ?? 'N/A'
        ];
    }

    /**
     * Get period start date
     */
    private function getPeriodStartDate(string $period)
    {
        switch ($period) {
            case 'daily':
                return now()->startOfDay();
            case 'weekly':
                return now()->startOfWeek();
            case 'monthly':
                return now()->startOfMonth();
            case 'quarterly':
                return now()->startOfQuarter();
            case 'yearly':
                return now()->startOfYear();
            default:
                return now()->startOfDay();
        }
    }

    /**
     * Get period end date
     */
    private function getPeriodEndDate(string $period)
    {
        switch ($period) {
            case 'daily':
                return now()->endOfDay();
            case 'weekly':
                return now()->endOfWeek();
            case 'monthly':
                return now()->endOfMonth();
            case 'quarterly':
                return now()->endOfQuarter();
            case 'yearly':
                return now()->endOfYear();
            default:
                return now()->endOfDay();
        }
    }

    /**
     * Get period description
     */
    private function getPeriodDescription(string $period)
    {
        switch ($period) {
            case 'daily':
                return now()->format('Y-m-d');
            case 'weekly':
                return 'Week of ' . now()->startOfWeek()->format('Y-m-d');
            case 'monthly':
                return now()->format('F Y');
            case 'quarterly':
                return 'Q' . now()->quarter . ' ' . now()->year;
            case 'yearly':
                return now()->year;
            default:
                return now()->format('Y-m-d');
        }
    }

    /**
     * Get email subject
     */
    private function getEmailSubject(string $type, $tenant)
    {
        $subjects = [
            'low_stock' => "Low Stock Alert - {$tenant->business_name}",
            'sales_summary' => "Sales Summary - {$tenant->business_name}",
            'fbr_failure' => "FBR Integration Failure - {$tenant->business_name}",
            'backup' => "Backup Notification - {$tenant->business_name}",
            'custom' => "Notification - {$tenant->business_name}"
        ];
        
        return $subjects[$type] ?? "Notification - {$tenant->business_name}";
    }

    /**
     * Get email template
     */
    private function getEmailTemplate(string $type)
    {
        $templates = [
            'low_stock' => 'emails.low-stock-alert',
            'sales_summary' => 'emails.sales-summary',
            'fbr_failure' => 'emails.fbr-failure-alert',
            'backup' => 'emails.backup-notification',
            'custom' => 'emails.custom-notification'
        ];
        
        return $templates[$type] ?? 'emails.custom-notification';
    }

    /**
     * Get WhatsApp message
     */
    private function getWhatsAppMessage(string $type, $tenant, array $data)
    {
        $message = "📢 *Notification*\n\n";
        $message .= "Business: {$tenant->business_name}\n";
        $message .= "Time: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        switch ($type) {
            case 'low_stock':
                $message .= "Low stock alert for multiple products.";
                break;
            case 'sales_summary':
                $message .= "Daily sales summary is available.";
                break;
            case 'fbr_failure':
                $message .= "FBR integration failure detected.";
                break;
            case 'backup':
                $message .= "Backup process completed.";
                break;
            default:
                $message .= $data['message'] ?? 'Custom notification';
        }
        
        return $message;
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings(int $tenantId, array $settings)
    {
        DB::table('notification_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'tenant_id' => $tenantId,
                'settings' => json_encode($settings),
                'updated_at' => now()
            ]
        );
    }

    /**
     * Add notification recipient
     */
    public function addNotificationRecipient(int $tenantId, array $recipientData)
    {
        DB::table('notification_recipients')->insert([
            'tenant_id' => $tenantId,
            'name' => $recipientData['name'],
            'email' => $recipientData['email'],
            'phone' => $recipientData['phone'],
            'notification_types' => json_encode($recipientData['notification_types']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Test notification delivery
     */
    public function testNotification(int $tenantId, string $type, string $email = null, string $phone = null)
    {
        $tenant = DB::table('tenants')->find($tenantId);
        
        $testData = [
            'message' => 'This is a test notification to verify your notification settings.',
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];
        
        $recipients = [];
        
        if ($email) {
            $recipients[] = ['email' => $email, 'phone' => null];
        }
        
        if ($phone) {
            $recipients[] = ['email' => null, 'phone' => $phone];
        }
        
        if (empty($recipients)) {
            $recipients = $this->getNotificationRecipients($tenantId, $type);
        }
        
        $this->sendCustomNotification($tenantId, $type, $testData, $recipients);
        
        return [
            'success' => true,
            'message' => 'Test notification sent successfully'
        ];
    }
}