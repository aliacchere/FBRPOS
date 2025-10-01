<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'business_type',
        'ntn',
        'strn',
        'address',
        'city',
        'province',
        'postal_code',
        'phone',
        'email',
        'website',
        'logo',
        'fiscal_year_start',
        'fiscal_year_end',
        'currency',
        'timezone',
        'is_active',
        'subscription_plan',
        'subscription_start',
        'subscription_end'
    ];

    protected $casts = [
        'fiscal_year_start' => 'date',
        'fiscal_year_end' => 'date',
        'subscription_start' => 'date',
        'subscription_end' => 'date',
        'is_active' => 'boolean'
    ];

    /**
     * Get the users for the tenant
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the FBR configuration for the tenant
     */
    public function fbrConfig(): HasOne
    {
        return $this->hasOne(FbrConfig::class);
    }

    /**
     * Get the categories for the tenant
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the products for the tenant
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the customers for the tenant
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get the suppliers for the tenant
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /**
     * Get the sales for the tenant
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get the purchase orders for the tenant
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Get the employees for the tenant
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get the templates for the tenant
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    /**
     * Get the settings for the tenant
     */
    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    /**
     * Get the audit logs for the tenant
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get tenant setting value
     */
    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('setting_key', $key)->first();
        return $setting ? $setting->setting_value : $default;
    }

    /**
     * Set tenant setting value
     */
    public function setSetting(string $key, $value): void
    {
        $this->settings()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
    }

    /**
     * Check if tenant has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        if (!$this->subscription_end) {
            return false;
        }

        return $this->subscription_end->isFuture();
    }

    /**
     * Get fiscal year start date
     */
    public function getFiscalYearStartAttribute($value)
    {
        if (!$value) {
            return now()->startOfYear();
        }

        return $this->asDate($value);
    }

    /**
     * Get fiscal year end date
     */
    public function getFiscalYearEndAttribute($value)
    {
        if (!$value) {
            return now()->endOfYear();
        }

        return $this->asDate($value);
    }

    /**
     * Get current fiscal year
     */
    public function getCurrentFiscalYear(): array
    {
        $start = $this->fiscal_year_start;
        $end = $this->fiscal_year_end;

        // If current date is before fiscal year start, use previous year
        if (now()->lt($start)) {
            $start = $start->subYear();
            $end = $end->subYear();
        }

        return [
            'start' => $start,
            'end' => $end
        ];
    }

    /**
     * Check if date is in current fiscal year
     */
    public function isInCurrentFiscalYear($date): bool
    {
        $fiscalYear = $this->getCurrentFiscalYear();
        $date = is_string($date) ? \Carbon\Carbon::parse($date) : $date;

        return $date->between($fiscalYear['start'], $fiscalYear['end']);
    }

    /**
     * Get business logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return asset('uploads/logos/' . $this->logo);
    }

    /**
     * Get formatted business address
     */
    public function getFormattedAddressAttribute(): string
    {
        $address = $this->address;
        
        if ($this->city) {
            $address .= ', ' . $this->city;
        }
        
        if ($this->province) {
            $address .= ', ' . $this->province;
        }
        
        if ($this->postal_code) {
            $address .= ' ' . $this->postal_code;
        }

        return $address;
    }

    /**
     * Get subscription status
     */
    public function getSubscriptionStatusAttribute(): string
    {
        if (!$this->subscription_end) {
            return 'inactive';
        }

        if ($this->subscription_end->isPast()) {
            return 'expired';
        }

        if ($this->subscription_end->diffInDays(now()) <= 7) {
            return 'expiring_soon';
        }

        return 'active';
    }

    /**
     * Scope for active tenants
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for tenants with active subscriptions
     */
    public function scopeWithActiveSubscription($query)
    {
        return $query->where('subscription_end', '>', now());
    }
}