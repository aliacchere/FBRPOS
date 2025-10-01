<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\TaxDeduction;
use App\Services\HrmService;
use App\Services\PayrollService;

class HrmController extends Controller
{
    protected $hrmService;
    protected $payrollService;

    public function __construct(HrmService $hrmService, PayrollService $payrollService)
    {
        $this->hrmService = $hrmService;
        $this->payrollService = $payrollService;
    }

    /**
     * Display HRM dashboard
     */
    public function index()
    {
        $tenant = Auth::user()->tenant;
        
        $stats = $this->hrmService->getHrmStats($tenant->id);
        $recentAttendance = $this->hrmService->getRecentAttendance($tenant->id);
        $upcomingBirthdays = $this->hrmService->getUpcomingBirthdays($tenant->id);
        
        return view('hrm.dashboard', compact('stats', 'recentAttendance', 'upcomingBirthdays'));
    }

    /**
     * Display employees list
     */
    public function employees()
    {
        $tenant = Auth::user()->tenant;
        $employees = Employee::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->paginate(20);
        
        return view('hrm.employees', compact('employees'));
    }

    /**
     * Store a new employee
     */
    public function storeEmployee(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|string|max:50|unique:employees,employee_id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'hire_date' => 'nullable|date',
            'date_of_birth' => 'nullable|date',
            'cnic' => 'nullable|string|max:15',
            'bank_account' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20'
        ]);

        $tenant = Auth::user()->tenant;
        
        $employeeData = $request->all();
        $employeeData['tenant_id'] = $tenant->id;
        
        Employee::create($employeeData);

        return redirect()->route('hrm.employees')
            ->with('success', 'Employee created successfully');
    }

    /**
     * Update an employee
     */
    public function updateEmployee(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        
        $request->validate([
            'employee_id' => 'required|string|max:50|unique:employees,employee_id,' . $employee->id,
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'hire_date' => 'nullable|date',
            'date_of_birth' => 'nullable|date',
            'cnic' => 'nullable|string|max:15',
            'bank_account' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20'
        ]);

        $employee->update($request->all());

        return redirect()->route('hrm.employees')
            ->with('success', 'Employee updated successfully');
    }

    /**
     * Display attendance management
     */
    public function attendance()
    {
        $tenant = Auth::user()->tenant;
        $employees = Employee::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        $today = now()->toDateString();
        $attendance = Attendance::where('tenant_id', $tenant->id)
            ->where('date', $today)
            ->get()
            ->keyBy('employee_id');
        
        return view('hrm.attendance', compact('employees', 'attendance', 'today'));
    }

    /**
     * Mark attendance
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,absent,late,half_day',
            'notes' => 'nullable|string|max:255'
        ]);

        $tenant = Auth::user()->tenant;
        $employee = Employee::findOrFail($request->employee_id);
        
        // Verify employee belongs to tenant
        if ($employee->tenant_id !== $tenant->id) {
            return redirect()->back()
                ->with('error', 'Employee not found');
        }

        $attendanceData = $request->all();
        $attendanceData['tenant_id'] = $tenant->id;
        
        // Calculate hours worked if both check-in and check-out are provided
        if ($attendanceData['check_in'] && $attendanceData['check_out']) {
            $checkIn = \Carbon\Carbon::parse($attendanceData['date'] . ' ' . $attendanceData['check_in']);
            $checkOut = \Carbon\Carbon::parse($attendanceData['date'] . ' ' . $attendanceData['check_out']);
            $attendanceData['hours_worked'] = $checkIn->diffInHours($checkOut);
        }

        Attendance::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'employee_id' => $request->employee_id,
                'date' => $request->date
            ],
            $attendanceData
        );

        return redirect()->back()
            ->with('success', 'Attendance marked successfully');
    }

    /**
     * Display payroll management
     */
    public function payroll()
    {
        $tenant = Auth::user()->tenant;
        $employees = Employee::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        $currentMonth = now()->format('Y-m');
        $payrolls = Payroll::where('tenant_id', $tenant->id)
            ->where('pay_period', 'like', $currentMonth . '%')
            ->with('employee')
            ->get()
            ->keyBy('employee_id');
        
        return view('hrm.payroll', compact('employees', 'payrolls', 'currentMonth'));
    }

    /**
     * Process payroll
     */
    public function processPayroll(Request $request)
    {
        $request->validate([
            'pay_period' => 'required|date_format:Y-m',
            'employees' => 'required|array',
            'employees.*.employee_id' => 'required|exists:employees,id',
            'employees.*.basic_salary' => 'required|numeric|min:0',
            'employees.*.allowances' => 'nullable|numeric|min:0',
            'employees.*.deductions' => 'nullable|numeric|min:0',
            'employees.*.overtime_hours' => 'nullable|numeric|min:0',
            'employees.*.overtime_rate' => 'nullable|numeric|min:0'
        ]);

        $tenant = Auth::user()->tenant;
        
        DB::beginTransaction();
        
        try {
            foreach ($request->employees as $employeeData) {
                $employee = Employee::findOrFail($employeeData['employee_id']);
                
                // Verify employee belongs to tenant
                if ($employee->tenant_id !== $tenant->id) {
                    continue;
                }

                $payrollData = $this->payrollService->calculatePayroll($employee, $employeeData, $request->pay_period);
                
                Payroll::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'employee_id' => $employee->id,
                        'pay_period' => $request->pay_period
                    ],
                    $payrollData
                );
            }
            
            DB::commit();
            
            return redirect()->route('hrm.payroll')
                ->with('success', 'Payroll processed successfully');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Failed to process payroll: ' . $e->getMessage());
        }
    }

    /**
     * Generate tax deduction certificate
     */
    public function generateTaxCertificate(Request $request, Employee $employee)
    {
        $this->authorize('view', $employee);
        
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . date('Y')
        ]);

        $tenant = Auth::user()->tenant;
        $year = $request->year;
        
        // Get payroll data for the year
        $payrolls = Payroll::where('tenant_id', $tenant->id)
            ->where('employee_id', $employee->id)
            ->where('pay_period', 'like', $year . '%')
            ->get();

        if ($payrolls->isEmpty()) {
            return redirect()->back()
                ->with('error', 'No payroll data found for the selected year');
        }

        $totalSalary = $payrolls->sum('gross_salary');
        $totalTaxDeduction = $payrolls->sum('tax_deduction');
        $totalNetSalary = $payrolls->sum('net_salary');

        $certificateData = [
            'employee' => $employee,
            'tenant' => $tenant,
            'year' => $year,
            'total_salary' => $totalSalary,
            'total_tax_deduction' => $totalTaxDeduction,
            'total_net_salary' => $totalNetSalary,
            'monthly_breakdown' => $this->getMonthlyBreakdown($payrolls)
        ];

        return view('hrm.tax-certificate', $certificateData);
    }

    /**
     * Get monthly breakdown for tax certificate
     */
    private function getMonthlyBreakdown($payrolls)
    {
        $breakdown = [];
        
        foreach ($payrolls as $payroll) {
            $month = \Carbon\Carbon::parse($payroll->pay_period . '-01')->format('F');
            
            $breakdown[] = [
                'month' => $month,
                'gross_salary' => $payroll->gross_salary,
                'tax_deduction' => $payroll->tax_deduction,
                'net_salary' => $payroll->net_salary
            ];
        }

        return $breakdown;
    }

    /**
     * Display commission tracking
     */
    public function commissions()
    {
        $tenant = Auth::user()->tenant;
        
        $commissions = DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->join('employees', 'users.employee_id', '=', 'employees.id')
            ->where('sales.tenant_id', $tenant->id)
            ->where('sales.created_at', '>=', now()->subMonth())
            ->selectRaw('
                employees.id as employee_id,
                employees.name as employee_name,
                employees.position,
                COUNT(sales.id) as total_sales,
                SUM(sales.total_amount) as total_revenue,
                AVG(sales.total_amount) as average_sale
            ')
            ->groupBy('employees.id', 'employees.name', 'employees.position')
            ->orderBy('total_revenue', 'desc')
            ->get();
        
        return view('hrm.commissions', compact('commissions'));
    }

    /**
     * Get employee performance report
     */
    public function performanceReport(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $tenant = Auth::user()->tenant;
        $employee = Employee::findOrFail($request->employee_id);
        
        // Verify employee belongs to tenant
        if ($employee->tenant_id !== $tenant->id) {
            return redirect()->back()
                ->with('error', 'Employee not found');
        }

        $performance = $this->hrmService->getEmployeePerformance(
            $employee->id,
            $request->start_date,
            $request->end_date
        );

        return view('hrm.performance-report', compact('employee', 'performance'));
    }

    /**
     * Get HRM statistics
     */
    public function getStats()
    {
        $tenant = Auth::user()->tenant;
        $stats = $this->hrmService->getHrmStats($tenant->id);
        
        return response()->json($stats);
    }

    /**
     * Get attendance data for calendar
     */
    public function getAttendanceCalendar(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
            'employee_id' => 'nullable|exists:employees,id'
        ]);

        $tenant = Auth::user()->tenant;
        $query = Attendance::where('tenant_id', $tenant->id)
            ->where('date', 'like', $request->month . '%');

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        $attendance = $query->with('employee')
            ->get()
            ->groupBy('date');

        return response()->json($attendance);
    }
}