import { Router } from 'express';
import { DashboardController } from '../controllers/dashboardController';
import { authenticateToken, requireClient } from '../middleware/auth';

const router = Router();

// All dashboard routes require authentication
router.use(authenticateToken);
router.use(requireClient);

// Dashboard statistics
router.get('/stats', DashboardController.getDashboardStats);
router.get('/sales-chart', DashboardController.getSalesChartData);
router.get('/top-products', DashboardController.getTopProducts);
router.get('/recent-activities', DashboardController.getRecentActivities);
router.get('/fbr-status', DashboardController.getFBRStatusSummary);

export default router;