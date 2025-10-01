<?php
/**
 * DPS POS FBR Integrated - FBR API Integration Engine
 * Core intelligent module for handling all FBR API interactions
 */

class FBRIntegrationEngine {
    private $tenant_id;
    private $config;
    private $pdo;

    public function __construct($tenant_id) {
        $this->tenant_id = $tenant_id;
        $this->pdo = $GLOBALS['pdo'];
        $this->config = $this->getFBRConfig();
    }

    /**
     * Get FBR configuration for the tenant
     */
    private function getFBRConfig() {
        return db_fetch("SELECT * FROM fbr_config WHERE tenant_id = ? AND is_active = 1", [$this->tenant_id]);
    }

    /**
     * Check if FBR is properly configured
     */
    public function isConfigured() {
        return $this->config && !empty($this->config['bearer_token']);
    }

    /**
     * Check if in sandbox mode
     */
    public function isSandboxMode() {
        return $this->config ? (bool)$this->config['sandbox_mode'] : true;
    }

    /**
     * Get the appropriate API base URL
     */
    private function getBaseURL() {
        return $this->isSandboxMode() ? FBR_SANDBOX_BASE_URL : FBR_PRODUCTION_BASE_URL;
    }

    /**
     * Make API call to FBR
     */
    private function makeAPICall($endpoint, $data) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'FBR not configured for this tenant'
            ];
        }

        $url = $this->getBaseURL() . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['bearer_token']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'CURL Error: ' . $error,
                'http_code' => 0
            ];
        }

        $decoded_response = json_decode($response, true);

        return [
            'success' => $http_code == 200,
            'http_code' => $http_code,
            'data' => $decoded_response,
            'raw_response' => $response
        ];
    }

    /**
     * Validate invoice data with FBR
     */
    public function validateInvoice($invoice_data) {
        $endpoint = $this->isSandboxMode() ? FBR_VALIDATE_ENDPOINT_SB : FBR_VALIDATE_ENDPOINT_PROD;
        
        $result = $this->makeAPICall($endpoint, $invoice_data);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'],
                'fbr_error' => null
            ];
        }

        $fbr_response = $result['data'];
        
        // Check if validation was successful
        if (isset($fbr_response['validationResponse']['statusCode']) && 
            $fbr_response['validationResponse']['statusCode'] === '00') {
            return [
                'success' => true,
                'fbr_response' => $fbr_response
            ];
        } else {
            $error_code = $fbr_response['validationResponse']['errorCode'] ?? 'UNKNOWN';
            $error_message = $fbr_response['validationResponse']['error'] ?? 'Unknown FBR error';
            
            return [
                'success' => false,
                'error' => $this->translateFBRError($error_code),
                'fbr_error' => $error_code,
                'fbr_message' => $error_message
            ];
        }
    }

    /**
     * Submit invoice to FBR
     */
    public function submitInvoice($invoice_data) {
        $endpoint = $this->isSandboxMode() ? FBR_POST_ENDPOINT_SB : FBR_POST_ENDPOINT_PROD;
        
        $result = $this->makeAPICall($endpoint, $invoice_data);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'],
                'fbr_invoice_number' => null
            ];
        }

        $fbr_response = $result['data'];
        
        // Check if submission was successful
        if (isset($fbr_response['validationResponse']['statusCode']) && 
            $fbr_response['validationResponse']['statusCode'] === '00' &&
            isset($fbr_response['invoiceNumber'])) {
            
            return [
                'success' => true,
                'fbr_invoice_number' => $fbr_response['invoiceNumber'],
                'fbr_response' => $fbr_response
            ];
        } else {
            $error_code = $fbr_response['validationResponse']['errorCode'] ?? 'UNKNOWN';
            $error_message = $fbr_response['validationResponse']['error'] ?? 'Unknown FBR error';
            
            return [
                'success' => false,
                'error' => $this->translateFBRError($error_code),
                'fbr_error' => $error_code,
                'fbr_message' => $error_message
            ];
        }
    }

    /**
     * Process sale with FBR integration
     */
    public function processSale($sale_data) {
        try {
            // Build FBR invoice data
            $invoice_data = $this->buildFBRInvoiceData($sale_data);
            
            if (!$invoice_data) {
                return [
                    'success' => false,
                    'error' => 'Failed to build FBR invoice data'
                ];
            }

            // First, validate the invoice
            $validation_result = $this->validateInvoice($invoice_data);
            
            if (!$validation_result['success']) {
                return [
                    'success' => false,
                    'error' => $validation_result['error'],
                    'fbr_error' => $validation_result['fbr_error'] ?? null
                ];
            }

            // If validation successful, submit the invoice
            $submission_result = $this->submitInvoice($invoice_data);
            
            if ($submission_result['success']) {
                // Update last sync time
                $this->updateLastSync();
                
                return [
                    'success' => true,
                    'fbr_invoice_number' => $submission_result['fbr_invoice_number'],
                    'fbr_response' => $submission_result['fbr_response']
                ];
            } else {
                // If submission fails, queue for retry
                $this->queueForRetry($sale_data['sale_id'], $invoice_data, $submission_result['error']);
                
                return [
                    'success' => false,
                    'error' => $submission_result['error'],
                    'fbr_error' => $submission_result['fbr_error'] ?? null,
                    'queued' => true
                ];
            }

        } catch (Exception $e) {
            error_log("FBR Integration Error: " . $e->getMessage());
            
            // Queue for retry on exception
            $this->queueForRetry($sale_data['sale_id'], $invoice_data ?? [], $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'FBR integration error: ' . $e->getMessage(),
                'queued' => true
            ];
        }
    }

    /**
     * Build FBR invoice data from sale data
     */
    private function buildFBRInvoiceData($sale_data) {
        // Get tenant information
        $tenant = db_fetch("SELECT * FROM tenants WHERE id = ?", [$this->tenant_id]);
        if (!$tenant) {
            return false;
        }

        // Get sale items
        $sale_items = db_fetch_all("
            SELECT si.*, p.name, p.tax_category, p.hs_code, p.unit_of_measure
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
        ", [$sale_data['sale_id']]);

        if (empty($sale_items)) {
            return false;
        }

        // Build invoice data
        $invoice_data = [
            'invoiceType' => 'Sale Invoice',
            'invoiceDate' => date('Y-m-d', strtotime($sale_data['created_at'])),
            'sellerNTNCNIC' => $tenant['ntn'],
            'sellerBusinessName' => $tenant['business_name'],
            'sellerProvince' => $tenant['province'],
            'sellerAddress' => $tenant['address'],
            'buyerNTNCNIC' => $sale_data['customer_ntn'] ?? '0000000000000',
            'buyerBusinessName' => $sale_data['customer_name'] ?? 'Walk-in Customer',
            'buyerProvince' => $sale_data['customer_province'] ?? $tenant['province'],
            'buyerAddress' => $sale_data['customer_address'] ?? 'N/A',
            'buyerRegistrationType' => 'Unregistered',
            'invoiceRefNo' => $sale_data['reference_number'] ?? '',
            'scenarioId' => 'SN001',
            'items' => []
        ];

        // Process items
        foreach ($sale_items as $item) {
            $tax_category = $this->getTaxCategory($item['tax_category']);
            
            $fbr_item = [
                'hsCode' => $item['hs_code'],
                'productDescription' => $item['name'],
                'rate' => $tax_category['rate'] . '%',
                'uoM' => $item['unit_of_measure'],
                'quantity' => (float)$item['quantity'],
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
                'saleType' => $tax_category['sale_type'],
                'sroItemSerialNo' => ''
            ];

            // Calculate values based on tax category
            if ($item['tax_category'] === 'third_schedule') {
                // Third Schedule: valueSalesExcludingST = 0, retail price in fixedNotifiedValueOrRetailPrice
                $fbr_item['valueSalesExcludingST'] = 0.00;
                $fbr_item['fixedNotifiedValueOrRetailPrice'] = (float)$item['unit_price'];
                $fbr_item['salesTaxApplicable'] = (float)$item['unit_price'] * ($tax_category['rate'] / 100);
            } else {
                // Standard calculation
                $fbr_item['valueSalesExcludingST'] = (float)$item['unit_price'];
                $fbr_item['fixedNotifiedValueOrRetailPrice'] = 0.00;
                $fbr_item['salesTaxApplicable'] = (float)$item['tax_amount'];
            }

            $fbr_item['totalValues'] = $fbr_item['valueSalesExcludingST'] + $fbr_item['salesTaxApplicable'];

            $invoice_data['items'][] = $fbr_item;
        }

        return $invoice_data;
    }

    /**
     * Get tax category configuration
     */
    private function getTaxCategory($category) {
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
    private function translateFBRError($error_code) {
        $error_messages = [
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

        return $error_messages[$error_code] ?? 'FBR Error: ' . $error_code;
    }

    /**
     * Queue invoice for retry
     */
    private function queueForRetry($sale_id, $invoice_data, $error_message) {
        db_insert('fbr_queue', [
            'tenant_id' => $this->tenant_id,
            'sale_id' => $sale_id,
            'invoice_data' => json_encode($invoice_data),
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 5,
            'error_message' => $error_message,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Update sale status
        db_update('sales', 
            ['fbr_status' => 'pending', 'fbr_error' => $error_message],
            'id = ?',
            [$sale_id]
        );
    }

    /**
     * Update last sync time
     */
    private function updateLastSync() {
        db_update('fbr_config',
            ['last_sync' => date('Y-m-d H:i:s')],
            'tenant_id = ?',
            [$this->tenant_id]
        );
    }

    /**
     * Process queued invoices (for background worker)
     */
    public static function processQueue() {
        $queued_items = db_fetch_all("
            SELECT fq.*, s.* 
            FROM fbr_queue fq
            JOIN sales s ON fq.sale_id = s.id
            WHERE fq.status = 'pending' 
            AND fq.retry_count < fq.max_retries
            ORDER BY fq.created_at ASC
            LIMIT 10
        ");

        foreach ($queued_items as $item) {
            $engine = new self($item['tenant_id']);
            
            // Update status to processing
            db_update('fbr_queue',
                ['status' => 'processing', 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$item['id']]
            );

            try {
                $invoice_data = json_decode($item['invoice_data'], true);
                $result = $engine->submitInvoice($invoice_data);

                if ($result['success']) {
                    // Update sale with FBR invoice number
                    db_update('sales',
                        [
                            'fbr_invoice_number' => $result['fbr_invoice_number'],
                            'fbr_status' => 'synced',
                            'fbr_error' => null
                        ],
                        'id = ?',
                        [$item['sale_id']]
                    );

                    // Mark queue item as completed
                    db_update('fbr_queue',
                        ['status' => 'completed', 'updated_at' => date('Y-m-d H:i:s')],
                        'id = ?',
                        [$item['id']]
                    );
                } else {
                    // Increment retry count
                    $new_retry_count = $item['retry_count'] + 1;
                    $status = $new_retry_count >= $item['max_retries'] ? 'failed' : 'pending';
                    
                    db_update('fbr_queue',
                        [
                            'retry_count' => $new_retry_count,
                            'status' => $status,
                            'error_message' => $result['error'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ],
                        'id = ?',
                        [$item['id']]
                    );

                    if ($status === 'failed') {
                        db_update('sales',
                            ['fbr_status' => 'failed', 'fbr_error' => $result['error']],
                            'id = ?',
                            [$item['sale_id']]
                        );
                    }
                }
            } catch (Exception $e) {
                error_log("FBR Queue Processing Error: " . $e->getMessage());
                
                // Mark as failed
                db_update('fbr_queue',
                    [
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id = ?',
                    [$item['id']]
                );
            }
        }
    }

    /**
     * Get FBR reference data
     */
    public function getReferenceData($type) {
        $endpoints = [
            'provinces' => FBR_PROVINCES_ENDPOINT,
            'document_types' => FBR_DOC_TYPES_ENDPOINT,
            'hs_codes' => FBR_HS_CODES_ENDPOINT,
            'uom' => FBR_UOM_ENDPOINT,
            'transaction_types' => FBR_TRANS_TYPES_ENDPOINT
        ];

        if (!isset($endpoints[$type])) {
            return ['success' => false, 'error' => 'Invalid reference data type'];
        }

        $url = FBR_REFERENCE_BASE_URL . $endpoints[$type];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'CURL Error: ' . $error];
        }

        $decoded_response = json_decode($response, true);

        return [
            'success' => $http_code == 200,
            'data' => $decoded_response
        ];
    }
}
?>