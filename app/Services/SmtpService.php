<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\MailManager;
use Carbon\Carbon;

class SmtpService
{
    /**
     * Configure SMTP settings for tenant
     */
    public function configureSmtp(int $tenantId, array $smtpSettings)
    {
        try {
            // Validate SMTP settings
            $this->validateSmtpSettings($smtpSettings);
            
            // Test SMTP connection
            $testResult = $this->testSmtpConnection($smtpSettings);
            
            if (!$testResult['success']) {
                throw new \Exception('SMTP connection test failed: ' . $testResult['error']);
            }
            
            // Save SMTP settings
            $this->saveSmtpSettings($tenantId, $smtpSettings);
            
            // Configure mail for tenant
            $this->configureMailForTenant($tenantId, $smtpSettings);
            
            return [
                'success' => true,
                'message' => 'SMTP settings configured successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('SMTP configuration failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test SMTP connection
     */
    public function testSmtpConnection(array $smtpSettings)
    {
        try {
            // Temporarily configure mail
            $this->configureMailTemporarily($smtpSettings);
            
            // Send test email
            Mail::raw('This is a test email to verify SMTP configuration.', function($message) use ($smtpSettings) {
                $message->to($smtpSettings['test_email'])
                        ->subject('SMTP Test Email');
            });
            
            return [
                'success' => true,
                'message' => 'SMTP connection test successful'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get SMTP settings for tenant
     */
    public function getSmtpSettings(int $tenantId)
    {
        $settings = DB::table('smtp_settings')
            ->where('tenant_id', $tenantId)
            ->first();
        
        if (!$settings) {
            return $this->getDefaultSmtpSettings();
        }
        
        return json_decode($settings->settings, true);
    }

    /**
     * Update SMTP settings
     */
    public function updateSmtpSettings(int $tenantId, array $smtpSettings)
    {
        try {
            // Validate settings
            $this->validateSmtpSettings($smtpSettings);
            
            // Test connection
            $testResult = $this->testSmtpConnection($smtpSettings);
            
            if (!$testResult['success']) {
                throw new \Exception('SMTP connection test failed: ' . $testResult['error']);
            }
            
            // Update settings
            $this->saveSmtpSettings($tenantId, $smtpSettings);
            
            // Reconfigure mail
            $this->configureMailForTenant($tenantId, $smtpSettings);
            
            return [
                'success' => true,
                'message' => 'SMTP settings updated successfully'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send email using tenant's SMTP settings
     */
    public function sendEmail(int $tenantId, string $to, string $subject, string $body, array $options = [])
    {
        try {
            // Get tenant SMTP settings
            $smtpSettings = $this->getSmtpSettings($tenantId);
            
            // Configure mail for tenant
            $this->configureMailForTenant($tenantId, $smtpSettings);
            
            // Send email
            Mail::send('emails.template', ['body' => $body], function($message) use ($to, $subject, $options) {
                $message->to($to)
                        ->subject($subject);
                
                if (isset($options['cc'])) {
                    $message->cc($options['cc']);
                }
                
                if (isset($options['bcc'])) {
                    $message->bcc($options['bcc']);
                }
                
                if (isset($options['attachments'])) {
                    foreach ($options['attachments'] as $attachment) {
                        $message->attach($attachment);
                    }
                }
            });
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Email sending failed', [
                'tenant_id' => $tenantId,
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send bulk emails
     */
    public function sendBulkEmails(int $tenantId, array $recipients, string $subject, string $body, array $options = [])
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($recipients as $recipient) {
            $result = $this->sendEmail($tenantId, $recipient['email'], $subject, $body, $options);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
            
            $results[] = [
                'email' => $recipient['email'],
                'result' => $result
            ];
        }
        
        return [
            'success' => $successCount > 0,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * Get email templates
     */
    public function getEmailTemplates(int $tenantId)
    {
        return DB::table('email_templates')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create email template
     */
    public function createEmailTemplate(int $tenantId, array $templateData)
    {
        $templateData['tenant_id'] = $tenantId;
        $templateData['created_at'] = now();
        $templateData['updated_at'] = now();
        
        $templateId = DB::table('email_templates')->insertGetId($templateData);
        
        return [
            'success' => true,
            'template_id' => $templateId,
            'message' => 'Email template created successfully'
        ];
    }

    /**
     * Update email template
     */
    public function updateEmailTemplate(int $templateId, array $templateData)
    {
        $templateData['updated_at'] = now();
        
        DB::table('email_templates')
            ->where('id', $templateId)
            ->update($templateData);
        
        return [
            'success' => true,
            'message' => 'Email template updated successfully'
        ];
    }

    /**
     * Delete email template
     */
    public function deleteEmailTemplate(int $templateId)
    {
        DB::table('email_templates')
            ->where('id', $templateId)
            ->delete();
        
        return [
            'success' => true,
            'message' => 'Email template deleted successfully'
        ];
    }

    /**
     * Send email using template
     */
    public function sendEmailWithTemplate(int $tenantId, int $templateId, array $variables, array $recipients)
    {
        try {
            $template = DB::table('email_templates')->find($templateId);
            
            if (!$template || $template->tenant_id !== $tenantId) {
                throw new \Exception('Template not found');
            }
            
            // Replace variables in template
            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body, $variables);
            
            // Send to all recipients
            $results = [];
            foreach ($recipients as $recipient) {
                $result = $this->sendEmail($tenantId, $recipient['email'], $subject, $body);
                $results[] = [
                    'email' => $recipient['email'],
                    'result' => $result
                ];
            }
            
            return [
                'success' => true,
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get email statistics
     */
    public function getEmailStatistics(int $tenantId, string $period = 'month')
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $stats = DB::table('email_logs')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent_emails,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_emails,
                SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_emails,
                SUM(CASE WHEN status = "opened" THEN 1 ELSE 0 END) as opened_emails
            ')
            ->first();
        
        $deliveryRate = $stats->total_emails > 0 
            ? round(($stats->delivered_emails / $stats->total_emails) * 100, 2)
            : 0;
        
        $openRate = $stats->delivered_emails > 0 
            ? round(($stats->opened_emails / $stats->delivered_emails) * 100, 2)
            : 0;
        
        return [
            'total_emails' => $stats->total_emails ?? 0,
            'sent_emails' => $stats->sent_emails ?? 0,
            'failed_emails' => $stats->failed_emails ?? 0,
            'delivered_emails' => $stats->delivered_emails ?? 0,
            'opened_emails' => $stats->opened_emails ?? 0,
            'delivery_rate' => $deliveryRate,
            'open_rate' => $openRate
        ];
    }

    /**
     * Validate SMTP settings
     */
    private function validateSmtpSettings(array $settings)
    {
        $required = ['host', 'port', 'username', 'password', 'encryption'];
        
        foreach ($required as $field) {
            if (!isset($settings[$field]) || empty($settings[$field])) {
                throw new \Exception("Required field '{$field}' is missing");
            }
        }
        
        if (!in_array($settings['encryption'], ['tls', 'ssl', 'none'])) {
            throw new \Exception('Invalid encryption type');
        }
        
        if (!is_numeric($settings['port']) || $settings['port'] < 1 || $settings['port'] > 65535) {
            throw new \Exception('Invalid port number');
        }
    }

    /**
     * Test SMTP connection
     */
    private function testSmtpConnection(array $smtpSettings)
    {
        try {
            // Configure mail temporarily
            $this->configureMailTemporarily($smtpSettings);
            
            // Send test email
            Mail::raw('This is a test email to verify SMTP configuration.', function($message) use ($smtpSettings) {
                $message->to($smtpSettings['test_email'])
                        ->subject('SMTP Test Email');
            });
            
            return [
                'success' => true,
                'message' => 'SMTP connection test successful'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Configure mail temporarily
     */
    private function configureMailTemporarily(array $smtpSettings)
    {
        Config::set('mail.mailers.smtp.host', $smtpSettings['host']);
        Config::set('mail.mailers.smtp.port', $smtpSettings['port']);
        Config::set('mail.mailers.smtp.username', $smtpSettings['username']);
        Config::set('mail.mailers.smtp.password', $smtpSettings['password']);
        Config::set('mail.mailers.smtp.encryption', $smtpSettings['encryption']);
        Config::set('mail.mailers.smtp.timeout', 60);
        Config::set('mail.from.address', $smtpSettings['from_email']);
        Config::set('mail.from.name', $smtpSettings['from_name']);
    }

    /**
     * Configure mail for tenant
     */
    private function configureMailForTenant(int $tenantId, array $smtpSettings)
    {
        // Store tenant-specific mail configuration
        $config = [
            'host' => $smtpSettings['host'],
            'port' => $smtpSettings['port'],
            'username' => $smtpSettings['username'],
            'password' => $smtpSettings['password'],
            'encryption' => $smtpSettings['encryption'],
            'from_email' => $smtpSettings['from_email'],
            'from_name' => $smtpSettings['from_name']
        ];
        
        // This would be used by a custom mail manager
        // to route emails to the correct SMTP server based on tenant
        DB::table('tenant_mail_configs')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'tenant_id' => $tenantId,
                'config' => json_encode($config),
                'updated_at' => now()
            ]
        );
    }

    /**
     * Save SMTP settings
     */
    private function saveSmtpSettings(int $tenantId, array $smtpSettings)
    {
        // Remove sensitive data for storage
        $settingsToStore = $smtpSettings;
        unset($settingsToStore['test_email']);
        
        DB::table('smtp_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'tenant_id' => $tenantId,
                'settings' => json_encode($settingsToStore),
                'updated_at' => now()
            ]
        );
    }

    /**
     * Get default SMTP settings
     */
    private function getDefaultSmtpSettings()
    {
        return [
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'username' => config('mail.mailers.smtp.username'),
            'password' => '',
            'encryption' => config('mail.mailers.smtp.encryption'),
            'from_email' => config('mail.from.address'),
            'from_name' => config('mail.from.name')
        ];
    }

    /**
     * Replace variables in template
     */
    private function replaceVariables(string $content, array $variables)
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }
        
        return $content;
    }

    /**
     * Get period start date
     */
    private function getPeriodStartDate(string $period)
    {
        switch ($period) {
            case 'day':
                return now()->startOfDay();
            case 'week':
                return now()->startOfWeek();
            case 'month':
                return now()->startOfMonth();
            case 'quarter':
                return now()->startOfQuarter();
            case 'year':
                return now()->startOfYear();
            default:
                return now()->startOfMonth();
        }
    }
}