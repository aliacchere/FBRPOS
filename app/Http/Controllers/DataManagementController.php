<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\DataImportService;
use App\Services\DataExportService;
use App\Services\BackupService;
use App\Services\NotificationService;
use Carbon\Carbon;

class DataManagementController extends Controller
{
    protected $dataImportService;
    protected $dataExportService;
    protected $backupService;
    protected $notificationService;

    public function __construct(
        DataImportService $dataImportService,
        DataExportService $dataExportService,
        BackupService $backupService,
        NotificationService $notificationService
    ) {
        $this->dataImportService = $dataImportService;
        $this->dataExportService = $dataExportService;
        $this->backupService = $backupService;
        $this->notificationService = $notificationService;
    }

    /**
     * Display data management dashboard
     */
    public function index()
    {
        $tenant = Auth::user()->tenant;
        
        $stats = [
            'total_products' => $tenant->products()->count(),
            'total_customers' => $tenant->customers()->count(),
            'total_suppliers' => $tenant->suppliers()->count(),
            'total_employees' => $tenant->employees()->count(),
            'last_backup' => $this->backupService->getLastBackupDate($tenant->id),
            'backup_size' => $this->backupService->getBackupSize($tenant->id)
        ];
        
        return view('data-management.dashboard', compact('stats'));
    }

    /**
     * Display import/export page
     */
    public function importExport()
    {
        $tenant = Auth::user()->tenant;
        
        $importTemplates = $this->dataImportService->getImportTemplates();
        $exportOptions = $this->dataExportService->getExportOptions();
        
        return view('data-management.import-export', compact('importTemplates', 'exportOptions'));
    }

    /**
     * Download import template
     */
    public function downloadImportTemplate(Request $request)
    {
        $request->validate([
            'type' => 'required|in:products,customers,suppliers,employees'
        ]);

        $template = $this->dataImportService->generateImportTemplate($request->type);
        
        return response()->download($template['path'], $template['filename'])->deleteFileAfterSend(true);
    }

