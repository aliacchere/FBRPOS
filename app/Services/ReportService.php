<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Generate sales report
     */
    public function generateSalesReport($tenantId, array $filters)
    {
        $query = DB::table('sales')
            ->where('tenant_id', $tenantId);

        // Apply date filters
        if (isset($filters['start_date'])) {
            $query->where('sale_date', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->where('sale_date', '<=', $filters['end_date']);
        }

        // Apply fiscal year filter
        if (isset($filters['fiscal_year_id'])) {
            $fiscalYear = DB::table('fiscal_years')
                ->where('id', $filters['fiscal_year_id'])
                ->where('tenant_id', $tenantId)
                ->first();
            
            if ($fiscalYear) {
                $query->whereBetween('sale_date', [$fiscalYear->start_date, $fiscalYear->end_date]);
            }
        }

        $sales = $query->get();
        
        // Calculate summary statistics
        $summary = [
            'total_sales' => $sales->count(),
            'total_revenue' => $sales->sum('total_amount'),
            'total_tax' => $sales->sum('tax_amount'),
            'total_discount' => $sales->sum('discount_amount'),
            'average_sale' => $sales->avg('total_amount'),
            'highest_sale' => $sales->max('total_amount'),
            'lowest_sale' => $sales->min('total_amount')
        ];

        // Daily breakdown
        $dailyBreakdown = $sales->groupBy(function($sale) {
            return Carbon::parse($sale->sale_date)->format('Y-m-d');
        })->map(function($daySales) {
            return [
                'date' => $daySales->first()->sale_date,
                'count' => $daySales->count(),
                'revenue' => $daySales->sum('total_amount'),
                'tax' => $daySales->sum('tax_amount')
            ];
        })->values();

        // Product performance
        $productPerformance = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.tenant_id', $tenantId)
            ->when(isset($filters['start_date']), function($query) use ($filters) {
                $query->where('sales.sale_date', '>=', $filters['start_date']);
            })
            ->when(isset($filters['end_date']), function($query) use ($filters) {
                $query->where('sales.sale_date', '<=', $filters['end_date']);
            })
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                SUM(sale_items.quantity) as total_quantity,
                SUM(sale_items.total_price) as total_revenue,
                AVG(sale_items.unit_price) as average_price
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return [
            'summary' => $summary,
            'daily_breakdown' => $dailyBreakdown,
            'product_performance' => $productPerformance,
            'data' => $sales
        ];
    }

    /**
     * Generate inventory report
     */
    public function generateInventoryReport($tenantId, string $reportType, array $filters = [])
    {
        switch ($reportType) {
            case 'valuation':
                return $this->generateInventoryValuationReport($tenantId, $filters);
            case 'aging':
                return $this->generateInventoryAgingReport($tenantId, $filters);
            case 'turnover':
                return $this->generateInventoryTurnoverReport($tenantId, $filters);
            case 'low_stock':
                return $this->generateLowStockReport($tenantId, $filters);
            default:
                throw new \InvalidArgumentException('Invalid report type');
        }
    }

    /**
     * Generate inventory valuation report
     */
    private function generateInventoryValuationReport($tenantId, array $filters)
    {
        $products = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $valuationMethod = $filters['valuation_method'] ?? 'fifo';
        $totalValue = 0;
        $details = [];

        foreach ($products as $product) {
            $value = $this->calculateProductValue($product, $valuationMethod);
            $totalValue += $value;
            
            $details[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $product->stock_quantity,
                'unit_cost' => $product->cost_price,
                'total_value' => $value,
                'category' => $product->category_id
            ];
        }

        return [
            'report_type' => 'valuation',
            'valuation_method' => $valuationMethod,
            'total_value' => $totalValue,
            'product_count' => count($details),
            'data' => $details
        ];
    }

    /**
     * Generate inventory aging report
     */
    private function generateInventoryAgingReport($tenantId, array $filters)
    {
        $products = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->get();

        $agingReport = [];
        $categories = [
            'fresh' => 0,
            'recent' => 0,
            'aging' => 0,
            'old' => 0,
            'very_old' => 0
        ];

        foreach ($products as $product) {
            $lastMovement = DB::table('stock_movements')
                ->where('product_id', $product->id)
                ->where('movement_type', 'in')
                ->orderBy('created_at', 'desc')
                ->first();

            $daysInStock = $lastMovement 
                ? Carbon::parse($lastMovement->created_at)->diffInDays(now())
                : 999;

            $agingCategory = $this->getAgingCategory($daysInStock);
            $categories[$agingCategory]++;

            $agingReport[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $product->stock_quantity,
                'value' => $product->stock_quantity * $product->cost_price,
                'days_in_stock' => $daysInStock,
                'aging_category' => $agingCategory,
                'last_movement' => $lastMovement ? $lastMovement->created_at : null
            ];
        }

        // Sort by days in stock descending
        usort($agingReport, function($a, $b) {
            return $b['days_in_stock'] <=> $a['days_in_stock'];
        });

        return [
            'report_type' => 'aging',
            'categories' => $categories,
            'data' => $agingReport
        ];
    }

    /**
     * Generate inventory turnover report
     */
    private function generateInventoryTurnoverReport($tenantId, array $filters)
    {
        $period = $filters['period'] ?? 30;
        $startDate = Carbon::now()->subDays($period);

        $turnoverData = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.sale_date', '>=', $startDate)
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                SUM(sale_items.quantity) as total_sold,
                AVG(products.stock_quantity) as average_stock,
                SUM(sale_items.total_price) as total_revenue
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->get()
            ->map(function($item) use ($period) {
                $turnoverRate = $item->average_stock > 0 ? $item->total_sold / $item->average_stock : 0;
                $daysToSell = $turnoverRate > 0 ? $period / $turnoverRate : 0;

                return [
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'sku' => $item->sku,
                    'total_sold' => $item->total_sold,
                    'average_stock' => round($item->average_stock, 2),
                    'turnover_rate' => round($turnoverRate, 2),
                    'days_to_sell' => round($daysToSell, 1),
                    'total_revenue' => $item->total_revenue
                ];
            })
            ->sortByDesc('turnover_rate')
            ->values();

        return [
            'report_type' => 'turnover',
            'period' => $period,
            'data' => $turnoverData
        ];
    }

    /**
     * Generate low stock report
     */
    private function generateLowStockReport($tenantId, array $filters)
    {
        $lowStockProducts = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereRaw('stock_quantity <= min_stock_level')
            ->get();

        $urgencyLevels = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];

        $reportData = $lowStockProducts->map(function($product) use (&$urgencyLevels) {
            $stockRatio = $product->stock_quantity / $product->min_stock_level;
            
            if ($stockRatio <= 0.5) {
                $urgency = 'critical';
            } elseif ($stockRatio <= 0.8) {
                $urgency = 'high';
            } elseif ($stockRatio <= 1.0) {
                $urgency = 'medium';
            } else {
                $urgency = 'low';
            }
            
            $urgencyLevels[$urgency]++;

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'current_stock' => $product->stock_quantity,
                'min_stock_level' => $product->min_stock_level,
                'stock_ratio' => round($stockRatio, 2),
                'urgency' => $urgency,
                'cost_price' => $product->cost_price,
                'total_value' => $product->stock_quantity * $product->cost_price
            ];
        })->sortByDesc('urgency')->values();

        return [
            'report_type' => 'low_stock',
            'urgency_levels' => $urgencyLevels,
            'total_products' => count($reportData),
            'data' => $reportData
        ];
    }

    /**
     * Generate financial report
     */
    public function generateFinancialReport($tenantId, string $reportType, array $filters)
    {
        switch ($reportType) {
            case 'profit_loss':
                return $this->generateProfitLossReport($tenantId, $filters);
            case 'balance_sheet':
                return $this->generateBalanceSheetReport($tenantId, $filters);
            case 'cash_flow':
                return $this->generateCashFlowReport($tenantId, $filters);
            default:
                throw new \InvalidArgumentException('Invalid financial report type');
        }
    }

    /**
     * Generate profit and loss report
     */
    private function generateProfitLossReport($tenantId, array $filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        // Revenue
        $revenue = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->sum('total_amount');

        // Cost of Goods Sold
        $cogs = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->sum(DB::raw('sale_items.quantity * products.cost_price'));

        // Operating Expenses
        $operatingExpenses = DB::table('expenses')
            ->where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        // Tax
        $tax = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->sum('tax_amount');

        $grossProfit = $revenue - $cogs;
        $operatingProfit = $grossProfit - $operatingExpenses;
        $netProfit = $operatingProfit - $tax;

        return [
            'report_type' => 'profit_loss',
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'revenue' => $revenue,
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'operating_profit' => $operatingProfit,
            'tax' => $tax,
            'net_profit' => $netProfit,
            'gross_profit_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
            'net_profit_margin' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0
        ];
    }

    /**
     * Generate balance sheet report
     */
    private function generateBalanceSheetReport($tenantId, array $filters)
    {
        $asOfDate = $filters['end_date'];

        // Assets
        $inventoryValue = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->sum(DB::raw('stock_quantity * cost_price'));

        $accountsReceivable = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'pending')
            ->where('sale_date', '<=', $asOfDate)
            ->sum('total_amount');

        $cash = DB::table('cash_accounts')
            ->where('tenant_id', $tenantId)
            ->where('as_of_date', '<=', $asOfDate)
            ->sum('balance');

        // Liabilities
        $accountsPayable = DB::table('purchase_orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'paid')
            ->where('created_at', '<=', $asOfDate)
            ->sum('total_amount');

        $totalAssets = $inventoryValue + $accountsReceivable + $cash;
        $totalLiabilities = $accountsPayable;
        $equity = $totalAssets - $totalLiabilities;

        return [
            'report_type' => 'balance_sheet',
            'as_of_date' => $asOfDate,
            'assets' => [
                'inventory' => $inventoryValue,
                'accounts_receivable' => $accountsReceivable,
                'cash' => $cash,
                'total' => $totalAssets
            ],
            'liabilities' => [
                'accounts_payable' => $accountsPayable,
                'total' => $totalLiabilities
            ],
            'equity' => $equity
        ];
    }

    /**
     * Generate cash flow report
     */
    private function generateCashFlowReport($tenantId, array $filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        // Operating Activities
        $cashFromSales = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $cashToSuppliers = DB::table('purchase_orders')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('total_amount');

        $operatingCashFlow = $cashFromSales - $cashToSuppliers;

        // Investing Activities
        $investingCashFlow = 0; // Would include equipment purchases, etc.

        // Financing Activities
        $financingCashFlow = 0; // Would include loans, investments, etc.

        $netCashFlow = $operatingCashFlow + $investingCashFlow + $financingCashFlow;

        return [
            'report_type' => 'cash_flow',
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'operating_activities' => [
                'cash_from_sales' => $cashFromSales,
                'cash_to_suppliers' => $cashToSuppliers,
                'net_operating_cash_flow' => $operatingCashFlow
            ],
            'investing_activities' => [
                'net_investing_cash_flow' => $investingCashFlow
            ],
            'financing_activities' => [
                'net_financing_cash_flow' => $financingCashFlow
            ],
            'net_cash_flow' => $netCashFlow
        ];
    }

    /**
     * Generate employee performance report
     */
    public function generateEmployeePerformanceReport($tenantId, array $filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $employeeId = $filters['employee_id'] ?? null;

        $query = DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->join('employees', 'users.employee_id', '=', 'employees.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($employeeId) {
            $query->where('employees.id', $employeeId);
        }

        $performance = $query->selectRaw('
                employees.id as employee_id,
                employees.name as employee_name,
                employees.position,
                employees.department,
                COUNT(sales.id) as total_sales,
                SUM(sales.total_amount) as total_revenue,
                AVG(sales.total_amount) as average_sale,
                MIN(sales.total_amount) as lowest_sale,
                MAX(sales.total_amount) as highest_sale
            ')
            ->groupBy('employees.id', 'employees.name', 'employees.position', 'employees.department')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return [
            'report_type' => 'employee_performance',
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'data' => $performance
        ];
    }

    /**
     * Generate tax report
     */
    public function generateTaxReport($tenantId, int $year)
    {
        // Sales tax collected
        $salesTax = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereYear('sale_date', $year)
            ->sum('tax_amount');

        // Employee tax deductions
        $employeeTaxDeductions = DB::table('payrolls')
            ->where('tenant_id', $tenantId)
            ->where('pay_period', 'like', $year . '%')
            ->sum('tax_deduction');

        // Monthly breakdown
        $monthlyBreakdown = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthSalesTax = DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->whereYear('sale_date', $year)
                ->whereMonth('sale_date', $month)
                ->sum('tax_amount');

            $monthEmployeeTax = DB::table('payrolls')
                ->where('tenant_id', $tenantId)
                ->where('pay_period', $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT))
                ->sum('tax_deduction');

            $monthlyBreakdown[] = [
                'month' => Carbon::create($year, $month, 1)->format('F'),
                'sales_tax' => $monthSalesTax,
                'employee_tax' => $monthEmployeeTax,
                'total_tax' => $monthSalesTax + $monthEmployeeTax
            ];
        }

        return [
            'report_type' => 'tax',
            'year' => $year,
            'total_sales_tax' => $salesTax,
            'total_employee_tax' => $employeeTaxDeductions,
            'total_tax' => $salesTax + $employeeTaxDeductions,
            'monthly_breakdown' => $monthlyBreakdown
        ];
    }

    /**
     * Get dashboard analytics
     */
    public function getDashboardAnalytics($tenantId, string $period, $fiscalYearId = null)
    {
        $dateRange = $this->getDateRange($period, $fiscalYearId);
        
        // Sales analytics
        $sales = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->get();

        $salesAnalytics = [
            'total_sales' => $sales->count(),
            'total_revenue' => $sales->sum('total_amount'),
            'average_sale' => $sales->avg('total_amount'),
            'growth_rate' => $this->calculateGrowthRate($tenantId, 'sales', $dateRange)
        ];

        // Inventory analytics
        $inventoryStats = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->selectRaw('
                COUNT(*) as total_products,
                SUM(stock_quantity) as total_stock,
                SUM(stock_quantity * cost_price) as total_value,
                SUM(CASE WHEN stock_quantity <= min_stock_level THEN 1 ELSE 0 END) as low_stock_count
            ')
            ->first();

        // Employee analytics
        $employeeStats = DB::table('employees')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->selectRaw('COUNT(*) as total_employees')
            ->first();

        return [
            'period' => $period,
            'date_range' => $dateRange,
            'sales' => $salesAnalytics,
            'inventory' => (array) $inventoryStats,
            'employees' => (array) $employeeStats
        ];
    }

    /**
     * Get chart data
     */
    public function getChartData($tenantId, string $chartType, string $period, $fiscalYearId = null)
    {
        $dateRange = $this->getDateRange($period, $fiscalYearId);
        
        switch ($chartType) {
            case 'sales_trend':
                return $this->getSalesTrendData($tenantId, $dateRange, $period);
            case 'inventory_trend':
                return $this->getInventoryTrendData($tenantId, $dateRange, $period);
            case 'employee_performance':
                return $this->getEmployeePerformanceData($tenantId, $dateRange);
            case 'revenue_breakdown':
                return $this->getRevenueBreakdownData($tenantId, $dateRange);
            default:
                throw new \InvalidArgumentException('Invalid chart type');
        }
    }

    /**
     * Get sales trend data
     */
    private function getSalesTrendData($tenantId, array $dateRange, string $period)
    {
        $groupBy = $this->getGroupByClause($period);
        
        $data = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw("DATE_FORMAT(sale_date, '{$groupBy}') as period, COUNT(*) as sales_count, SUM(total_amount) as revenue")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'labels' => $data->pluck('period')->toArray(),
            'datasets' => [
                [
                    'label' => 'Sales Count',
                    'data' => $data->pluck('sales_count')->toArray(),
                    'type' => 'line'
                ],
                [
                    'label' => 'Revenue',
                    'data' => $data->pluck('revenue')->toArray(),
                    'type' => 'bar'
                ]
            ]
        ];
    }

    /**
     * Get inventory trend data
     */
    private function getInventoryTrendData($tenantId, array $dateRange, string $period)
    {
        $groupBy = $this->getGroupByClause($period);
        
        $data = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->where('stock_movements.tenant_id', $tenantId)
            ->whereBetween('stock_movements.created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw("
                DATE_FORMAT(stock_movements.created_at, '{$groupBy}') as period,
                SUM(CASE WHEN stock_movements.movement_type = 'in' THEN stock_movements.quantity ELSE 0 END) as stock_in,
                SUM(CASE WHEN stock_movements.movement_type = 'out' THEN stock_movements.quantity ELSE 0 END) as stock_out
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'labels' => $data->pluck('period')->toArray(),
            'datasets' => [
                [
                    'label' => 'Stock In',
                    'data' => $data->pluck('stock_in')->toArray(),
                    'type' => 'bar'
                ],
                [
                    'label' => 'Stock Out',
                    'data' => $data->pluck('stock_out')->toArray(),
                    'type' => 'bar'
                ]
            ]
        ];
    }

    /**
     * Get employee performance data
     */
    private function getEmployeePerformanceData($tenantId, array $dateRange)
    {
        $data = DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->join('employees', 'users.employee_id', '=', 'employees.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sales.sale_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                employees.name as employee_name,
                COUNT(sales.id) as sales_count,
                SUM(sales.total_amount) as revenue
            ')
            ->groupBy('employees.id', 'employees.name')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get();

        return [
            'labels' => $data->pluck('employee_name')->toArray(),
            'datasets' => [
                [
                    'label' => 'Sales Count',
                    'data' => $data->pluck('sales_count')->toArray(),
                    'type' => 'bar'
                ],
                [
                    'label' => 'Revenue',
                    'data' => $data->pluck('revenue')->toArray(),
                    'type' => 'line'
                ]
            ]
        ];
    }

    /**
     * Get revenue breakdown data
     */
    private function getRevenueBreakdownData($tenantId, array $dateRange)
    {
        $data = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sales.sale_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                products.category_id,
                SUM(sale_items.total_price) as revenue
            ')
            ->groupBy('products.category_id')
            ->get();

        return [
            'labels' => $data->pluck('category_id')->toArray(),
            'datasets' => [
                [
                    'label' => 'Revenue by Category',
                    'data' => $data->pluck('revenue')->toArray(),
                    'type' => 'doughnut'
                ]
            ]
        ];
    }

    /**
     * Get fiscal years
     */
    public function getFiscalYears($tenantId)
    {
        return DB::table('fiscal_years')
            ->where('tenant_id', $tenantId)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Get current fiscal year
     */
    public function getCurrentFiscalYear($tenantId)
    {
        return DB::table('fiscal_years')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Helper methods
     */
    private function calculateProductValue($product, string $method)
    {
        // Simplified calculation - would need more complex logic for FIFO/LIFO
        return $product->stock_quantity * $product->cost_price;
    }

    private function getAgingCategory(int $daysInStock)
    {
        if ($daysInStock <= 30) return 'fresh';
        if ($daysInStock <= 60) return 'recent';
        if ($daysInStock <= 90) return 'aging';
        if ($daysInStock <= 180) return 'old';
        return 'very_old';
    }

    private function getDateRange(string $period, $fiscalYearId = null)
    {
        if ($fiscalYearId) {
            $fiscalYear = DB::table('fiscal_years')
                ->where('id', $fiscalYearId)
                ->first();
            
            if ($fiscalYear) {
                return [
                    'start' => $fiscalYear->start_date,
                    'end' => $fiscalYear->end_date
                ];
            }
        }

        switch ($period) {
            case 'today':
                return [
                    'start' => now()->startOfDay(),
                    'end' => now()->endOfDay()
                ];
            case 'week':
                return [
                    'start' => now()->startOfWeek(),
                    'end' => now()->endOfWeek()
                ];
            case 'month':
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth()
                ];
            case 'quarter':
                return [
                    'start' => now()->startOfQuarter(),
                    'end' => now()->endOfQuarter()
                ];
            case 'year':
                return [
                    'start' => now()->startOfYear(),
                    'end' => now()->endOfYear()
                ];
            default:
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth()
                ];
        }
    }

    private function calculateGrowthRate($tenantId, string $metric, array $dateRange)
    {
        // Get current period data
        $currentData = $this->getMetricData($tenantId, $metric, $dateRange);
        
        // Get previous period data
        $previousDateRange = $this->getPreviousPeriodRange($dateRange);
        $previousData = $this->getMetricData($tenantId, $metric, $previousDateRange);
        
        if ($previousData == 0) return 0;
        
        return round((($currentData - $previousData) / $previousData) * 100, 2);
    }

    private function getMetricData($tenantId, string $metric, array $dateRange)
    {
        switch ($metric) {
            case 'sales':
                return DB::table('sales')
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])
                    ->sum('total_amount');
            default:
                return 0;
        }
    }

    private function getPreviousPeriodRange(array $dateRange)
    {
        $start = Carbon::parse($dateRange['start']);
        $end = Carbon::parse($dateRange['end']);
        $duration = $start->diffInDays($end);
        
        return [
            'start' => $start->subDays($duration + 1)->toDateString(),
            'end' => $start->addDays($duration)->toDateString()
        ];
    }

    private function getGroupByClause(string $period)
    {
        switch ($period) {
            case 'today':
                return '%H:00';
            case 'week':
                return '%Y-%m-%d';
            case 'month':
                return '%Y-%m-%d';
            case 'quarter':
                return '%Y-%m';
            case 'year':
                return '%Y-%m';
            default:
                return '%Y-%m-%d';
        }
    }
}