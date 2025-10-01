<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Payroll;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Calculate payroll for an employee
     */
    public function calculatePayroll(Employee $employee, array $employeeData, string $payPeriod)
    {
        $basicSalary = $employeeData['basic_salary'];
        $allowances = $employeeData['allowances'] ?? 0;
        $deductions = $employeeData['deductions'] ?? 0;
        $overtimeHours = $employeeData['overtime_hours'] ?? 0;
        $overtimeRate = $employeeData['overtime_rate'] ?? 0;

        // Calculate overtime pay
        $overtimePay = $overtimeHours * $overtimeRate;

        // Calculate gross salary
        $grossSalary = $basicSalary + $allowances + $overtimePay;

        // Calculate tax deduction
        $taxDeduction = $this->calculateTaxDeduction($grossSalary);

        // Calculate other deductions (if any)
        $otherDeductions = $deductions;

        // Calculate net salary
        $netSalary = $grossSalary - $taxDeduction - $otherDeductions;

        return [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'pay_period' => $payPeriod,
            'basic_salary' => $basicSalary,
            'allowances' => $allowances,
            'overtime_hours' => $overtimeHours,
            'overtime_rate' => $overtimeRate,
            'overtime_pay' => $overtimePay,
            'gross_salary' => $grossSalary,
            'tax_deduction' => $taxDeduction,
            'other_deductions' => $otherDeductions,
            'net_salary' => $netSalary,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Calculate tax deduction based on Pakistani tax brackets
     */
    private function calculateTaxDeduction(float $grossSalary): float
    {
        $annualSalary = $grossSalary * 12;
        
        // Pakistani tax brackets for 2024
        if ($annualSalary <= 600000) {
            // No tax for income up to 600,000
            return 0;
        } elseif ($annualSalary <= 1200000) {
            // 2.5% for income from 600,001 to 1,200,000
            $taxableIncome = $annualSalary - 600000;
            $annualTax = $taxableIncome * 0.025;
        } elseif ($annualSalary <= 2400000) {
            // 12.5% for income from 1,200,001 to 2,400,000
            $taxableIncome = $annualSalary - 1200000;
            $annualTax = (600000 * 0.025) + ($taxableIncome * 0.125);
        } elseif ($annualSalary <= 3600000) {
            // 20% for income from 2,400,001 to 3,600,000
            $taxableIncome = $annualSalary - 2400000;
            $annualTax = (600000 * 0.025) + (1200000 * 0.125) + ($taxableIncome * 0.20);
        } elseif ($annualSalary <= 6000000) {
            // 25% for income from 3,600,001 to 6,000,000
            $taxableIncome = $annualSalary - 3600000;
            $annualTax = (600000 * 0.025) + (1200000 * 0.125) + (1200000 * 0.20) + ($taxableIncome * 0.25);
        } else {
            // 35% for income above 6,000,000
            $taxableIncome = $annualSalary - 6000000;
            $annualTax = (600000 * 0.025) + (1200000 * 0.125) + (1200000 * 0.20) + (2400000 * 0.25) + ($taxableIncome * 0.35);
        }

        // Return monthly tax deduction
        return $annualTax / 12;
    }

    /**
     * Calculate attendance-based salary
     */
    public function calculateAttendanceBasedSalary(Employee $employee, string $payPeriod): array
    {
        $startDate = Carbon::parse($payPeriod . '-01');
        $endDate = $startDate->copy()->endOfMonth();
        
        // Get attendance for the month
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalDays = $startDate->daysInMonth;
        $workingDays = $attendance->where('status', 'present')->count();
        $halfDays = $attendance->where('status', 'half_day')->count();
        $absentDays = $attendance->where('status', 'absent')->count();
        $lateDays = $attendance->where('status', 'late')->count();

        // Calculate attendance percentage
        $attendancePercentage = (($workingDays + ($halfDays * 0.5)) / $totalDays) * 100;

        // Calculate salary based on attendance
        $basicSalary = $employee->salary ?? 0;
        $attendanceBasedSalary = $basicSalary * ($attendancePercentage / 100);

        return [
            'total_days' => $totalDays,
            'working_days' => $workingDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            'late_days' => $lateDays,
            'attendance_percentage' => round($attendancePercentage, 2),
            'basic_salary' => $basicSalary,
            'attendance_based_salary' => round($attendanceBasedSalary, 2)
        ];
    }

    /**
     * Calculate overtime pay
     */
    public function calculateOvertimePay(Employee $employee, int $overtimeHours, float $overtimeRate = null): float
    {
        if (!$overtimeRate) {
            // Default overtime rate is 1.5x the hourly rate
            $hourlyRate = ($employee->salary ?? 0) / (8 * 30); // Assuming 8 hours per day, 30 days per month
            $overtimeRate = $hourlyRate * 1.5;
        }

        return $overtimeHours * $overtimeRate;
    }

    /**
     * Calculate commission for sales staff
     */
    public function calculateCommission(Employee $employee, string $payPeriod, float $commissionRate = 0.02): float
    {
        $startDate = Carbon::parse($payPeriod . '-01');
        $endDate = $startDate->copy()->endOfMonth();

        // Get sales made by the employee in the period
        $sales = \DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->where('users.employee_id', $employee->id)
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->sum('sales.total_amount');

        return $sales * $commissionRate;
    }

    /**
     * Generate payroll report
     */
    public function generatePayrollReport(string $tenantId, string $payPeriod)
    {
        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->where('pay_period', $payPeriod)
            ->with('employee')
            ->get();

        $summary = [
            'total_employees' => $payrolls->count(),
            'total_gross_salary' => $payrolls->sum('gross_salary'),
            'total_tax_deduction' => $payrolls->sum('tax_deduction'),
            'total_other_deductions' => $payrolls->sum('other_deductions'),
            'total_net_salary' => $payrolls->sum('net_salary'),
            'average_salary' => $payrolls->avg('gross_salary'),
            'highest_salary' => $payrolls->max('gross_salary'),
            'lowest_salary' => $payrolls->min('gross_salary')
        ];

        return [
            'summary' => $summary,
            'payrolls' => $payrolls
        ];
    }

    /**
     * Generate tax deduction summary
     */
    public function generateTaxDeductionSummary(string $tenantId, int $year)
    {
        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->where('pay_period', 'like', $year . '%')
            ->with('employee')
            ->get()
            ->groupBy('employee_id');

        $taxSummary = [];

        foreach ($payrolls as $employeeId => $employeePayrolls) {
            $employee = $employeePayrolls->first()->employee;
            $totalGrossSalary = $employeePayrolls->sum('gross_salary');
            $totalTaxDeduction = $employeePayrolls->sum('tax_deduction');
            $totalNetSalary = $employeePayrolls->sum('net_salary');

            $taxSummary[] = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_id_number' => $employee->employee_id,
                'cnic' => $employee->cnic,
                'total_gross_salary' => $totalGrossSalary,
                'total_tax_deduction' => $totalTaxDeduction,
                'total_net_salary' => $totalNetSalary,
                'monthly_breakdown' => $employeePayrolls->map(function ($payroll) {
                    return [
                        'month' => Carbon::parse($payroll->pay_period . '-01')->format('F'),
                        'gross_salary' => $payroll->gross_salary,
                        'tax_deduction' => $payroll->tax_deduction,
                        'net_salary' => $payroll->net_salary
                    ];
                })->values()
            ];
        }

        return $taxSummary;
    }

    /**
     * Calculate benefits and allowances
     */
    public function calculateBenefits(Employee $employee, string $payPeriod): array
    {
        $basicSalary = $employee->salary ?? 0;
        
        // Calculate various benefits (these would be configurable)
        $houseRentAllowance = $basicSalary * 0.45; // 45% of basic salary
        $medicalAllowance = $basicSalary * 0.10; // 10% of basic salary
        $transportAllowance = 15000; // Fixed amount
        $mealAllowance = 8000; // Fixed amount
        
        $totalBenefits = $houseRentAllowance + $medicalAllowance + $transportAllowance + $mealAllowance;

        return [
            'basic_salary' => $basicSalary,
            'house_rent_allowance' => $houseRentAllowance,
            'medical_allowance' => $medicalAllowance,
            'transport_allowance' => $transportAllowance,
            'meal_allowance' => $mealAllowance,
            'total_benefits' => $totalBenefits,
            'gross_salary' => $basicSalary + $totalBenefits
        ];
    }

    /**
     * Calculate deductions
     */
    public function calculateDeductions(Employee $employee, float $grossSalary): array
    {
        // Provident Fund (8.33% of basic salary)
        $providentFund = ($employee->salary ?? 0) * 0.0833;
        
        // Social Security (1% of gross salary)
        $socialSecurity = $grossSalary * 0.01;
        
        // Income Tax (calculated separately)
        $incomeTax = $this->calculateTaxDeduction($grossSalary);
        
        // Other deductions (advances, loans, etc.)
        $otherDeductions = 0; // This would be calculated based on employee's advances/loans
        
        $totalDeductions = $providentFund + $socialSecurity + $incomeTax + $otherDeductions;

        return [
            'provident_fund' => $providentFund,
            'social_security' => $socialSecurity,
            'income_tax' => $incomeTax,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions
        ];
    }

    /**
     * Generate payslip
     */
    public function generatePayslip(Payroll $payroll): array
    {
        $employee = $payroll->employee;
        $tenant = $employee->tenant;
        
        $payslip = [
            'employee' => [
                'name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'position' => $employee->position,
                'department' => $employee->department
            ],
            'employer' => [
                'name' => $tenant->business_name,
                'address' => $tenant->address,
                'ntn' => $tenant->ntn
            ],
            'pay_period' => $payroll->pay_period,
            'earnings' => [
                'basic_salary' => $payroll->basic_salary,
                'allowances' => $payroll->allowances,
                'overtime_pay' => $payroll->overtime_pay,
                'gross_salary' => $payroll->gross_salary
            ],
            'deductions' => [
                'tax_deduction' => $payroll->tax_deduction,
                'other_deductions' => $payroll->other_deductions,
                'total_deductions' => $payroll->tax_deduction + $payroll->other_deductions
            ],
            'net_salary' => $payroll->net_salary,
            'generated_at' => now()
        ];

        return $payslip;
    }
}