<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class TemplateService
{
    protected $qrCodeService;

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Get templates for tenant
     */
    public function getTemplates(int $tenantId)
    {
        return DB::table('templates')
            ->where('tenant_id', $tenantId)
            ->orderBy('type')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get templates by type
     */
    public function getTemplatesByType(int $tenantId, string $type)
    {
        return DB::table('templates')
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create new template
     */
    public function createTemplate(int $tenantId, array $templateData)
    {
        $templateData['tenant_id'] = $tenantId;
        $templateData['created_at'] = now();
        $templateData['updated_at'] = now();
        
        // If this is set as default, unset other defaults for this type
        if ($templateData['is_default'] ?? false) {
            $this->unsetDefaultForType($tenantId, $templateData['type']);
        }
        
        $templateId = DB::table('templates')->insertGetId($templateData);
        
        // Create initial version
        $this->createTemplateVersion($templateId, $templateData['content'], 'Initial version');
        
        return $this->getTemplate($templateId);
    }

    /**
     * Update template
     */
    public function updateTemplate(int $templateId, int $tenantId, array $templateData)
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template || $template->tenant_id !== $tenantId) {
            throw new \Exception('Template not found');
        }
        
        $templateData['updated_at'] = now();
        
        // If this is set as default, unset other defaults for this type
        if ($templateData['is_default'] ?? false) {
            $this->unsetDefaultForType($tenantId, $template->type);
        }
        
        DB::table('templates')
            ->where('id', $templateId)
            ->update($templateData);
        
        // Create new version if content changed
        if (isset($templateData['content']) && $templateData['content'] !== $template->content) {
            $this->createTemplateVersion($templateId, $templateData['content'], 'Template updated');
        }
        
        return $this->getTemplate($templateId);
    }

    /**
     * Delete template
     */
    public function deleteTemplate(int $templateId, int $tenantId)
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template || $template->tenant_id !== $tenantId) {
            throw new \Exception('Template not found');
        }
        
        if ($template->is_default) {
            throw new \Exception('Cannot delete default template');
        }
        
        // Delete template versions
        DB::table('template_versions')
            ->where('template_id', $templateId)
            ->delete();
        
        // Delete template
        DB::table('templates')
            ->where('id', $templateId)
            ->delete();
    }

    /**
     * Duplicate template
     */
    public function duplicateTemplate(int $templateId, string $newName, int $tenantId)
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template || $template->tenant_id !== $tenantId) {
            throw new \Exception('Template not found');
        }
        
        $duplicateData = [
            'tenant_id' => $tenantId,
            'name' => $newName,
            'type' => $template->type,
            'content' => $template->content,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        $newTemplateId = DB::table('templates')->insertGetId($duplicateData);
        
        // Create initial version
        $this->createTemplateVersion($newTemplateId, $template->content, 'Duplicated from ' . $template->name);
        
        return $this->getTemplate($newTemplateId);
    }

    /**
     * Set default template
     */
    public function setDefaultTemplate(int $templateId, int $tenantId)
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template || $template->tenant_id !== $tenantId) {
            throw new \Exception('Template not found');
        }
        
        // Unset other defaults for this type
        $this->unsetDefaultForType($tenantId, $template->type);
        
        // Set this as default
        DB::table('templates')
            ->where('id', $templateId)
            ->update(['is_default' => true, 'updated_at' => now()]);
    }

    /**
     * Preview template
     */
    public function previewTemplate(string $content, string $type, int $tenantId)
    {
        $previewData = $this->getPreviewData($type, $tenantId);
        $processedContent = $this->processTemplate($content, $previewData, $tenantId);
        
        return [
            'html' => $processedContent,
            'preview_data' => $previewData
        ];
    }

    /**
     * Generate document from template
     */
    public function generateDocument(int $templateId, array $data, string $format, int $tenantId)
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template || $template->tenant_id !== $tenantId) {
            throw new \Exception('Template not found');
        }
        
        $processedContent = $this->processTemplate($template->content, $data, $tenantId);
        
        if ($format === 'pdf') {
            return $this->generatePdf($processedContent, $template->name);
        } else {
            return [
                'html' => $processedContent,
                'filename' => $template->name . '.html'
            ];
        }
    }

    /**
     * Import template
     */
    public function importTemplate($file, string $type, int $tenantId)
    {
        $content = file_get_contents($file->getPathname());
        $templateData = json_decode($content, true);
        
        if (!$templateData) {
            throw new \Exception('Invalid template file');
        }
        
        $importData = [
            'name' => $templateData['name'] ?? 'Imported Template',
            'type' => $type,
            'content' => $templateData['content'] ?? '',
            'is_default' => false
        ];
        
        return $this->createTemplate($tenantId, $importData);
    }

    /**
     * Export template
     */
    public function exportTemplate(int $templateId, int $tenantId)
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template || $template->tenant_id !== $tenantId) {
            throw new \Exception('Template not found');
        }
        
        $exportData = [
            'name' => $template->name,
            'type' => $template->type,
            'content' => $template->content,
            'exported_at' => now()->toISOString()
        ];
        
        $filename = 'template_' . $template->name . '_' . now()->format('Y-m-d_H-i-s') . '.json';
        $filepath = storage_path('app/exports/' . $filename);
        
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        file_put_contents($filepath, json_encode($exportData, JSON_PRETTY_PRINT));
        
        return [
            'path' => $filepath,
            'filename' => $filename
        ];
    }

    /**
     * Get available tags
     */
    public function getAvailableTags()
    {
        return [
            'business' => [
                '{{business_name}}' => 'Business Name',
                '{{business_address}}' => 'Business Address',
                '{{business_phone}}' => 'Business Phone',
                '{{business_email}}' => 'Business Email',
                '{{business_website}}' => 'Business Website',
                '{{ntn}}' => 'NTN Number',
                '{{strn}}' => 'STRN Number'
            ],
            'invoice' => [
                '{{invoice_number}}' => 'Invoice Number',
                '{{invoice_date}}' => 'Invoice Date',
                '{{due_date}}' => 'Due Date',
                '{{fbr_invoice_number}}' => 'FBR Invoice Number',
                '{{fbr_invoice_date}}' => 'FBR Invoice Date',
                '{{payment_terms}}' => 'Payment Terms'
            ],
            'customer' => [
                '{{customer_name}}' => 'Customer Name',
                '{{customer_address}}' => 'Customer Address',
                '{{customer_phone}}' => 'Customer Phone',
                '{{customer_email}}' => 'Customer Email',
                '{{customer_ntn}}' => 'Customer NTN'
            ],
            'items' => [
                '{{items_table}}' => 'Items Table',
                '{{item_name}}' => 'Item Name',
                '{{item_quantity}}' => 'Item Quantity',
                '{{item_price}}' => 'Item Price',
                '{{item_total}}' => 'Item Total'
            ],
            'totals' => [
                '{{subtotal}}' => 'Subtotal',
                '{{tax_amount}}' => 'Tax Amount',
                '{{discount_amount}}' => 'Discount Amount',
                '{{total_amount}}' => 'Total Amount',
                '{{amount_paid}}' => 'Amount Paid',
                '{{balance_due}}' => 'Balance Due'
            ],
            'qr_code' => [
                '{{qr_code}}' => 'QR Code',
                '{{qr_code_fbr}}' => 'FBR QR Code',
                '{{qr_code_dps}}' => 'DPS QR Code'
            ],
            'system' => [
                '{{generated_date}}' => 'Generated Date',
                '{{generated_time}}' => 'Generated Time',
                '{{page_number}}' => 'Page Number',
                '{{total_pages}}' => 'Total Pages'
            ]
        ];
    }

    /**
     * Process template with data
     */
    public function processTemplate(string $content, array $data, int $tenantId)
    {
        // Get tenant data
        $tenant = DB::table('tenants')->find($tenantId);
        
        // Replace business tags
        $content = str_replace('{{business_name}}', $tenant->business_name ?? '', $content);
        $content = str_replace('{{business_address}}', $tenant->address ?? '', $content);
        $content = str_replace('{{business_phone}}', $tenant->phone ?? '', $content);
        $content = str_replace('{{business_email}}', $tenant->email ?? '', $content);
        $content = str_replace('{{business_website}}', $tenant->website ?? '', $content);
        $content = str_replace('{{ntn}}', $tenant->ntn ?? '', $content);
        $content = str_replace('{{strn}}', $tenant->strn ?? '', $content);
        
        // Replace data tags
        foreach ($data as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }
        
        // Process items table
        if (strpos($content, '{{items_table}}') !== false) {
            $itemsTable = $this->generateItemsTable($data['items'] ?? []);
            $content = str_replace('{{items_table}}', $itemsTable, $content);
        }
        
        // Process QR codes
        $content = $this->processQrCodes($content, $data, $tenantId);
        
        // Process system tags
        $content = str_replace('{{generated_date}}', now()->format('Y-m-d'), $content);
        $content = str_replace('{{generated_time}}', now()->format('H:i:s'), $content);
        
        return $content;
    }

    /**
     * Generate items table
     */
    private function generateItemsTable(array $items)
    {
        $table = '<table class="items-table" style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $table .= '<thead>';
        $table .= '<tr style="background-color: #f5f5f5;">';
        $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Item</th>';
        $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Qty</th>';
        $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Price</th>';
        $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total</th>';
        $table .= '</tr>';
        $table .= '</thead>';
        $table .= '<tbody>';
        
        foreach ($items as $item) {
            $table .= '<tr>';
            $table .= '<td style="border: 1px solid #ddd; padding: 8px;">' . ($item['name'] ?? '') . '</td>';
            $table .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' . ($item['quantity'] ?? '') . '</td>';
            $table .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($item['unit_price'] ?? 0, 2) . '</td>';
            $table .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($item['total_price'] ?? 0, 2) . '</td>';
            $table .= '</tr>';
        }
        
        $table .= '</tbody>';
        $table .= '</table>';
        
        return $table;
    }

    /**
     * Process QR codes
     */
    private function processQrCodes(string $content, array $data, int $tenantId)
    {
        $qrCodeSettings = $this->qrCodeService->getQrCodeSettings($tenantId);
        
        if ($qrCodeSettings['qr_code_type'] === 'disabled') {
            $content = str_replace('{{qr_code}}', '', $content);
            $content = str_replace('{{qr_code_fbr}}', '', $content);
            $content = str_replace('{{qr_code_dps}}', '', $content);
            return $content;
        }
        
        // Generate QR code based on type
        if ($qrCodeSettings['qr_code_type'] === 'fbr_verification') {
            $qrCodeData = $this->generateFbrQrCodeData($data);
        } elseif ($qrCodeSettings['qr_code_type'] === 'dps_verification') {
            $qrCodeData = $this->generateDpsQrCodeData($data);
        } else {
            $qrCodeData = $qrCodeSettings['custom_text'] ?? '';
        }
        
        $qrCodeImage = $this->qrCodeService->generateQrCodeImage(
            $qrCodeData,
            $qrCodeSettings['qr_code_size']
        );
        
        $qrCodeHtml = '<img src="data:image/png;base64,' . $qrCodeImage . '" style="max-width: ' . $qrCodeSettings['qr_code_size'] . 'px;">';
        
        $content = str_replace('{{qr_code}}', $qrCodeHtml, $content);
        $content = str_replace('{{qr_code_fbr}}', $qrCodeHtml, $content);
        $content = str_replace('{{qr_code_dps}}', $qrCodeHtml, $content);
        
        return $content;
    }

    /**
     * Generate FBR QR code data
     */
    private function generateFbrQrCodeData(array $data)
    {
        return json_encode([
            'invoice_number' => $data['invoice_number'] ?? '',
            'invoice_date' => $data['invoice_date'] ?? '',
            'total_amount' => $data['total_amount'] ?? 0,
            'fbr_invoice_number' => $data['fbr_invoice_number'] ?? '',
            'verification_url' => 'https://fbr.gov.pk/verify'
        ]);
    }

    /**
     * Generate DPS QR code data
     */
    private function generateDpsQrCodeData(array $data)
    {
        return json_encode([
            'invoice_number' => $data['invoice_number'] ?? '',
            'invoice_date' => $data['invoice_date'] ?? '',
            'total_amount' => $data['total_amount'] ?? 0,
            'verification_url' => url('/verify/' . ($data['invoice_number'] ?? ''))
        ]);
    }

    /**
     * Generate PDF
     */
    private function generatePdf(string $content, string $filename)
    {
        $pdf = Pdf::loadHTML($content);
        $pdf->setPaper('A4', 'portrait');
        
        $tempFile = tempnam(sys_get_temp_dir(), 'template_');
        $pdf->save($tempFile);
        
        return [
            'path' => $tempFile,
            'filename' => $filename . '.pdf',
            'mime_type' => 'application/pdf'
        ];
    }

    /**
     * Get preview data
     */
    public function getPreviewData(string $type, int $tenantId)
    {
        $tenant = DB::table('tenants')->find($tenantId);
        
        $baseData = [
            'invoice_number' => 'INV-2024-001',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'fbr_invoice_number' => 'FBR-2024-001',
            'fbr_invoice_date' => now()->format('Y-m-d'),
            'payment_terms' => 'Net 30',
            'customer_name' => 'John Doe',
            'customer_address' => '123 Main Street, City, Country',
            'customer_phone' => '+1234567890',
            'customer_email' => 'john@example.com',
            'customer_ntn' => '1234567890123',
            'items' => [
                [
                    'name' => 'Sample Product 1',
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'total_price' => 200.00
                ],
                [
                    'name' => 'Sample Product 2',
                    'quantity' => 1,
                    'unit_price' => 150.00,
                    'total_price' => 150.00
                ]
            ],
            'subtotal' => 350.00,
            'tax_amount' => 63.00,
            'discount_amount' => 0.00,
            'total_amount' => 413.00,
            'amount_paid' => 0.00,
            'balance_due' => 413.00
        ];
        
        return $baseData;
    }

    /**
     * Validate template
     */
    public function validateTemplate(string $content, string $type, int $tenantId)
    {
        $errors = [];
        $warnings = [];
        
        // Check for required tags based on type
        $requiredTags = $this->getRequiredTagsForType($type);
        
        foreach ($requiredTags as $tag) {
            if (strpos($content, $tag) === false) {
                $warnings[] = "Recommended tag '{$tag}' not found";
            }
        }
        
        // Check for invalid tags
        $availableTags = $this->getAvailableTags();
        $allTags = [];
        foreach ($availableTags as $category => $tags) {
            $allTags = array_merge($allTags, array_keys($tags));
        }
        
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        foreach ($matches[1] as $tag) {
            if (!in_array("{{$tag}}", $allTags)) {
                $warnings[] = "Unknown tag '{{$tag}}'";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Get required tags for type
     */
    private function getRequiredTagsForType(string $type)
    {
        $requiredTags = [
            'invoice' => ['{{invoice_number}}', '{{invoice_date}}', '{{customer_name}}', '{{total_amount}}'],
            'receipt' => ['{{invoice_number}}', '{{invoice_date}}', '{{customer_name}}', '{{total_amount}}'],
            'quote' => ['{{invoice_number}}', '{{invoice_date}}', '{{customer_name}}', '{{total_amount}}'],
            'delivery_note' => ['{{invoice_number}}', '{{invoice_date}}', '{{customer_name}}']
        ];
        
        return $requiredTags[$type] ?? [];
    }

    /**
     * Get template statistics
     */
    public function getTemplateStatistics(int $tenantId)
    {
        $stats = DB::table('templates')
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total_templates,
                SUM(CASE WHEN type = "invoice" THEN 1 ELSE 0 END) as invoice_templates,
                SUM(CASE WHEN type = "receipt" THEN 1 ELSE 0 END) as receipt_templates,
                SUM(CASE WHEN type = "quote" THEN 1 ELSE 0 END) as quote_templates,
                SUM(CASE WHEN type = "delivery_note" THEN 1 ELSE 0 END) as delivery_note_templates,
                SUM(CASE WHEN is_default = 1 THEN 1 ELSE 0 END) as default_templates
            ')
            ->first();
        
        return (array) $stats;
    }

    /**
     * Get template
     */
    private function getTemplate(int $templateId)
    {
        return DB::table('templates')->find($templateId);
    }

    /**
     * Unset default for type
     */
    private function unsetDefaultForType(int $tenantId, string $type)
    {
        DB::table('templates')
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->update(['is_default' => false]);
    }

    /**
     * Create template version
     */
    private function createTemplateVersion(int $templateId, string $content, string $description)
    {
        DB::table('template_versions')->insert([
            'template_id' => $templateId,
            'content' => $content,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Get template history
     */
    public function getTemplateHistory(int $templateId, int $tenantId)
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template || $template->tenant_id !== $tenantId) {
            throw new \Exception('Template not found');
        }
        
        return DB::table('template_versions')
            ->where('template_id', $templateId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Restore template version
     */
    public function restoreVersion(int $templateId, int $versionId, int $tenantId)
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template || $template->tenant_id !== $tenantId) {
            throw new \Exception('Template not found');
        }
        
        $version = DB::table('template_versions')
            ->where('id', $versionId)
            ->where('template_id', $templateId)
            ->first();
        
        if (!$version) {
            throw new \Exception('Version not found');
        }
        
        // Update template with version content
        DB::table('templates')
            ->where('id', $templateId)
            ->update([
                'content' => $version->content,
                'updated_at' => now()
            ]);
        
        // Create new version
        $this->createTemplateVersion($templateId, $version->content, 'Restored from version ' . $version->id);
        
        return $this->getTemplate($templateId);
    }

    /**
     * Reset to default
     */
    public function resetToDefault(int $templateId, int $tenantId)
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template || $template->tenant_id !== $tenantId) {
            throw new \Exception('Template not found');
        }
        
        $defaultContent = $this->getDefaultTemplateContent($template->type);
        
        DB::table('templates')
            ->where('id', $templateId)
            ->update([
                'content' => $defaultContent,
                'updated_at' => now()
            ]);
        
        // Create new version
        $this->createTemplateVersion($templateId, $defaultContent, 'Reset to default');
        
        return $this->getTemplate($templateId);
    }

    /**
     * Get default template content
     */
    private function getDefaultTemplateContent(string $type)
    {
        $defaultTemplates = [
            'invoice' => $this->getDefaultInvoiceTemplate(),
            'receipt' => $this->getDefaultReceiptTemplate(),
            'quote' => $this->getDefaultQuoteTemplate(),
            'delivery_note' => $this->getDefaultDeliveryNoteTemplate()
        ];
        
        return $defaultTemplates[$type] ?? '';
    }

    /**
     * Get default invoice template
     */
    private function getDefaultInvoiceTemplate()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .business-info { margin-bottom: 20px; }
        .invoice-details { float: right; }
        .customer-info { margin-bottom: 20px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f5f5f5; }
        .totals { float: right; margin-top: 20px; }
        .footer { margin-top: 50px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{business_name}}</h1>
        <p>{{business_address}}</p>
        <p>Phone: {{business_phone}} | Email: {{business_email}}</p>
    </div>
    
    <div class="invoice-details">
        <h2>INVOICE</h2>
        <p><strong>Invoice #:</strong> {{invoice_number}}</p>
        <p><strong>Date:</strong> {{invoice_date}}</p>
        <p><strong>Due Date:</strong> {{due_date}}</p>
    </div>
    
    <div class="customer-info">
        <h3>Bill To:</h3>
        <p><strong>{{customer_name}}</strong></p>
        <p>{{customer_address}}</p>
        <p>Phone: {{customer_phone}}</p>
        <p>Email: {{customer_email}}</p>
    </div>
    
    {{items_table}}
    
    <div class="totals">
        <p><strong>Subtotal: ₨{{subtotal}}</strong></p>
        <p>Tax: ₨{{tax_amount}}</p>
        <p>Discount: ₨{{discount_amount}}</p>
        <p><strong>Total: ₨{{total_amount}}</strong></p>
    </div>
    
    <div class="footer">
        <p>Thank you for your business!</p>
        {{qr_code}}
    </div>
</body>
</html>';
    }

    /**
     * Get default receipt template
     */
    private function getDefaultReceiptTemplate()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .receipt-details { text-align: center; margin-bottom: 20px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f5f5f5; }
        .totals { text-align: center; margin-top: 20px; }
        .footer { margin-top: 50px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{business_name}}</h1>
        <p>{{business_address}}</p>
        <p>Phone: {{business_phone}}</p>
    </div>
    
    <div class="receipt-details">
        <h2>RECEIPT</h2>
        <p><strong>Receipt #:</strong> {{invoice_number}}</p>
        <p><strong>Date:</strong> {{invoice_date}}</p>
    </div>
    
    {{items_table}}
    
    <div class="totals">
        <p><strong>Total: ₨{{total_amount}}</strong></p>
        <p>Amount Paid: ₨{{amount_paid}}</p>
        <p>Change: ₨{{balance_due}}</p>
    </div>
    
    <div class="footer">
        <p>Thank you for your purchase!</p>
        {{qr_code}}
    </div>
</body>
</html>';
    }

    /**
     * Get default quote template
     */
    private function getDefaultQuoteTemplate()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quote</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .quote-details { float: right; }
        .customer-info { margin-bottom: 20px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f5f5f5; }
        .totals { float: right; margin-top: 20px; }
        .footer { margin-top: 50px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{business_name}}</h1>
        <p>{{business_address}}</p>
        <p>Phone: {{business_phone}} | Email: {{business_email}}</p>
    </div>
    
    <div class="quote-details">
        <h2>QUOTE</h2>
        <p><strong>Quote #:</strong> {{invoice_number}}</p>
        <p><strong>Date:</strong> {{invoice_date}}</p>
        <p><strong>Valid Until:</strong> {{due_date}}</p>
    </div>
    
    <div class="customer-info">
        <h3>Quote For:</h3>
        <p><strong>{{customer_name}}</strong></p>
        <p>{{customer_address}}</p>
        <p>Phone: {{customer_phone}}</p>
        <p>Email: {{customer_email}}</p>
    </div>
    
    {{items_table}}
    
    <div class="totals">
        <p><strong>Subtotal: ₨{{subtotal}}</strong></p>
        <p>Tax: ₨{{tax_amount}}</p>
        <p>Discount: ₨{{discount_amount}}</p>
        <p><strong>Total: ₨{{total_amount}}</strong></p>
    </div>
    
    <div class="footer">
        <p>This quote is valid for 30 days from the date above.</p>
        <p>Thank you for considering our services!</p>
    </div>
</body>
</html>';
    }

    /**
     * Get default delivery note template
     */
    private function getDefaultDeliveryNoteTemplate()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Delivery Note</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .delivery-details { float: right; }
        .customer-info { margin-bottom: 20px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f5f5f5; }
        .footer { margin-top: 50px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{business_name}}</h1>
        <p>{{business_address}}</p>
        <p>Phone: {{business_phone}}</p>
    </div>
    
    <div class="delivery-details">
        <h2>DELIVERY NOTE</h2>
        <p><strong>Delivery #:</strong> {{invoice_number}}</p>
        <p><strong>Date:</strong> {{invoice_date}}</p>
    </div>
    
    <div class="customer-info">
        <h3>Deliver To:</h3>
        <p><strong>{{customer_name}}</strong></p>
        <p>{{customer_address}}</p>
        <p>Phone: {{customer_phone}}</p>
    </div>
    
    {{items_table}}
    
    <div class="footer">
        <p><strong>Delivery Instructions:</strong></p>
        <p>Please check all items before signing.</p>
        <p>Signature: _________________________ Date: ___________</p>
    </div>
</body>
</html>';
    }
}