<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ReportService;
use App\Services\ExportService;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $reportService;
    protected $exportService;

    public function __construct(ReportService $reportService, ExportService $exportService)
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    /**
     * Display reports dashboard
     */
    public function index()
    {
        $tenant = Auth::user()->tenant;
        $fiscalYears = $this->reportService->getFiscalYears($tenant->id);
        $currentFiscalYear = $this->reportService->getCurrentFiscalYear($tenant->id);
        
        return view('reports.dashboard', compact('fiscalYears', 'currentFiscalYear'));
    }

    /**
     * Sales report
     */
    public function salesReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'fiscal_year_id' => 'nullable|exists:fiscal_years,id',
            'format' => 'nullable|in:view,pdf,excel,csv'
        ]);

        $tenant = Auth::user()->tenant;
        $filters = $request->only(['start_date', 'end_date', 'fiscal_year_id']);
        
        $report = $this->reportService->generateSalesReport($tenant->id, $filters);
        
        if ($request->format === 'pdf') {
            return $this->exportService->exportToPdf('reports.sales-report-pdf', $report, 'sales-report.pdf');
        } elseif ($request->format === 'excel') {
            return $this->exportService->exportToExcel($report['data'], 'sales-report.xlsx');
        } elseif ($request->format === 'csv') {
            return $this->exportService->exportToCsv($report['data'], 'sales-report.csv');
        }
        
        return view('reports.sales-report', compact('report', 'filters'));
    }

    /**
     * Inventory report
     */
    public function inventoryReport(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:valuation,aging,turnover,low_stock',
            'format' => 'nullable|in:view,pdf,excel,csv'
        ]);

        $tenant = Auth::user()->tenant;
        $reportType = $request->report_type;
        
        $report = $this->reportService->generateInventoryReport($tenant->id, $reportType, $request->all());
        
        if ($request->format === 'pdf') {
            return $this->exportService->exportToPdf('reports.inventory-report-pdf', $report, 'inventory-report.pdf');
        } elseif ($request->format === 'excel') {
            return $this->exportService->exportToExcel($report['data'], 'inventory-report.xlsx');
        } elseif ($request->format === 'csv') {
            return $this->exportService->exportToCsv($report['data'], 'inventory-report.csv');
        }
        
        return view('reports.inventory-report', compact('report', 'reportType'));
    }

    /**
     * Financial report
     */
    public function financialReport(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:profit_loss,balance_sheet,cash_flow',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'fiscal_year_id' => 'nullable|exists:fiscal_years,id',
            'format' => 'nullable|in:view,pdf,excel,csv'
        ]);

        $tenant = Auth::user()->tenant;
        $filters = $request->only(['start_date', 'end_date', 'fiscal_year_id']);
        $reportType = $request->report_type;
        
        $report = $this->reportService->generateFinancialReport($tenant->id, $reportType, $filters);
        
        if ($request->format === 'pdf') {
            return $this->exportService->exportToPdf('reports.financial-report-pdf', $report, 'financial-report.pdf');
        } elseif ($request->format === 'excel') {
            return $this->exportService->exportToExcel($report['data'], 'financial-report.xlsx');
        } elseif ($request->format === 'csv') {
            return $this->exportService->exportToCsv($report['data'], 'financial-report.csv');
        }
        
        return view('reports.financial-report', compact('report', 'reportType', 'filters'));
    }

    /**
     * Employee performance report
     */
    public function employeePerformanceReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'employee_id' => 'nullable|exists:employees,id',
            'format' => 'nullable|in:view,pdf,excel,csv'
        ]);

        $tenant = Auth::user()->tenant;
        $filters = $request->only(['start_date', 'end_date', 'employee_id']);
        
        $report = $this->reportService->generateEmployeePerformanceReport($tenant->id, $filters);
        
        if ($request->format === 'pdf') {
            return $this->exportService->exportToPdf('reports.employee-performance-pdf', $report, 'employee-performance.pdf');
        } elseif ($request->format === 'excel') {
            return $this->exportService->exportToExcel($report['data'], 'employee-performance.xlsx');
        } elseif ($request->format === 'csv') {
            return $this->exportService->exportToCsv($report['data'], 'employee-performance.csv');
        }
        
        return view('reports.employee-performance', compact('report', 'filters'));
    }

    /**
     * Tax report
     */
    public function taxReport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . date('Y'),
            'format' => 'nullable|in:view,pdf,excel,csv'
        ]);

        $tenant = Auth::user()->tenant;
        $year = $request->year;
        
        $report = $this->reportService->generateTaxReport($tenant->id, $year);
        
        if ($request->format === 'pdf') {
            return $this->exportService->exportToPdf('reports.tax-report-pdf', $report, 'tax-report.pdf');
        } elseif ($request->format === 'excel') {
            return $this->exportService->exportToExcel($report['data'], 'tax-report.xlsx');
        } elseif ($request->format === 'csv') {
            return $this->exportService->exportToCsv($report['data'], 'tax-report.csv');
        }
        
        return view('reports.tax-report', compact('report', 'year'));
    }

    /**
     * Dashboard analytics
     */
    public function getDashboardAnalytics(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:today,week,month,quarter,year',
            'fiscal_year_id' => 'nullable|exists:fiscal_years,id'
        ]);

        $tenant = Auth::user()->tenant;
        $period = $request->period ?? 'month';
        $fiscalYearId = $request->fiscal_year_id;
        
        $analytics = $this->reportService->getDashboardAnalytics($tenant->id, $period, $fiscalYearId);
        
        return response()->json($analytics);
    }

    /**
     * Create fiscal year
     */
    public function createFiscalYear(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean'
        ]);

        $tenant = Auth::user()->tenant;
        
        // Check for overlapping fiscal years
        $overlapping = DB::table('fiscal_years')
            ->where('tenant_id', $tenant->id)
            ->where(function($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                      ->orWhere(function($q) use ($request) {
                          $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                      });
            })
            ->exists();

        if ($overlapping) {
            return redirect()->back()
                ->with('error', 'Fiscal year dates overlap with existing fiscal year');
        }

        $fiscalYearData = $request->all();
        $fiscalYearData['tenant_id'] = $tenant->id;
        
        DB::table('fiscal_years')->insert($fiscalYearData);

        return redirect()->route('reports.index')
            ->with('success', 'Fiscal year created successfully');
    }

    /**
     * Set active fiscal year
     */
    public function setActiveFiscalYear(Request $request)
    {
        $request->validate([
            'fiscal_year_id' => 'required|exists:fiscal_years,id'
        ]);

        $tenant = Auth::user()->tenant;
        
        DB::beginTransaction();
        
        try {
            // Deactivate all fiscal years for this tenant
            DB::table('fiscal_years')
                ->where('tenant_id', $tenant->id)
                ->update(['is_active' => false]);
            
            // Activate selected fiscal year
            DB::table('fiscal_years')
                ->where('id', $request->fiscal_year_id)
                ->where('tenant_id', $tenant->id)
                ->update(['is_active' => true]);
            
            DB::commit();
            
            return redirect()->route('reports.index')
                ->with('success', 'Active fiscal year updated successfully');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Failed to update fiscal year: ' . $e->getMessage());
        }
    }

    /**
     * Get chart data for dashboard
     */
    public function getChartData(Request $request)
    {
        $request->validate([
            'chart_type' => 'required|in:sales_trend,inventory_trend,employee_performance,revenue_breakdown',
            'period' => 'nullable|in:week,month,quarter,year',
            'fiscal_year_id' => 'nullable|exists:fiscal_years,id'
        ]);

        $tenant = Auth::user()->tenant;
        $chartType = $request->chart_type;
        $period = $request->period ?? 'month';
        $fiscalYearId = $request->fiscal_year_id;
        
        $data = $this->reportService->getChartData($tenant->id, $chartType, $period, $fiscalYearId);
        
        return response()->json($data);
    }

    /**
     * Export multiple reports
     */
    public function exportMultipleReports(Request $request)
    {
        $request->validate([
            'reports' => 'required|array|min:1',
            'reports.*.type' => 'required|string',
            'reports.*.filters' => 'nullable|array',
            'format' => 'required|in:pdf,excel,zip'
        ]);

        $tenant = Auth::user()->tenant;
        $reports = $request->reports;
        $format = $request->format;
        
        $exportedFiles = [];
        
        foreach ($reports as $reportConfig) {
            $report = $this->reportService->generateReport($tenant->id, $reportConfig['type'], $reportConfig['filters'] ?? []);
            
            if ($format === 'pdf') {
                $filename = $this->exportService->exportToPdf(
                    'reports.' . $reportConfig['type'] . '-pdf',
                    $report,
                    $reportConfig['type'] . '-report.pdf'
                );
                $exportedFiles[] = $filename;
            } elseif ($format === 'excel') {
                $filename = $this->exportService->exportToExcel(
                    $report['data'],
                    $reportConfig['type'] . '-report.xlsx'
                );
                $exportedFiles[] = $filename;
            }
        }
        
        if ($format === 'zip') {
            return $this->exportService->createZipArchive($exportedFiles, 'reports-export.zip');
        }
        
        return response()->json([
            'success' => true,
            'files' => $exportedFiles
        ]);
    }

    /**
     * Schedule report generation
     */
    public function scheduleReport(Request $request)
    {
        $request->validate([
            'report_type' => 'required|string',
            'filters' => 'nullable|array',
            'schedule' => 'required|in:daily,weekly,monthly',
            'email_recipients' => 'required|array',
            'email_recipients.*' => 'email'
        ]);

        $tenant = Auth::user()->tenant;
        
        // Create scheduled report entry
        $scheduledReport = DB::table('scheduled_reports')->insert([
            'tenant_id' => $tenant->id,
            'report_type' => $request->report_type,
            'filters' => json_encode($request->filters ?? []),
            'schedule' => $request->schedule,
            'email_recipients' => json_encode($request->email_recipients),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return redirect()->route('reports.index')
            ->with('success', 'Report scheduled successfully');
    }
}