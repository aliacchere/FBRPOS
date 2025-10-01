<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\FbrConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FbrIntegrationService
{
    protected $config;
    protected $baseUrl;
    protected $validateEndpoint;
    protected $postEndpoint;

    public function __construct()
    {
        $this->baseUrl = config('fbr.base_url');
        $this->validateEndpoint = config('fbr.validate_endpoint');
        $this->postEndpoint = config('fbr.post_endpoint');
    }

    /**
     * Process a sale with FBR integration
     */
    public function processSale(Sale $sale)
    {
        $tenant = $sale->tenant;
        $config = $tenant->fbrConfig;

        if (!$config || !$config->is_active) {
            return [
                'success' => false,
                'error' => 'FBR not configured for this tenant'
            ];
        }

        try {
            // Build FBR invoice data
            $invoiceData = $this->buildFbrInvoiceData($sale);

            // First validate the invoice
            $validationResult = $this->validateInvoice($invoiceData, $config);

            if (!$validationResult['success']) {
                return $validationResult;
            }

            // If validation successful, submit the invoice
            $submissionResult = $this->submitInvoice($invoiceData, $config);

            if ($submissionResult['success']) {
                // Update last sync time
                $config->update(['last_sync' => now()]);

                return [
                    'success' => true,
                    'fbr_invoice_number' => $submissionResult['fbr_invoice_number'],
                    'fbr_response' => $submissionResult['fbr_response']
                ];
            } else {
                // Queue for retry
                $this->queueForRetry($sale, $invoiceData, $submissionResult['error']);

                return [
                    'success' => false,
                    'error' => $submissionResult['error'],
                    'queued' => true
                ];
            }

        } catch (\Exception $e) {
            Log::error("FBR Integration Error: " . $e->getMessage());
            
            // Queue for retry on exception
            $this->queueForRetry($sale, $invoiceData ?? [], $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'FBR integration error: ' . $e->getMessage(),
                'queued' => true
            ];
        }
    }

    /**
     * Validate invoice with FBR
     */
    public function validateInvoice(array $invoiceData, FbrConfig $config)
    {
        $url = $this->baseUrl . $this->validateEndpoint;
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $config->bearer_token
        ])->timeout(30)->post($url, $invoiceData);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => 'FBR API connection failed: ' . $response->status()
            ];
        }

        $fbrResponse = $response->json();
        
        // Check if validation was successful
        if (isset($fbrResponse['validationResponse']['statusCode']) && 
            $fbrResponse['validationResponse']['statusCode'] === '00') {
            return [
                'success' => true,
                'fbr_response' => $fbrResponse
            ];
        } else {
            $errorCode = $fbrResponse['validationResponse']['errorCode'] ?? 'UNKNOWN';
            $errorMessage = $fbrResponse['validationResponse']['error'] ?? 'Unknown FBR error';
            
            return [
                'success' => false,
                'error' => $this->translateFbrError($errorCode),
                'fbr_error' => $errorCode,
                'fbr_message' => $errorMessage
            ];
        }
    }

    /**
     * Submit invoice to FBR
     */
    public function submitInvoice(array $invoiceData, FbrConfig $config)
    {
        $url = $this->baseUrl . $this->postEndpoint;
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $config->bearer_token
        ])->timeout(30)->post($url, $invoiceData);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => 'FBR API connection failed: ' . $response->status()
            ];
        }

        $fbrResponse = $response->json();
        
        // Check if submission was successful
        if (isset($fbrResponse['validationResponse']['statusCode']) && 
            $fbrResponse['validationResponse']['statusCode'] === '00' &&
            isset($fbrResponse['invoiceNumber'])) {
            
            return [
                'success' => true,
                'fbr_invoice_number' => $fbrResponse['invoiceNumber'],
                'fbr_response' => $fbrResponse
            ];
        } else {
            $errorCode = $fbrResponse['validationResponse']['errorCode'] ?? 'UNKNOWN';
            $errorMessage = $fbrResponse['validationResponse']['error'] ?? 'Unknown FBR error';
            
            return [
                'success' => false,
                'error' => $this->translateFbrError($errorCode),
                'fbr_error' => $errorCode,
                'fbr_message' => $errorMessage
            ];
        }
    }

    /**
     * Build FBR invoice data from sale
     */
    private function buildFbrInvoiceData(Sale $sale)
    {
        $tenant = $sale->tenant;
        
        $invoiceData = [
            'invoiceType' => 'Sale Invoice',
            'invoiceDate' => $sale->sale_date,
            'sellerNTNCNIC' => $tenant->ntn,
            'sellerBusinessName' => $tenant->business_name,
            'sellerProvince' => $tenant->province,
            'sellerAddress' => $tenant->address,
            'buyerNTNCNIC' => '0000000000000', // Default for walk-in customers
            'buyerBusinessName' => 'Walk-in Customer',
            'buyerProvince' => $tenant->province,
            'buyerAddress' => 'N/A',
            'buyerRegistrationType' => 'Unregistered',
            'invoiceRefNo' => $sale->reference_number ?? '',
            'scenarioId' => 'SN001',
            'items' => []
        ];

        // Process sale items
        foreach ($sale->items as $item) {
            $product = $item->product;
            $taxCategory = $this->getTaxCategory($product->tax_category);
            
            $fbrItem = [
                'hsCode' => $product->hs_code,
                'productDescription' => $product->name,
                'rate' => $taxCategory['rate'] . '%',
                'uoM' => $product->unit_of_measure,
                'quantity' => (float)$item->quantity,
                'totalValues' => 0.00,
                'valueSalesExcludingST' => 0.00,
                'fixedNotifiedValueOrRetailPrice' => 0.00,
                'salesTaxApplicable' => 0.00,
                'salesTaxWithheldAtSource' => 0.00,
                'extraTax' => 0.00,
                'furtherTax' => 0.00,
                'sroScheduleNo' => '',
                'fedPayable' => 0.00,
                'discount' => 0.00,
                'saleType' => $taxCategory['sale_type'],
                'sroItemSerialNo' => ''
            ];

            // Calculate values based on tax category
            if ($product->tax_category === 'third_schedule') {
                // Third Schedule: valueSalesExcludingST = 0, retail price in fixedNotifiedValueOrRetailPrice
                $fbrItem['valueSalesExcludingST'] = 0.00;
                $fbrItem['fixedNotifiedValueOrRetailPrice'] = (float)$item->unit_price;
                $fbrItem['salesTaxApplicable'] = (float)$item->unit_price * ($taxCategory['rate'] / 100);
            } else {
                // Standard calculation
                $fbrItem['valueSalesExcludingST'] = (float)$item->unit_price;
                $fbrItem['fixedNotifiedValueOrRetailPrice'] = 0.00;
                $fbrItem['salesTaxApplicable'] = (float)$item->tax_amount;
            }

            $fbrItem['totalValues'] = $fbrItem['valueSalesExcludingST'] + $fbrItem['salesTaxApplicable'];

            $invoiceData['items'][] = $fbrItem;
        }

        return $invoiceData;
    }

    /**
     * Get tax category configuration
     */
    private function getTaxCategory($category)
    {
        $categories = [
            'standard_rate' => [
                'rate' => 18,
                'sale_type' => 'Goods at standard rate (default)',
                'scenario_id' => 'SN001'
            ],
            'third_schedule' => [
                'rate' => 18,
                'sale_type' => 'Third Schedule (MRP)',
                'scenario_id' => 'SN008'
            ],
            'reduced_rate' => [
                'rate' => 5,
                'sale_type' => 'Goods at reduced rate',
                'scenario_id' => 'SN002'
            ],
            'exempt' => [
                'rate' => 0,
                'sale_type' => 'Exempt',
                'scenario_id' => 'SN006'
            ],
            'steel' => [
                'rate' => 18,
                'sale_type' => 'Steel',
                'scenario_id' => 'SN010'
            ]
        ];

        return $categories[$category] ?? $categories['standard_rate'];
    }

    /**
     * Translate FBR error codes to user-friendly messages
     */
    private function translateFbrError($errorCode)
    {
        $errorMessages = [
            '0001' => 'FBR Error: Your business is not registered for sales tax. Please check your NTN in the settings.',
            '0002' => 'FBR Error: The customer\'s NTN or CNIC is invalid. Please use a valid 13-digit CNIC or 7/9-digit NTN.',
            '0021' => 'FBR Error: The \'Value of Sales\' for an item is missing. Please ensure the product has a valid price.',
            '0052' => 'FBR Error: The HS Code for a product is incorrect. Please update it in the product settings.',
            '0053' => 'FBR Error: Invalid invoice date format. Please use YYYY-MM-DD format.',
            '0054' => 'FBR Error: Invalid quantity value. Please enter a valid numeric quantity.',
            '0055' => 'FBR Error: Invalid tax rate. Please check the tax rate configuration.',
            '0056' => 'FBR Error: Missing required field. Please check all required fields are filled.',
            '0057' => 'FBR Error: Invalid province code. Please select a valid province.',
            '0058' => 'FBR Error: Invalid unit of measure. Please select a valid UOM.',
            '0059' => 'FBR Error: Invalid scenario ID. Please check the sale type configuration.',
            '0060' => 'FBR Error: Duplicate invoice reference number. Please use a unique reference number.'
        ];

        return $errorMessages[$errorCode] ?? 'FBR Error: ' . $errorCode;
    }

    /**
     * Queue invoice for retry
     */
    private function queueForRetry(Sale $sale, array $invoiceData, string $errorMessage)
    {
        $sale->fbrQueue()->create([
            'tenant_id' => $sale->tenant_id,
            'invoice_data' => json_encode($invoiceData),
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 5,
            'error_message' => $errorMessage
        ]);

        $sale->update([
            'fbr_status' => 'pending',
            'fbr_error' => $errorMessage
        ]);
    }

    /**
     * Process queued invoices (for background worker)
     */
    public function processQueue()
    {
        $queuedItems = \App\Models\FbrQueue::where('status', 'pending')
            ->where('retry_count', '<', 'max_retries')
            ->with(['sale.tenant.fbrConfig'])
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get();

        foreach ($queuedItems as $item) {
            $item->update(['status' => 'processing']);

            try {
                $config = $item->sale->tenant->fbrConfig;
                $invoiceData = json_decode($item->invoice_data, true);
                
                $result = $this->submitInvoice($invoiceData, $config);

                if ($result['success']) {
                    // Update sale with FBR invoice number
                    $item->sale->update([
                        'fbr_invoice_number' => $result['fbr_invoice_number'],
                        'fbr_status' => 'synced',
                        'fbr_error' => null
                    ]);

                    // Mark queue item as completed
                    $item->update(['status' => 'completed']);
                } else {
                    // Increment retry count
                    $newRetryCount = $item->retry_count + 1;
                    $status = $newRetryCount >= $item->max_retries ? 'failed' : 'pending';
                    
                    $item->update([
                        'retry_count' => $newRetryCount,
                        'status' => $status,
                        'error_message' => $result['error']
                    ]);

                    if ($status === 'failed') {
                        $item->sale->update([
                            'fbr_status' => 'failed',
                            'fbr_error' => $result['error']
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error("FBR Queue Processing Error: " . $e->getMessage());
                
                $item->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Get FBR reference data
     */
    public function getReferenceData($type)
    {
        $endpoints = [
            'provinces' => '/provinces',
            'document_types' => '/doctypecode',
            'hs_codes' => '/itemdesccode',
            'uom' => '/uom',
            'transaction_types' => '/transtypecode'
        ];

        if (!isset($endpoints[$type])) {
            return ['success' => false, 'error' => 'Invalid reference data type'];
        }

        $url = config('fbr.reference_base_url') . $endpoints[$type];
        
        $response = Http::timeout(30)->get($url);

        if (!$response->successful()) {
            return ['success' => false, 'error' => 'Failed to fetch reference data'];
        }

        return [
            'success' => true,
            'data' => $response->json()
        ];
    }
}