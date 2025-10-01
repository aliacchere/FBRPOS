<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'customer_id',
        'invoice_number',
        'reference_number',
        'fbr_invoice_number',
        'fbr_status',
        'fbr_error',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'notes',
        'sale_date'
    ];

    protected $casts = [
        'sale_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2'
    ];

    /**
     * Get the tenant that owns the sale
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user that created the sale
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer for the sale
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the sale items for the sale
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Get the FBR queue entries for the sale
     */
    public function fbrQueue(): HasMany
    {
        return $this->hasMany(FbrQueue::class);
    }

    /**
     * Get the stock movements for the sale
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', 'sale');
    }

    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'Rs. ' . number_format($this->total_amount, 2);
    }

    /**
     * Get formatted subtotal
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return 'Rs. ' . number_format($this->subtotal, 2);
    }

    /**
     * Get formatted tax amount
     */
    public function getFormattedTaxAttribute(): string
    {
        return 'Rs. ' . number_format($this->tax_amount, 2);
    }

    /**
     * Get FBR status badge class
     */
    public function getFbrStatusBadgeClassAttribute(): string
    {
        return match ($this->fbr_status) {
            'synced' => 'bg-green-500',
            'pending' => 'bg-yellow-500',
            'failed' => 'bg-red-500',
            default => 'bg-gray-500'
        };
    }

    /**
     * Get FBR status text
     */
    public function getFbrStatusTextAttribute(): string
    {
        return match ($this->fbr_status) {
            'synced' => 'Synced',
            'pending' => 'Pending',
            'failed' => 'Failed',
            default => 'Unknown'
        };
    }

    /**
     * Get payment status badge class
     */
    public function getPaymentStatusBadgeClassAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'bg-green-500',
            'pending' => 'bg-yellow-500',
            'refunded' => 'bg-red-500',
            default => 'bg-gray-500'
        };
    }

    /**
     * Get payment status text
     */
    public function getPaymentStatusTextAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'refunded' => 'Refunded',
            default => 'Unknown'
        };
    }

    /**
     * Get payment method text
     */
    public function getPaymentMethodTextAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'card' => 'Card',
            'easypaisa' => 'Easypaisa',
            'jazzcash' => 'JazzCash',
            'credit' => 'Credit',
            default => 'Unknown'
        };
    }

    /**
     * Check if sale is FBR synced
     */
    public function isFbrSynced(): bool
    {
        return $this->fbr_status === 'synced';
    }

    /**
     * Check if sale is FBR pending
     */
    public function isFbrPending(): bool
    {
        return $this->fbr_status === 'pending';
    }

    /**
     * Check if sale is FBR failed
     */
    public function isFbrFailed(): bool
    {
        return $this->fbr_status === 'failed';
    }

    /**
     * Check if sale is paid
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if sale is pending payment
     */
    public function isPendingPayment(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if sale is refunded
     */
    public function isRefunded(): bool
    {
        return $this->payment_status === 'refunded';
    }

    /**
     * Get total items count
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Get total items count (as decimal)
     */
    public function getTotalItemsDecimalAttribute(): float
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Get tax rate percentage
     */
    public function getTaxRatePercentageAttribute(): float
    {
        if ($this->subtotal == 0) {
            return 0;
        }

        return ($this->tax_amount / $this->subtotal) * 100;
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentageAttribute(): float
    {
        if ($this->subtotal == 0) {
            return 0;
        }

        return ($this->discount_amount / $this->subtotal) * 100;
    }

    /**
     * Scope for FBR synced sales
     */
    public function scopeFbrSynced($query)
    {
        return $query->where('fbr_status', 'synced');
    }

    /**
     * Scope for FBR pending sales
     */
    public function scopeFbrPending($query)
    {
        return $query->where('fbr_status', 'pending');
    }

    /**
     * Scope for FBR failed sales
     */
    public function scopeFbrFailed($query)
    {
        return $query->where('fbr_status', 'failed');
    }

    /**
     * Scope for paid sales
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope for pending payment sales
     */
    public function scopePendingPayment($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope for refunded sales
     */
    public function scopeRefunded($query)
    {
        return $query->where('payment_status', 'refunded');
    }

    /**
     * Scope for sales by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('sale_date', [$startDate, $endDate]);
    }

    /**
     * Scope for sales by fiscal year
     */
    public function scopeByFiscalYear($query, $tenantId, $fiscalYearStart, $fiscalYearEnd)
    {
        return $query->where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$fiscalYearStart, $fiscalYearEnd]);
    }

    /**
     * Scope for sales by payment method
     */
    public function scopeByPaymentMethod($query, $paymentMethod)
    {
        return $query->where('payment_method', $paymentMethod);
    }

    /**
     * Scope for sales by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for sales by customer
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Get sales summary for date range
     */
    public static function getSummaryForDateRange($tenantId, $startDate, $endDate)
    {
        return static::where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(total_amount) as total_revenue,
                SUM(subtotal) as total_subtotal,
                SUM(tax_amount) as total_tax,
                SUM(discount_amount) as total_discount,
                AVG(total_amount) as average_sale,
                SUM(CASE WHEN fbr_status = "synced" THEN 1 ELSE 0 END) as fbr_synced,
                SUM(CASE WHEN fbr_status = "pending" THEN 1 ELSE 0 END) as fbr_pending,
                SUM(CASE WHEN fbr_status = "failed" THEN 1 ELSE 0 END) as fbr_failed
            ')
            ->first();
    }

    /**
     * Get daily sales summary
     */
    public static function getDailySummary($tenantId, $date)
    {
        return static::where('tenant_id', $tenantId)
            ->where('sale_date', $date)
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(total_amount) as total_revenue,
                SUM(CASE WHEN fbr_status = "synced" THEN 1 ELSE 0 END) as fbr_synced,
                SUM(CASE WHEN fbr_status = "pending" THEN 1 ELSE 0 END) as fbr_pending,
                SUM(CASE WHEN fbr_status = "failed" THEN 1 ELSE 0 END) as fbr_failed
            ')
            ->first();
    }

    /**
     * Get monthly sales summary
     */
    public static function getMonthlySummary($tenantId, $year, $month)
    {
        return static::where('tenant_id', $tenantId)
            ->whereYear('sale_date', $year)
            ->whereMonth('sale_date', $month)
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(total_amount) as total_revenue,
                SUM(CASE WHEN fbr_status = "synced" THEN 1 ELSE 0 END) as fbr_synced,
                SUM(CASE WHEN fbr_status = "pending" THEN 1 ELSE 0 END) as fbr_pending,
                SUM(CASE WHEN fbr_status = "failed" THEN 1 ELSE 0 END) as fbr_failed
            ')
            ->first();
    }
}