    /**
     * Import data from CSV/Excel
     */
    public function importData(Request $request)
    {
        $request->validate([
            'type' => 'required|in:products,customers,suppliers,employees',
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
            'update_existing' => 'boolean'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $result = $this->dataImportService->importData(
                $tenant->id,
                $request->type,
                $request->file('file'),
                $request->boolean('update_existing')
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Data imported successfully',
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Export data to CSV/Excel
     */
    public function exportData(Request $request)
    {
        $request->validate([
            'type' => 'required|in:products,customers,suppliers,employees,sales,inventory,payroll',
            'format' => 'required|in:csv,excel',
            'filters' => 'nullable|array'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $file = $this->dataExportService->exportData(
                $tenant->id,
                $request->type,
                $request->format,
                $request->filters ?? []
            );
            
            return response()->download($file['path'], $file['filename'])->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk export all data
     */
    public function bulkExportAll(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,excel,zip',
            'include_files' => 'boolean'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $files = $this->dataExportService->bulkExportAll(
                $tenant->id,
                $request->format,
                $request->boolean('include_files')
            );
            
            if ($request->format === 'zip') {
                return response()->download($files['path'], $files['filename'])->deleteFileAfterSend(true);
            } else {
                return response()->json([
                    'success' => true,
                    'files' => $files
                ]);
            }
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Bulk export failed: ' . $e->getMessage());
        }
    }

    /**
     * Display settings backup/restore page
     */
    public function settingsBackup()
    {
        $tenant = Auth::user()->tenant;
        
        $settingsBackups = $this->backupService->getSettingsBackups($tenant->id);
        
        return view('data-management.settings-backup', compact('settingsBackups'));
    }

    /**
     * Export business settings
     */
    public function exportSettings(Request $request)
    {
        $tenant = Auth::user()->tenant;
        
        try {
            $settingsFile = $this->backupService->exportSettings($tenant->id);
            
            return response()->download($settingsFile['path'], $settingsFile['filename'])->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Settings export failed: ' . $e->getMessage());
        }
    }

    /**
     * Import business settings
     */
    public function importSettings(Request $request)
    {
        $request->validate([
            'settings_file' => 'required|file|mimes:json|max:5120' // 5MB max
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $result = $this->backupService->importSettings($tenant->id, $request->file('settings_file'));
            
            return redirect()->back()
                ->with('success', 'Settings imported successfully. ' . $result['message']);
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Settings import failed: ' . $e->getMessage());
        }
    }

    /**
     * Display system backup page (Super Admin only)
     */
    public function systemBackup()
    {
        $this->authorize('superAdmin', Auth::user());
        
        $backups = $this->backupService->getSystemBackups();
        
        return view('data-management.system-backup', compact('backups'));
    }

    /**
     * Create system backup
     */
    public function createSystemBackup(Request $request)
    {
        $this->authorize('superAdmin', Auth::user());
        
        $request->validate([
            'backup_name' => 'nullable|string|max:255',
            'include_files' => 'boolean',
            'password' => 'required|string|min:8'
        ]);

        try {
            $backup = $this->backupService->createSystemBackup(
                $request->backup_name,
                $request->boolean('include_files'),
                $request->password
            );
            
            return response()->json([
                'success' => true,
                'message' => 'System backup created successfully',
                'backup' => $backup
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Backup creation failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Download system backup
     */
    public function downloadSystemBackup(Request $request, $backupId)
    {
        $this->authorize('superAdmin', Auth::user());
        
        try {
            $backup = $this->backupService->getSystemBackup($backupId);
            
            return response()->download($backup['path'], $backup['filename']);
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Backup download failed: ' . $e->getMessage());
        }
    }

    /**
     * Restore system from backup
     */
    public function restoreSystemBackup(Request $request)
    {
        $this->authorize('superAdmin', Auth::user());
        
        $request->validate([
            'backup_file' => 'required|file|mimes:zip|max:102400', // 100MB max
            'password' => 'required|string',
            'confirm_restore' => 'required|accepted'
        ]);

        try {
            $result = $this->backupService->restoreSystemBackup(
                $request->file('backup_file'),
                $request->password
            );
            
            return redirect()->back()
                ->with('success', 'System restored successfully. ' . $result['message']);
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'System restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete system backup
     */
    public function deleteSystemBackup(Request $request, $backupId)
    {
        $this->authorize('superAdmin', Auth::user());
        
        try {
            $this->backupService->deleteSystemBackup($backupId);
            
            return redirect()->back()
                ->with('success', 'Backup deleted successfully');
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Backup deletion failed: ' . $e->getMessage());
        }
    }

    /**
     * Schedule automatic backups
     */
    public function scheduleBackups(Request $request)
    {
        $this->authorize('superAdmin', Auth::user());
        
        $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly',
            'time' => 'required|date_format:H:i',
            'retention_days' => 'required|integer|min:1|max:365',
            'include_files' => 'boolean',
            'email_notifications' => 'boolean',
            'email_recipients' => 'nullable|array',
            'email_recipients.*' => 'email'
        ]);

        try {
            $this->backupService->scheduleBackups($request->all());
            
            return redirect()->back()
                ->with('success', 'Backup schedule updated successfully');
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Schedule update failed: ' . $e->getMessage());
        }
    }

    /**
     * Get import/export status
     */
    public function getImportExportStatus()
    {
        $tenant = Auth::user()->tenant;
        
        $status = [
            'recent_imports' => $this->dataImportService->getRecentImports($tenant->id),
            'recent_exports' => $this->dataExportService->getRecentExports($tenant->id),
            'backup_status' => $this->backupService->getBackupStatus($tenant->id)
        ];
        
        return response()->json($status);
    }

    /**
     * Validate import file
     */
    public function validateImportFile(Request $request)
    {
        $request->validate([
            'type' => 'required|in:products,customers,suppliers,employees',
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240'
        ]);

        try {
            $validation = $this->dataImportService->validateImportFile(
                $request->type,
                $request->file('file')
            );
            
            return response()->json([
                'success' => true,
                'validation' => $validation
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get data statistics
     */
    public function getDataStatistics()
    {
        $tenant = Auth::user()->tenant;
        
        $stats = [
            'products' => [
                'total' => $tenant->products()->count(),
                'active' => $tenant->products()->where('is_active', true)->count(),
                'low_stock' => $tenant->products()->whereRaw('stock_quantity <= min_stock_level')->count()
            ],
            'customers' => [
                'total' => $tenant->customers()->count(),
                'active' => $tenant->customers()->where('is_active', true)->count()
            ],
            'suppliers' => [
                'total' => $tenant->suppliers()->count(),
                'active' => $tenant->suppliers()->where('is_active', true)->count()
            ],
            'employees' => [
                'total' => $tenant->employees()->count(),
                'active' => $tenant->employees()->where('is_active', true)->count()
            ],
            'sales' => [
                'total' => $tenant->sales()->count(),
                'this_month' => $tenant->sales()->whereMonth('sale_date', now()->month)->count(),
                'revenue' => $tenant->sales()->sum('total_amount')
            ]
        ];
        
        return response()->json($stats);
    }

    /**
     * Clean up old data
     */
    public function cleanupOldData(Request $request)
    {
        $this->authorize('superAdmin', Auth::user());
        
        $request->validate([
            'data_type' => 'required|in:logs,backups,exports,imports',
            'older_than_days' => 'required|integer|min:30|max:365'
        ]);

        try {
            $result = $this->backupService->cleanupOldData(
                $request->data_type,
                $request->older_than_days
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Cleanup completed successfully',
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage()
            ], 400);
        }
    }
}