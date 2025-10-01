<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait TenantScope
{
    /**
     * Boot the tenant scope trait
     */
    protected static function bootTenantScope()
    {
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()->tenant) {
                $model->tenant_id = Auth::user()->tenant->id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check() && Auth::user()->tenant) {
                $builder->where('tenant_id', Auth::user()->tenant->id);
            }
        });
    }

    /**
     * Get the tenant that owns the model
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /**
     * Scope a query to only include records for a specific tenant
     */
    public function scopeForTenant(Builder $query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include records for the current tenant
     */
    public function scopeForCurrentTenant(Builder $query)
    {
        if (Auth::check() && Auth::user()->tenant) {
            return $query->where('tenant_id', Auth::user()->tenant->id);
        }
        
        return $query;
    }

    /**
     * Check if the model belongs to the current tenant
     */
    public function belongsToCurrentTenant()
    {
        if (!Auth::check() || !Auth::user()->tenant) {
            return false;
        }
        
        return $this->tenant_id === Auth::user()->tenant->id;
    }

    /**
     * Check if the model belongs to a specific tenant
     */
    public function belongsToTenant($tenantId)
    {
        return $this->tenant_id === $tenantId;
    }
}