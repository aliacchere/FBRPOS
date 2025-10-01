<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\TemplateService;
use App\Services\QrCodeService;

class TemplateController extends Controller
{
    protected $templateService;
    protected $qrCodeService;

    public function __construct(TemplateService $templateService, QrCodeService $qrCodeService)
    {
        $this->templateService = $templateService;
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Display template management dashboard
     */
    public function index()
    {
        $tenant = Auth::user()->tenant;
        
        $templates = $this->templateService->getTemplates($tenant->id);
        $availableTags = $this->templateService->getAvailableTags();
        $qrCodeSettings = $this->qrCodeService->getQrCodeSettings($tenant->id);
        
        return view('templates.dashboard', compact('templates', 'availableTags', 'qrCodeSettings'));
    }

    /**
     * Create new template
     */
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:invoice,receipt,quote,delivery_note',
            'content' => 'required|string',
            'is_default' => 'boolean'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $template = $this->templateService->createTemplate($tenant->id, $request->all());
            
            return response()->json([
                'success' => true,
                'template' => $template,
                'message' => 'Template created successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update template
     */
    public function update(Request $request, $templateId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'is_default' => 'boolean'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $template = $this->templateService->updateTemplate($templateId, $tenant->id, $request->all());
            
            return response()->json([
                'success' => true,
                'template' => $template,
                'message' => 'Template updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete template
     */
    public function delete($templateId)
    {
        $tenant = Auth::user()->tenant;
        
        try {
            $this->templateService->deleteTemplate($templateId, $tenant->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Preview template
     */
    public function preview(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'type' => 'required|in:invoice,receipt,quote,delivery_note'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $preview = $this->templateService->previewTemplate(
                $request->content,
                $request->type,
                $tenant->id
            );
            
            return response()->json([
                'success' => true,
                'preview' => $preview
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate document from template
     */
    public function generateDocument(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:templates,id',
            'data' => 'required|array',
            'format' => 'required|in:pdf,html'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $document = $this->templateService->generateDocument(
                $request->template_id,
                $request->data,
                $request->format,
                $tenant->id
            );
            
            return response()->json([
                'success' => true,
                'document' => $document
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Duplicate template
     */
    public function duplicate(Request $request, $templateId)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $template = $this->templateService->duplicateTemplate(
                $templateId,
                $request->name,
                $tenant->id
            );
            
            return response()->json([
                'success' => true,
                'template' => $template,
                'message' => 'Template duplicated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Set default template
     */
    public function setDefault(Request $request, $templateId)
    {
        $tenant = Auth::user()->tenant;
        
        try {
            $this->templateService->setDefaultTemplate($templateId, $tenant->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Default template updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Import template
     */
    public function import(Request $request)
    {
        $request->validate([
            'template_file' => 'required|file|mimes:json|max:5120',
            'type' => 'required|in:invoice,receipt,quote,delivery_note'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $template = $this->templateService->importTemplate(
                $request->file('template_file'),
                $request->type,
                $tenant->id
            );
            
            return response()->json([
                'success' => true,
                'template' => $template,
                'message' => 'Template imported successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Export template
     */
    public function export($templateId)
    {
        $tenant = Auth::user()->tenant;
        
        try {
            $export = $this->templateService->exportTemplate($templateId, $tenant->id);
            
            return response()->download($export['path'], $export['filename'])->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Template export failed: ' . $e->getMessage());
        }
    }

    /**
     * Get template by type
     */
    public function getByType(Request $request)
    {
        $request->validate([
            'type' => 'required|in:invoice,receipt,quote,delivery_note'
        ]);

        $tenant = Auth::user()->tenant;
        
        $templates = $this->templateService->getTemplatesByType($tenant->id, $request->type);
        
        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    /**
     * Update QR code settings
     */
    public function updateQrCodeSettings(Request $request)
    {
        $request->validate([
            'qr_code_type' => 'required|in:fbr_verification,dps_verification,disabled',
            'qr_code_size' => 'required|integer|min:100|max:500',
            'qr_code_position' => 'required|in:top_left,top_right,bottom_left,bottom_right,center',
            'custom_text' => 'nullable|string|max:255'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $settings = $this->qrCodeService->updateQrCodeSettings($tenant->id, $request->all());
            
            return response()->json([
                'success' => true,
                'settings' => $settings,
                'message' => 'QR code settings updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate QR code
     */
    public function generateQrCode(Request $request)
    {
        $request->validate([
            'data' => 'required|string',
            'size' => 'nullable|integer|min:100|max:500',
            'type' => 'required|in:fbr_verification,dps_verification,custom'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $qrCode = $this->qrCodeService->generateQrCode(
                $request->data,
                $request->type,
                $request->size ?? 200,
                $tenant->id
            );
            
            return response()->json([
                'success' => true,
                'qr_code' => $qrCode
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get template statistics
     */
    public function getStatistics()
    {
        $tenant = Auth::user()->tenant;
        
        $stats = $this->templateService->getTemplateStatistics($tenant->id);
        
        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Validate template content
     */
    public function validateTemplate(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'type' => 'required|in:invoice,receipt,quote,delivery_note'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $validation = $this->templateService->validateTemplate(
                $request->content,
                $request->type,
                $tenant->id
            );
            
            return response()->json([
                'success' => true,
                'validation' => $validation
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get template preview data
     */
    public function getPreviewData(Request $request)
    {
        $request->validate([
            'type' => 'required|in:invoice,receipt,quote,delivery_note'
        ]);

        $tenant = Auth::user()->tenant;
        
        $previewData = $this->templateService->getPreviewData($request->type, $tenant->id);
        
        return response()->json([
            'success' => true,
            'preview_data' => $previewData
        ]);
    }

    /**
     * Reset template to default
     */
    public function resetToDefault($templateId)
    {
        $tenant = Auth::user()->tenant;
        
        try {
            $template = $this->templateService->resetToDefault($templateId, $tenant->id);
            
            return response()->json([
                'success' => true,
                'template' => $template,
                'message' => 'Template reset to default successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get template history
     */
    public function getTemplateHistory($templateId)
    {
        $tenant = Auth::user()->tenant;
        
        $history = $this->templateService->getTemplateHistory($templateId, $tenant->id);
        
        return response()->json([
            'success' => true,
            'history' => $history
        ]);
    }

    /**
     * Restore template version
     */
    public function restoreVersion(Request $request, $templateId)
    {
        $request->validate([
            'version_id' => 'required|exists:template_versions,id'
        ]);

        $tenant = Auth::user()->tenant;
        
        try {
            $template = $this->templateService->restoreVersion(
                $templateId,
                $request->version_id,
                $tenant->id
            );
            
            return response()->json([
                'success' => true,
                'template' => $template,
                'message' => 'Template version restored successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}