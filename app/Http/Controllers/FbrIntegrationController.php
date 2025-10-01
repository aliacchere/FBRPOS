<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\FbrIntegrationService;
use App\Services\FbrScenarioService;
use App\Services\NotificationService;

class FbrIntegrationController extends Controller
{
    protected $fbrIntegrationService;
    protected $fbrScenarioService;
    protected $notificationService;

    public function __construct(
        FbrIntegrationService $fbrIntegrationService,
        FbrScenarioService $fbrScenarioService,
        NotificationService $notificationService
    ) {
        $this->fbrIntegrationService = $fbrIntegrationService;
        $this->fbrScenarioService = $fbrScenarioService;
        $this->notificationService = $notificationService;
    }

    /**
     * Display FBR integration dashboard
     */
    public function index()
    {
        $tenant = Auth::user()->tenant;
        
        $integrationSettings = $this->getIntegrationSettings($tenant->id);
        $statistics = $this->getIntegrationStatistics($tenant->id);
        $recentLogs = $this->getRecentLogs($tenant->id);
        $scenarios = $this->fbrScenarioService->getAvailableScenarios();
        
        return view('fbr-integration.dashboard', compact(
            'integrationSettings',
            'statistics',
            'recentLogs',
            'scenarios'
        ));
    }

    /**
     * Update FBR integration settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'bearer_token' => 'required|string|max:500',
            'environment' => 'required|in:sandbox,production',
            'auto_retry' => 'boolean',
            'retry_attempts' => 'nullable|integer|min:1|max:10',
            'retry_delay' => 'nullable|integer|min:1|max:60',
            'notification_email' => 'nullable|email',
            'webhook_url' => 'nullable|url',
            'default_scenario' => 'nullable|string|max:10'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            // Test the bearer token
            $testResult = $this->fbrIntegrationService->testConnection(
                $request->bearer_token,
                $request->environment
            );
            
            if (!$testResult['success']) {
                return redirect()->back()
                    ->with('error', 'FBR connection test failed: ' . $testResult['error']);
            }
            
            // Update settings
            $this->updateIntegrationSettings($tenant->id, $request->all());
            
            return redirect()->route('fbr-integration.index')
                ->with('success', 'FBR integration settings updated successfully');
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update settings: ' . $e->getMessage());
        }
    }

    /**
     * Test FBR connection
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'bearer_token' => 'required|string',
            'environment' => 'required|in:sandbox,production'
        ]);

        try {
            $result = $this->fbrIntegrationService->testConnection(
                $request->bearer_token,
                $request->environment
            );
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get FBR reference data
     */
    public function getReferenceData(Request $request)
    {
        $request->validate([
            'type' => 'required|in:provinces,doctypecode,itemdesccode,uom,hs_code'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $data = $this->fbrIntegrationService->getReferenceData(
                $request->type,
                $tenant->id
            );
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Refresh reference data cache
     */
    public function refreshReferenceData(Request $request)
    {
        $request->validate([
            'type' => 'required|in:provinces,doctypecode,itemdesccode,uom,hs_code,all'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            if ($request->type === 'all') {
                $types = ['provinces', 'doctypecode', 'itemdesccode', 'uom', 'hs_code'];
                foreach ($types as $type) {
                    $this->fbrIntegrationService->refreshReferenceData($type, $tenant->id);
                }
            } else {
                $this->fbrIntegrationService->refreshReferenceData($request->type, $tenant->id);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Reference data refreshed successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Process manual FBR request
     */
    public function processManualRequest(Request $request)
    {
        $request->validate([
            'scenario_code' => 'required|string|max:10',
            'sale_data' => 'required|array',
            'sale_data.invoice_number' => 'required|string',
            'sale_data.sale_date' => 'required|date',
            'sale_data.items' => 'required|array|min:1',
            'sale_data.items.*.name' => 'required|string',
            'sale_data.items.*.quantity' => 'required|numeric|min:0.01',
            'sale_data.items.*.unit_price' => 'required|numeric|min:0',
            'sale_data.items.*.total_price' => 'required|numeric|min:0'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $result = $this->fbrScenarioService->processScenario(
                $request->scenario_code,
                $request->sale_data,
                $tenant->id
            );
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get FBR logs
     */
    public function getLogs(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:success,failed,pending',
            'scenario_code' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $tenant = Auth::user()->tenant;
        
        $query = DB::table('fbr_logs')
            ->where('tenant_id', $tenant->id);
        
        if ($request->start_date) {
            $query->where('created_at', '>=', $request->start_date);
        }
        
        if ($request->end_date) {
            $query->where('created_at', '<=', $request->end_date);
        }
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->scenario_code) {
            $query->where('scenario_code', $request->scenario_code);
        }
        
        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);
        
        return response()->json($logs);
    }

    /**
     * Retry failed FBR requests
     */
    public function retryFailedRequests(Request $request)
    {
        $request->validate([
            'log_ids' => 'required|array',
            'log_ids.*' => 'integer|exists:fbr_logs,id'
        ]);

        $tenant = Auth::user()->tenant;
        $retried = 0;
        $errors = [];
        
        foreach ($request->log_ids as $logId) {
            try {
                $log = DB::table('fbr_logs')->find($logId);
                
                if (!$log || $log->tenant_id !== $tenant->id) {
                    continue;
                }
                
                $saleData = json_decode($log->request_payload, true);
                
                $result = $this->fbrScenarioService->processScenario(
                    $log->scenario_code,
                    $saleData,
                    $tenant->id
                );
                
                if ($result['success']) {
                    $retried++;
                } else {
                    $errors[] = "Log ID {$logId}: " . $result['error'];
                }
                
            } catch (\Exception $e) {
                $errors[] = "Log ID {$logId}: " . $e->getMessage();
            }
        }
        
        return response()->json([
            'success' => true,
            'retried' => $retried,
            'errors' => $errors
        ]);
    }

    /**
     * Get integration statistics
     */
    public function getStatistics(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:day,week,month,quarter,year'
        ]);

        $tenant = Auth::user()->tenant;
        $period = $request->period ?? 'month';
        
        $statistics = $this->getIntegrationStatistics($tenant->id, $period);
        
        return response()->json($statistics);
    }

    /**
     * Get scenario recommendations
     */
    public function getScenarioRecommendations(Request $request)
    {
        $request->validate([
            'sale_data' => 'required|array'
        ]);

        $recommendations = $this->fbrScenarioService->getScenarioRecommendations($request->sale_data);
        
        return response()->json([
            'success' => true,
            'recommendations' => $recommendations
        ]);
    }

    /**
     * Validate scenario requirements
     */
    public function validateScenarioRequirements(Request $request)
    {
        $request->validate([
            'scenario_code' => 'required|string|max:10',
            'sale_data' => 'required|array'
        ]);

        $validation = $this->fbrScenarioService->validateScenarioRequirements(
            $request->scenario_code,
            $request->sale_data
        );
        
        return response()->json($validation);
    }

    /**
     * Get integration settings
     */
    private function getIntegrationSettings(int $tenantId)
    {
        return DB::table('fbr_integration_settings')
            ->where('tenant_id', $tenantId)
            ->first() ?? (object) [
                'bearer_token' => '',
                'environment' => 'sandbox',
                'auto_retry' => true,
                'retry_attempts' => 3,
                'retry_delay' => 5,
                'notification_email' => '',
                'webhook_url' => '',
                'default_scenario' => 'SN001'
            ];
    }

    /**
     * Update integration settings
     */
    private function updateIntegrationSettings(int $tenantId, array $settings)
    {
        DB::table('fbr_integration_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            array_merge($settings, [
                'tenant_id' => $tenantId,
                'updated_at' => now()
            ])
        );
    }

    /**
     * Get integration statistics
     */
    private function getIntegrationStatistics(int $tenantId, string $period = 'month')
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $stats = DB::table('fbr_logs')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful_requests,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_requests,
                AVG(response_time) as avg_response_time,
                MAX(response_time) as max_response_time,
                MIN(response_time) as min_response_time
            ')
            ->first();
        
        $successRate = $stats->total_requests > 0 
            ? round(($stats->successful_requests / $stats->total_requests) * 100, 2)
            : 0;
        
        return [
            'total_requests' => $stats->total_requests ?? 0,
            'successful_requests' => $stats->successful_requests ?? 0,
            'failed_requests' => $stats->failed_requests ?? 0,
            'success_rate' => $successRate,
            'avg_response_time' => round($stats->avg_response_time ?? 0, 2),
            'max_response_time' => $stats->max_response_time ?? 0,
            'min_response_time' => $stats->min_response_time ?? 0
        ];
    }

    /**
     * Get recent logs
     */
    private function getRecentLogs(int $tenantId, int $limit = 10)
    {
        return DB::table('fbr_logs')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
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