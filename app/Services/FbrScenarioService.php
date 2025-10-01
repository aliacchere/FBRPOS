<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FbrScenarioService
{
    protected $fbrIntegrationService;
    protected $notificationService;

    public function __construct(FbrIntegrationService $fbrIntegrationService, NotificationService $notificationService)
    {
        $this->fbrIntegrationService = $fbrIntegrationService;
        $this->notificationService = $notificationService;
    }

    /**
     * Process FBR scenario based on product type
     */
    public function processScenario(string $scenarioCode, array $saleData, int $tenantId)
    {
        $scenario = $this->getScenarioConfig($scenarioCode);
        
        if (!$scenario) {
            throw new \Exception("Invalid scenario code: {$scenarioCode}");
        }

        $payload = $this->buildScenarioPayload($scenario, $saleData, $tenantId);
        
        try {
            // Validate with FBR
            $validationResult = $this->fbrIntegrationService->validateInvoiceData($payload, $tenantId);
            
            if ($validationResult['success']) {
                // Post to FBR
                $postResult = $this->fbrIntegrationService->postInvoiceData($payload, $tenantId);
                
                if ($postResult['success']) {
                    return [
                        'success' => true,
                        'fbr_invoice_number' => $postResult['fbr_invoice_number'],
                        'fbr_invoice_date' => $postResult['fbr_invoice_date'],
                        'scenario_code' => $scenarioCode,
                        'payload' => $payload
                    ];
                } else {
                    throw new \Exception($postResult['error']);
                }
            } else {
                throw new \Exception($validationResult['error']);
            }
            
        } catch (\Exception $e) {
            // Queue for retry if FBR is down
            $this->queueForRetry($scenarioCode, $payload, $tenantId, $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'queued' => true
            ];
        }
    }

    /**
     * Get scenario configuration
     */
    private function getScenarioConfig(string $scenarioCode)
    {
        $scenarios = [
            'SN001' => [
                'name' => 'Standard Rate Sales',
                'description' => 'Regular sales with 18% tax',
                'tax_rate' => 0.18,
                'tax_category' => 'standard_rate',
                'required_fields' => ['buyer_name', 'buyer_ntn', 'items'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount']
            ],
            'SN002' => [
                'name' => 'Third Schedule (MRP)',
                'description' => 'Third schedule items with MRP',
                'tax_rate' => 0.18,
                'tax_category' => 'third_schedule',
                'required_fields' => ['buyer_name', 'buyer_ntn', 'items'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount'],
                'special_fields' => ['fixed_notified_value_or_retail_price']
            ],
            'SN003' => [
                'name' => 'Reduced Rate Sales',
                'description' => 'Sales with reduced tax rate',
                'tax_rate' => 0.10,
                'tax_category' => 'reduced_rate',
                'required_fields' => ['buyer_name', 'buyer_ntn', 'items'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount']
            ],
            'SN004' => [
                'name' => 'Exempt Sales',
                'description' => 'Tax exempt sales',
                'tax_rate' => 0.00,
                'tax_category' => 'exempt',
                'required_fields' => ['buyer_name', 'items'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount']
            ],
            'SN005' => [
                'name' => 'Steel Sector Sales',
                'description' => 'Steel sector specific sales',
                'tax_rate' => 0.18,
                'tax_category' => 'steel',
                'required_fields' => ['buyer_name', 'buyer_ntn', 'items'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount']
            ],
            'SN006' => [
                'name' => 'Export Sales',
                'description' => 'Export sales with zero rating',
                'tax_rate' => 0.00,
                'tax_category' => 'export',
                'required_fields' => ['buyer_name', 'buyer_ntn', 'items', 'export_document'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount']
            ],
            'SN007' => [
                'name' => 'Sales Return',
                'description' => 'Sales return with credit note',
                'tax_rate' => 0.18,
                'tax_category' => 'return',
                'required_fields' => ['buyer_name', 'buyer_ntn', 'items', 'original_invoice_number'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount']
            ],
            'SN008' => [
                'name' => 'Sales to Unregistered',
                'description' => 'Sales to unregistered buyers',
                'tax_rate' => 0.18,
                'tax_category' => 'unregistered',
                'required_fields' => ['buyer_name', 'items'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount']
            ],
            'SN009' => [
                'name' => 'Sales to Government',
                'description' => 'Sales to government entities',
                'tax_rate' => 0.18,
                'tax_category' => 'government',
                'required_fields' => ['buyer_name', 'buyer_ntn', 'items'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount']
            ],
            'SN010' => [
                'name' => 'Sales to Diplomatic Missions',
                'description' => 'Sales to diplomatic missions',
                'tax_rate' => 0.00,
                'tax_category' => 'diplomatic',
                'required_fields' => ['buyer_name', 'items'],
                'optional_fields' => ['buyer_phone', 'buyer_address', 'discount_amount']
            ]
        ];

        return $scenarios[$scenarioCode] ?? null;
    }

    /**
     * Build scenario payload
     */
    private function buildScenarioPayload(array $scenario, array $saleData, int $tenantId)
    {
        $tenant = DB::table('tenants')->find($tenantId);
        $fiscalYear = $this->getCurrentFiscalYear($tenantId);
        
        $payload = [
            'invoice_number' => $saleData['invoice_number'],
            'invoice_date' => $saleData['sale_date'],
            'sale_type' => $scenario['tax_category'],
            'buyer_name' => $saleData['customer_name'] ?? 'Walk-in Customer',
            'buyer_ntn' => $saleData['customer_ntn'] ?? '',
            'buyer_phone' => $saleData['customer_phone'] ?? '',
            'buyer_address' => $saleData['customer_address'] ?? '',
            'items' => $this->buildItemsPayload($saleData['items'], $scenario),
            'discount_amount' => $saleData['discount_amount'] ?? 0,
            'total_amount' => $saleData['total_amount'],
            'tax_amount' => $saleData['tax_amount'],
            'net_amount' => $saleData['net_amount'],
            'fiscal_year' => $fiscalYear['year'] ?? date('Y'),
            'scenario_code' => $scenario['name']
        ];

        // Add special fields based on scenario
        if (isset($scenario['special_fields'])) {
            foreach ($scenario['special_fields'] as $field) {
                $payload[$field] = $this->getSpecialFieldValue($field, $saleData);
            }
        }

        return $payload;
    }

    /**
     * Build items payload
     */
    private function buildItemsPayload(array $items, array $scenario)
    {
        $itemsPayload = [];
        
        foreach ($items as $item) {
            $itemPayload = [
                'item_code' => $item['hs_code'],
                'item_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'tax_rate' => $scenario['tax_rate'],
                'tax_amount' => $item['tax_amount'],
                'unit_of_measure' => $item['unit_of_measure']
            ];

            // Add special fields for specific scenarios
            if ($scenario['tax_category'] === 'third_schedule') {
                $itemPayload['fixed_notified_value_or_retail_price'] = $item['mrp'] ?? $item['unit_price'];
            }

            if ($scenario['tax_category'] === 'export') {
                $itemPayload['export_document'] = $item['export_document'] ?? '';
            }

            $itemsPayload[] = $itemPayload;
        }

        return $itemsPayload;
    }

    /**
     * Get special field value
     */
    private function getSpecialFieldValue(string $field, array $saleData)
    {
        switch ($field) {
            case 'fixed_notified_value_or_retail_price':
                return $saleData['mrp'] ?? $saleData['total_amount'];
            case 'export_document':
                return $saleData['export_document'] ?? '';
            case 'original_invoice_number':
                return $saleData['original_invoice_number'] ?? '';
            default:
                return '';
        }
    }

    /**
     * Queue for retry
     */
    private function queueForRetry(string $scenarioCode, array $payload, int $tenantId, string $error)
    {
        DB::table('fbr_retry_queue')->insert([
            'tenant_id' => $tenantId,
            'scenario_code' => $scenarioCode,
            'payload' => json_encode($payload),
            'error_message' => $error,
            'retry_count' => 0,
            'max_retries' => 5,
            'next_retry_at' => now()->addMinutes(5),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        Log::warning('FBR request queued for retry', [
            'tenant_id' => $tenantId,
            'scenario_code' => $scenarioCode,
            'error' => $error
        ]);
    }

    /**
     * Process retry queue
     */
    public function processRetryQueue()
    {
        $pendingItems = DB::table('fbr_retry_queue')
            ->where('status', 'pending')
            ->where('retry_count', '<', DB::raw('max_retries'))
            ->where('next_retry_at', '<=', now())
            ->get();

        foreach ($pendingItems as $item) {
            try {
                $payload = json_decode($item->payload, true);
                $scenario = $this->getScenarioConfig($item->scenario_code);
                
                if (!$scenario) {
                    $this->markAsFailed($item->id, 'Invalid scenario code');
                    continue;
                }

                // Retry the request
                $result = $this->processScenario($item->scenario_code, $payload, $item->tenant_id);
                
                if ($result['success']) {
                    $this->markAsCompleted($item->id, $result);
                } else {
                    $this->incrementRetryCount($item->id, $result['error']);
                }
                
            } catch (\Exception $e) {
                $this->incrementRetryCount($item->id, $e->getMessage());
            }
        }
    }

    /**
     * Mark item as completed
     */
    private function markAsCompleted(int $itemId, array $result)
    {
        DB::table('fbr_retry_queue')
            ->where('id', $itemId)
            ->update([
                'status' => 'completed',
                'result' => json_encode($result),
                'completed_at' => now(),
                'updated_at' => now()
            ]);
    }

    /**
     * Mark item as failed
     */
    private function markAsFailed(int $itemId, string $error)
    {
        DB::table('fbr_retry_queue')
            ->where('id', $itemId)
            ->update([
                'status' => 'failed',
                'error_message' => $error,
                'failed_at' => now(),
                'updated_at' => now()
            ]);
    }

    /**
     * Increment retry count
     */
    private function incrementRetryCount(int $itemId, string $error)
    {
        $item = DB::table('fbr_retry_queue')->find($itemId);
        
        $retryCount = $item->retry_count + 1;
        $nextRetryAt = now()->addMinutes(pow(2, $retryCount)); // Exponential backoff
        
        if ($retryCount >= $item->max_retries) {
            $this->markAsFailed($itemId, $error);
        } else {
            DB::table('fbr_retry_queue')
                ->where('id', $itemId)
                ->update([
                    'retry_count' => $retryCount,
                    'next_retry_at' => $nextRetryAt,
                    'error_message' => $error,
                    'updated_at' => now()
                ]);
        }
    }

    /**
     * Get current fiscal year
     */
    private function getCurrentFiscalYear(int $tenantId)
    {
        return DB::table('fiscal_years')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get scenario statistics
     */
    public function getScenarioStatistics(int $tenantId, string $period = 'month')
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $stats = DB::table('fbr_logs')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                scenario_code,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful_requests,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_requests,
                AVG(response_time) as avg_response_time
            ')
            ->groupBy('scenario_code')
            ->get();

        return $stats;
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
     * Get available scenarios
     */
    public function getAvailableScenarios()
    {
        return [
            'SN001' => 'Standard Rate Sales',
            'SN002' => 'Third Schedule (MRP)',
            'SN003' => 'Reduced Rate Sales',
            'SN004' => 'Exempt Sales',
            'SN005' => 'Steel Sector Sales',
            'SN006' => 'Export Sales',
            'SN007' => 'Sales Return',
            'SN008' => 'Sales to Unregistered',
            'SN009' => 'Sales to Government',
            'SN010' => 'Sales to Diplomatic Missions'
        ];
    }

    /**
     * Validate scenario requirements
     */
    public function validateScenarioRequirements(string $scenarioCode, array $saleData)
    {
        $scenario = $this->getScenarioConfig($scenarioCode);
        
        if (!$scenario) {
            return ['valid' => false, 'errors' => ['Invalid scenario code']];
        }

        $errors = [];
        
        foreach ($scenario['required_fields'] as $field) {
            if (!isset($saleData[$field]) || empty($saleData[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get scenario recommendations
     */
    public function getScenarioRecommendations(array $saleData)
    {
        $recommendations = [];
        
        // Check if buyer has NTN
        if (isset($saleData['customer_ntn']) && !empty($saleData['customer_ntn'])) {
            $recommendations[] = 'SN001'; // Standard Rate Sales
        } else {
            $recommendations[] = 'SN008'; // Sales to Unregistered
        }
        
        // Check if items are third schedule
        if (isset($saleData['items'])) {
            $hasThirdSchedule = false;
            foreach ($saleData['items'] as $item) {
                if (isset($item['tax_category']) && $item['tax_category'] === 'third_schedule') {
                    $hasThirdSchedule = true;
                    break;
                }
            }
            
            if ($hasThirdSchedule) {
                $recommendations[] = 'SN002'; // Third Schedule (MRP)
            }
        }
        
        // Check if items are exempt
        if (isset($saleData['items'])) {
            $allExempt = true;
            foreach ($saleData['items'] as $item) {
                if (isset($item['tax_category']) && $item['tax_category'] !== 'exempt') {
                    $allExempt = false;
                    break;
                }
            }
            
            if ($allExempt) {
                $recommendations[] = 'SN004'; // Exempt Sales
            }
        }
        
        return $recommendations;
    }
}