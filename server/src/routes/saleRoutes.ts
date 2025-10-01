import { Router } from 'express';
import { 
  SaleController, 
  validateCreateSale, 
  validateProcessReturn 
} from '../controllers/saleController';
import { authenticateToken, requireClient, requireRole } from '../middleware/auth';

const router = Router();

// All sale routes require authentication
router.use(authenticateToken);
router.use(requireClient);

// Get sales and stats (all roles)
router.get('/', SaleController.getSales);
router.get('/stats', SaleController.getSaleStats);
router.get('/held', SaleController.getHeldSales);
router.get('/:id', SaleController.getSale);

// Create sales (all roles)
router.post('/', validateCreateSale, SaleController.createSale);

// Hold/Resume sales (all roles)
router.put('/:id/hold', SaleController.holdSale);
router.put('/:id/resume', SaleController.resumeSale);

// Process returns (admin and manager only)
router.use(requireRole(['ADMIN', 'MANAGER']));
router.post('/:id/return', validateProcessReturn, SaleController.processReturn);

export default router;