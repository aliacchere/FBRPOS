import { Router } from 'express';
import { ReportController } from '../controllers/reportController';
import { authenticateToken, requireClient, requireRole } from '../middleware/auth';

const router = Router();

// All report routes require authentication
router.use(authenticateToken);
router.use(requireClient);

// Generate reports (all roles)
router.get('/sales', ReportController.generateSalesReport);
router.get('/tax', ReportController.generateTaxReport);
router.get('/fbr-compliance', ReportController.generateFBRComplianceReport);
router.get('/inventory', ReportController.generateInventoryReport);
router.get('/customers', ReportController.generateCustomerReport);

export default router;