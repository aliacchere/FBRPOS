<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip tenant middleware for certain routes
        if ($this->shouldSkipTenantMiddleware($request)) {
            return $next($request);
        }

        // Get tenant from authenticated user
        $user = Auth::user();
        
        if (!$user || !$user->tenant) {
            return redirect()->route('login')->with('error', 'Tenant not found');
        }

        $tenant = $user->tenant;
        
        // Set tenant context
        $this->setTenantContext($tenant);
        
        // Add tenant to request
        $request->merge(['tenant' => $tenant]);
        
        return $next($request);
    }

    /**
     * Check if tenant middleware should be skipped
     */
    private function shouldSkipTenantMiddleware(Request $request)
    {
        $skipRoutes = [
            'login',
            'register',
            'password.reset',
            'password.email',
            'install.*',
            'api.install.*'
        ];
        
        foreach ($skipRoutes as $route) {
            if ($request->routeIs($route)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Set tenant context
     */
    private function setTenantContext($tenant)
    {
        // Set tenant ID in config
        Config::set('app.tenant_id', $tenant->id);
        
        // Set tenant-specific database connection if needed
        if ($tenant->database_connection) {
            Config::set('database.default', $tenant->database_connection);
        }
        
        // Set tenant-specific mail configuration
        $this->setTenantMailConfig($tenant);
        
        // Set tenant-specific storage configuration
        $this->setTenantStorageConfig($tenant);
        
        // Set tenant-specific cache configuration
        $this->setTenantCacheConfig($tenant);
    }

    /**
     * Set tenant-specific mail configuration
     */
    private function setTenantMailConfig($tenant)
    {
        $mailConfig = DB::table('tenant_mail_configs')
            ->where('tenant_id', $tenant->id)
            ->first();
        
        if ($mailConfig) {
            $config = json_decode($mailConfig->config, true);
            
            Config::set('mail.mailers.smtp.host', $config['host']);
            Config::set('mail.mailers.smtp.port', $config['port']);
            Config::set('mail.mailers.smtp.username', $config['username']);
            Config::set('mail.mailers.smtp.password', $config['password']);
            Config::set('mail.mailers.smtp.encryption', $config['encryption']);
            Config::set('mail.from.address', $config['from_email']);
            Config::set('mail.from.name', $config['from_name']);
        }
    }

    /**
     * Set tenant-specific storage configuration
     */
    private function setTenantStorageConfig($tenant)
    {
        // Set tenant-specific storage disk
        Config::set('filesystems.disks.tenant', [
            'driver' => 'local',
            'root' => storage_path('app/tenant_' . $tenant->id),
        ]);
    }

    /**
     * Set tenant-specific cache configuration
     */
    private function setTenantCacheConfig($tenant)
    {
        // Set tenant-specific cache prefix
        Config::set('cache.prefix', 'tenant_' . $tenant->id . '_');
    }
}