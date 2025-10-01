import { Router } from 'express';
import { InvoiceController } from '../controllers/invoiceController';
import { authenticateToken, requireClient, requireRole } from '../middleware/auth';

const router = Router();

// All invoice routes require authentication
router.use(authenticateToken);
router.use(requireClient);

// Get invoice data and stats (all roles)
router.get('/stats', InvoiceController.getInvoiceStats);
router.get('/fbr-logs', InvoiceController.getFBRLogs);
router.get('/fbr-reference/:type', InvoiceController.getFBRReferenceData);

// Validate and submit invoices (all roles)
router.post('/:id/validate', InvoiceController.validateInvoice);
router.post('/:id/submit', InvoiceController.submitInvoice);

// Generate PDF (all roles)
router.get('/:id/pdf', InvoiceController.generateInvoicePDF);

export default router;