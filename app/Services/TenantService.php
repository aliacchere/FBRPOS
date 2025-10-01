<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

class TenantService
{
    /**
     * Create a new tenant
     */
    public function createTenant(array $tenantData, array $adminData)
    {
        DB::beginTransaction();
        
        try {
            // Create tenant
            $tenant = Tenant::create([
                'business_name' => $tenantData['business_name'],
                'business_type' => $tenantData['business_type'] ?? 'retail',
                'address' => $tenantData['address'] ?? '',
                'city' => $tenantData['city'] ?? '',
                'state' => $tenantData['state'] ?? '',
                'postal_code' => $tenantData['postal_code'] ?? '',
                'country' => $tenantData['country'] ?? 'Pakistan',
                'phone' => $tenantData['phone'] ?? '',
                'email' => $tenantData['email'] ?? '',
                'website' => $tenantData['website'] ?? '',
                'ntn' => $tenantData['ntn'] ?? '',
                'strn' => $tenantData['strn'] ?? '',
                'license_key' => $tenantData['license_key'] ?? '',
                'subscription_plan' => $tenantData['subscription_plan'] ?? 'basic',
                'subscription_status' => 'active',
                'subscription_start_date' => now(),
                'subscription_end_date' => now()->addYear(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Create super admin user
            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'password' => Hash::make($adminData['password']),
                'role' => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Create default categories
            $this->createDefaultCategories($tenant->id);
            
            // Create default tax settings
            $this->createDefaultTaxSettings($tenant->id);
            
            // Create default fiscal year
            $this->createDefaultFiscalYear($tenant->id);
            
            // Create default user roles
            $this->createDefaultUserRoles($tenant->id);
            
            // Create default templates
            $this->createDefaultTemplates($tenant->id);
            
            // Create default settings
            $this->createDefaultSettings($tenant->id);
            
            DB::commit();
            
            return [
                'tenant' => $tenant,
                'admin' => $admin
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update tenant information
     */
    public function updateTenant(int $tenantId, array $data)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        $tenant->update($data);
        
        return $tenant;
    }

    /**
     * Suspend tenant
     */
    public function suspendTenant(int $tenantId, string $reason = null)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        $tenant->update([
            'is_active' => false,
            'suspension_reason' => $reason,
            'suspended_at' => now()
        ]);
        
        // Deactivate all users
        User::where('tenant_id', $tenantId)
            ->update(['is_active' => false]);
        
        return $tenant;
    }

    /**
     * Reactivate tenant
     */
    public function reactivateTenant(int $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        $tenant->update([
            'is_active' => true,
            'suspension_reason' => null,
            'suspended_at' => null
        ]);
        
        // Reactivate admin users
        User::where('tenant_id', $tenantId)
            ->whereIn('role', ['super_admin', 'admin'])
            ->update(['is_active' => true]);
        
        return $tenant;
    }

    /**
     * Delete tenant
     */
    public function deleteTenant(int $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        DB::beginTransaction();
        
        try {
            // Delete all tenant data
            $this->deleteTenantData($tenantId);
            
            // Delete tenant
            $tenant->delete();
            
            DB::commit();
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get tenant statistics
     */
    public function getTenantStatistics(int $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        $stats = [
            'users' => User::where('tenant_id', $tenantId)->count(),
            'products' => DB::table('products')->where('tenant_id', $tenantId)->count(),
            'customers' => DB::table('customers')->where('tenant_id', $tenantId)->count(),
            'suppliers' => DB::table('suppliers')->where('tenant_id', $tenantId)->count(),
            'sales' => DB::table('sales')->where('tenant_id', $tenantId)->count(),
            'inventory_value' => DB::table('products')
                ->where('tenant_id', $tenantId)
                ->sum(DB::raw('stock_quantity * cost_price')),
            'total_revenue' => DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->sum('total_amount'),
            'subscription_status' => $tenant->subscription_status,
            'subscription_end_date' => $tenant->subscription_end_date,
            'is_active' => $tenant->is_active
        ];
        
        return $stats;
    }

    /**
     * Check subscription status
     */
    public function checkSubscriptionStatus(int $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        if (!$tenant->is_active) {
            return [
                'status' => 'suspended',
                'message' => 'Tenant is suspended'
            ];
        }
        
        if ($tenant->subscription_end_date < now()) {
            return [
                'status' => 'expired',
                'message' => 'Subscription has expired'
            ];
        }
        
        if ($tenant->subscription_end_date < now()->addDays(7)) {
            return [
                'status' => 'expiring_soon',
                'message' => 'Subscription expires in ' . now()->diffInDays($tenant->subscription_end_date) . ' days'
            ];
        }
        
        return [
            'status' => 'active',
            'message' => 'Subscription is active'
        ];
    }

    /**
     * Renew subscription
     */
    public function renewSubscription(int $tenantId, int $months = 12)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        $newEndDate = $tenant->subscription_end_date > now() 
            ? Carbon::parse($tenant->subscription_end_date)->addMonths($months)
            : now()->addMonths($months);
        
        $tenant->update([
            'subscription_status' => 'active',
            'subscription_end_date' => $newEndDate,
            'is_active' => true
        ]);
        
        return $tenant;
    }

    /**
     * Create default categories
     */
    private function createDefaultCategories(int $tenantId)
    {
        $categories = [
            ['name' => 'General', 'description' => 'General products', 'is_active' => true],
            ['name' => 'Electronics', 'description' => 'Electronic products', 'is_active' => true],
            ['name' => 'Clothing', 'description' => 'Clothing and accessories', 'is_active' => true],
            ['name' => 'Food & Beverages', 'description' => 'Food and beverage products', 'is_active' => true],
            ['name' => 'Health & Beauty', 'description' => 'Health and beauty products', 'is_active' => true]
        ];
        
        foreach ($categories as $category) {
            DB::table('categories')->insert(array_merge($category, [
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    /**
     * Create default tax settings
     */
    private function createDefaultTaxSettings(int $tenantId)
    {
        $taxSettings = [
            [
                'name' => 'Standard Rate',
                'rate' => 18.0,
                'type' => 'percentage',
                'is_active' => true
            ],
            [
                'name' => 'Reduced Rate',
                'rate' => 10.0,
                'type' => 'percentage',
                'is_active' => true
            ],
            [
                'name' => 'Zero Rate',
                'rate' => 0.0,
                'type' => 'percentage',
                'is_active' => true
            ]
        ];
        
        foreach ($taxSettings as $setting) {
            DB::table('tax_settings')->insert(array_merge($setting, [
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    /**
     * Create default fiscal year
     */
    private function createDefaultFiscalYear(int $tenantId)
    {
        $currentYear = now()->year;
        $fiscalYear = [
            'name' => "FY {$currentYear}-" . ($currentYear + 1),
            'start_date' => "{$currentYear}-07-01",
            'end_date' => ($currentYear + 1) . "-06-30",
            'is_active' => true
        ];
        
        DB::table('fiscal_years')->insert(array_merge($fiscalYear, [
            'tenant_id' => $tenantId,
            'created_at' => now(),
            'updated_at' => now()
        ]));
    }

    /**
     * Create default user roles
     */
    private function createDefaultUserRoles(int $tenantId)
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'description' => 'Full system access',
                'permissions' => json_encode(['*']),
                'is_active' => true
            ],
            [
                'name' => 'Admin',
                'description' => 'Administrative access',
                'permissions' => json_encode([
                    'inventory.*',
                    'sales.*',
                    'customers.*',
                    'suppliers.*',
                    'reports.*',
                    'settings.*'
                ]),
                'is_active' => true
            ],
            [
                'name' => 'Cashier',
                'description' => 'Point of sale access',
                'permissions' => json_encode([
                    'pos.*',
                    'sales.create',
                    'sales.view',
                    'customers.view',
                    'products.view'
                ]),
                'is_active' => true
            ],
            [
                'name' => 'Manager',
                'description' => 'Management access',
                'permissions' => json_encode([
                    'inventory.*',
                    'sales.*',
                    'customers.*',
                    'reports.*',
                    'employees.view'
                ]),
                'is_active' => true
            ]
        ];
        
        foreach ($roles as $role) {
            DB::table('user_roles')->insert(array_merge($role, [
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    /**
     * Create default templates
     */
    private function createDefaultTemplates(int $tenantId)
    {
        $templates = [
            [
                'name' => 'Default Invoice',
                'type' => 'invoice',
                'content' => $this->getDefaultInvoiceTemplate(),
                'is_default' => true
            ],
            [
                'name' => 'Default Receipt',
                'type' => 'receipt',
                'content' => $this->getDefaultReceiptTemplate(),
                'is_default' => true
            ],
            [
                'name' => 'Default Quote',
                'type' => 'quote',
                'content' => $this->getDefaultQuoteTemplate(),
                'is_default' => true
            ]
        ];
        
        foreach ($templates as $template) {
            DB::table('templates')->insert(array_merge($template, [
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    /**
     * Create default settings
     */
    private function createDefaultSettings(int $tenantId)
    {
        $settings = [
            'currency' => 'PKR',
            'currency_symbol' => '₨',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'timezone' => 'Asia/Karachi',
            'language' => 'en',
            'theme' => 'default',
            'notifications' => json_encode([
                'email' => true,
                'whatsapp' => false,
                'low_stock' => true,
                'sales_summary' => true,
                'fbr_failure' => true
            ])
        ];
        
        DB::table('tenant_settings')->insert([
            'tenant_id' => $tenantId,
            'settings' => json_encode($settings),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Delete tenant data
     */
    private function deleteTenantData(int $tenantId)
    {
        $tables = [
            'users',
            'products',
            'customers',
            'suppliers',
            'employees',
            'sales',
            'sale_items',
            'stock_movements',
            'purchase_orders',
            'purchase_order_items',
            'payrolls',
            'attendances',
            'categories',
            'tax_settings',
            'fiscal_years',
            'user_roles',
            'templates',
            'tenant_settings',
            'fbr_integration_settings',
            'qr_code_settings',
            'notification_settings',
            'smtp_settings'
        ];
        
        foreach ($tables as $table) {
            DB::table($table)->where('tenant_id', $tenantId)->delete();
        }
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
}