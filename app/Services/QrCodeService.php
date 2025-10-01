<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    /**
     * Get QR code settings for tenant
     */
    public function getQrCodeSettings(int $tenantId)
    {
        $settings = DB::table('qr_code_settings')
            ->where('tenant_id', $tenantId)
            ->first();
        
        if (!$settings) {
            return $this->getDefaultQrCodeSettings();
        }
        
        return json_decode($settings->settings, true);
    }

    /**
     * Update QR code settings
     */
    public function updateQrCodeSettings(int $tenantId, array $settings)
    {
        $settingsData = [
            'qr_code_type' => $settings['qr_code_type'],
            'qr_code_size' => $settings['qr_code_size'],
            'qr_code_position' => $settings['qr_code_position'],
            'custom_text' => $settings['custom_text'] ?? '',
            'updated_at' => now()
        ];
        
        DB::table('qr_code_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            array_merge($settingsData, [
                'tenant_id' => $tenantId,
                'settings' => json_encode($settingsData),
                'created_at' => now()
            ])
        );
        
        return $settingsData;
    }

    /**
     * Generate QR code
     */
    public function generateQrCode(string $data, string $type, int $size = 200, int $tenantId = null)
    {
        $settings = $tenantId ? $this->getQrCodeSettings($tenantId) : $this->getDefaultQrCodeSettings();
        
        $qrCodeData = $this->prepareQrCodeData($data, $type, $settings);
        
        return $this->generateQrCodeImage($qrCodeData, $size);
    }

    /**
     * Generate QR code image
     */
    public function generateQrCodeImage(string $data, int $size = 200)
    {
        $qrCode = QrCode::format('png')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($data);
        
        return base64_encode($qrCode);
    }

    /**
     * Generate FBR verification QR code
     */
    public function generateFbrQrCode(array $invoiceData, int $size = 200)
    {
        $qrCodeData = $this->buildFbrQrCodeData($invoiceData);
        return $this->generateQrCodeImage($qrCodeData, $size);
    }

    /**
     * Generate DPS verification QR code
     */
    public function generateDpsQrCode(array $invoiceData, int $size = 200)
    {
        $qrCodeData = $this->buildDpsQrCodeData($invoiceData);
        return $this->generateQrCodeImage($qrCodeData, $size);
    }

    /**
     * Generate custom QR code
     */
    public function generateCustomQrCode(string $text, int $size = 200)
    {
        return $this->generateQrCodeImage($text, $size);
    }

    /**
     * Validate QR code data
     */
    public function validateQrCodeData(string $data, string $type)
    {
        $errors = [];
        
        switch ($type) {
            case 'fbr_verification':
                $errors = $this->validateFbrQrCodeData($data);
                break;
            case 'dps_verification':
                $errors = $this->validateDpsQrCodeData($data);
                break;
            case 'custom':
                $errors = $this->validateCustomQrCodeData($data);
                break;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get QR code statistics
     */
    public function getQrCodeStatistics(int $tenantId, string $period = 'month')
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $stats = DB::table('qr_code_logs')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_generated,
                SUM(CASE WHEN type = "fbr_verification" THEN 1 ELSE 0 END) as fbr_qr_codes,
                SUM(CASE WHEN type = "dps_verification" THEN 1 ELSE 0 END) as dps_qr_codes,
                SUM(CASE WHEN type = "custom" THEN 1 ELSE 0 END) as custom_qr_codes
            ')
            ->first();
        
        return (array) $stats;
    }

    /**
     * Log QR code generation
     */
    public function logQrCodeGeneration(int $tenantId, string $type, string $data, int $size)
    {
        DB::table('qr_code_logs')->insert([
            'tenant_id' => $tenantId,
            'type' => $type,
            'data' => $data,
            'size' => $size,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Get QR code history
     */
    public function getQrCodeHistory(int $tenantId, int $limit = 50)
    {
        return DB::table('qr_code_logs')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Prepare QR code data based on type
     */
    private function prepareQrCodeData(string $data, string $type, array $settings)
    {
        switch ($type) {
            case 'fbr_verification':
                return $this->buildFbrQrCodeData(json_decode($data, true));
            case 'dps_verification':
                return $this->buildDpsQrCodeData(json_decode($data, true));
            case 'custom':
                return $settings['custom_text'] ?? $data;
            default:
                return $data;
        }
    }

    /**
     * Build FBR QR code data
     */
    private function buildFbrQrCodeData(array $invoiceData)
    {
        $fbrData = [
            'invoice_number' => $invoiceData['invoice_number'] ?? '',
            'invoice_date' => $invoiceData['invoice_date'] ?? '',
            'total_amount' => $invoiceData['total_amount'] ?? 0,
            'fbr_invoice_number' => $invoiceData['fbr_invoice_number'] ?? '',
            'verification_url' => 'https://fbr.gov.pk/verify',
            'timestamp' => now()->toISOString()
        ];
        
        return json_encode($fbrData);
    }

    /**
     * Build DPS QR code data
     */
    private function buildDpsQrCodeData(array $invoiceData)
    {
        $dpsData = [
            'invoice_number' => $invoiceData['invoice_number'] ?? '',
            'invoice_date' => $invoiceData['invoice_date'] ?? '',
            'total_amount' => $invoiceData['total_amount'] ?? 0,
            'verification_url' => url('/verify/' . ($invoiceData['invoice_number'] ?? '')),
            'timestamp' => now()->toISOString()
        ];
        
        return json_encode($dpsData);
    }

    /**
     * Validate FBR QR code data
     */
    private function validateFbrQrCodeData(string $data)
    {
        $errors = [];
        $fbrData = json_decode($data, true);
        
        if (!$fbrData) {
            $errors[] = 'Invalid JSON data';
            return $errors;
        }
        
        $requiredFields = ['invoice_number', 'invoice_date', 'total_amount', 'fbr_invoice_number'];
        
        foreach ($requiredFields as $field) {
            if (!isset($fbrData[$field]) || empty($fbrData[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }
        
        if (isset($fbrData['total_amount']) && (!is_numeric($fbrData['total_amount']) || $fbrData['total_amount'] < 0)) {
            $errors[] = 'Total amount must be a positive number';
        }
        
        return $errors;
    }

    /**
     * Validate DPS QR code data
     */
    private function validateDpsQrCodeData(string $data)
    {
        $errors = [];
        $dpsData = json_decode($data, true);
        
        if (!$dpsData) {
            $errors[] = 'Invalid JSON data';
            return $errors;
        }
        
        $requiredFields = ['invoice_number', 'invoice_date', 'total_amount'];
        
        foreach ($requiredFields as $field) {
            if (!isset($dpsData[$field]) || empty($dpsData[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }
        
        if (isset($dpsData['total_amount']) && (!is_numeric($dpsData['total_amount']) || $dpsData['total_amount'] < 0)) {
            $errors[] = 'Total amount must be a positive number';
        }
        
        return $errors;
    }

    /**
     * Validate custom QR code data
     */
    private function validateCustomQrCodeData(string $data)
    {
        $errors = [];
        
        if (empty($data)) {
            $errors[] = 'Custom text cannot be empty';
        }
        
        if (strlen($data) > 1000) {
            $errors[] = 'Custom text is too long (max 1000 characters)';
        }
        
        return $errors;
    }

    /**
     * Get default QR code settings
     */
    private function getDefaultQrCodeSettings()
    {
        return [
            'qr_code_type' => 'fbr_verification',
            'qr_code_size' => 200,
            'qr_code_position' => 'bottom_right',
            'custom_text' => ''
        ];
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

    /**
     * Generate QR code for invoice
     */
    public function generateInvoiceQrCode(array $invoiceData, int $tenantId, int $size = 200)
    {
        $settings = $this->getQrCodeSettings($tenantId);
        
        switch ($settings['qr_code_type']) {
            case 'fbr_verification':
                $qrCodeData = $this->buildFbrQrCodeData($invoiceData);
                break;
            case 'dps_verification':
                $qrCodeData = $this->buildDpsQrCodeData($invoiceData);
                break;
            case 'custom':
                $qrCodeData = $settings['custom_text'] ?? '';
                break;
            default:
                $qrCodeData = $this->buildFbrQrCodeData($invoiceData);
        }
        
        $qrCodeImage = $this->generateQrCodeImage($qrCodeData, $size);
        
        // Log the generation
        $this->logQrCodeGeneration($tenantId, $settings['qr_code_type'], $qrCodeData, $size);
        
        return [
            'image' => $qrCodeImage,
            'data' => $qrCodeData,
            'type' => $settings['qr_code_type']
        ];
    }

    /**
     * Get QR code position CSS
     */
    public function getQrCodePositionCss(string $position)
    {
        $positions = [
            'top_left' => 'position: absolute; top: 10px; left: 10px;',
            'top_right' => 'position: absolute; top: 10px; right: 10px;',
            'bottom_left' => 'position: absolute; bottom: 10px; left: 10px;',
            'bottom_right' => 'position: absolute; bottom: 10px; right: 10px;',
            'center' => 'text-align: center; margin: 20px auto;'
        ];
        
        return $positions[$position] ?? $positions['bottom_right'];
    }

    /**
     * Generate QR code with custom styling
     */
    public function generateStyledQrCode(string $data, int $size = 200, array $options = [])
    {
        $qrCode = QrCode::format('png')
            ->size($size)
            ->margin($options['margin'] ?? 1)
            ->errorCorrection($options['error_correction'] ?? 'M')
            ->color($options['foreground_color'] ?? 0, $options['foreground_color'] ?? 0, $options['foreground_color'] ?? 0)
            ->backgroundColor($options['background_color'] ?? 255, $options['background_color'] ?? 255, $options['background_color'] ?? 255)
            ->generate($data);
        
        return base64_encode($qrCode);
    }

    /**
     * Generate QR code for multiple invoices
     */
    public function generateBulkQrCodes(array $invoices, int $tenantId, int $size = 200)
    {
        $results = [];
        
        foreach ($invoices as $invoice) {
            $qrCode = $this->generateInvoiceQrCode($invoice, $tenantId, $size);
            $results[] = [
                'invoice_number' => $invoice['invoice_number'],
                'qr_code' => $qrCode
            ];
        }
        
        return $results;
    }

    /**
     * Get QR code usage analytics
     */
    public function getQrCodeAnalytics(int $tenantId, string $period = 'month')
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $analytics = DB::table('qr_code_logs')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as daily_count,
                type
            ')
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();
        
        return $analytics;
    }
}