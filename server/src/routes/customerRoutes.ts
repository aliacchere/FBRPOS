import { Router } from 'express';
import { 
  CustomerController, 
  validateCreateCustomer, 
  validateUpdateCustomer 
} from '../controllers/customerController';
import { authenticateToken, requireClient, requireRole } from '../middleware/auth';

const router = Router();

// All customer routes require authentication
router.use(authenticateToken);
router.use(requireClient);

// Get customers and search (all roles)
router.get('/', CustomerController.getCustomers);
router.get('/search', CustomerController.searchCustomers);
router.get('/stats', CustomerController.getCustomerStats);
router.get('/fbr-provinces', CustomerController.getFBRProvinces);
router.get('/:id', CustomerController.getCustomer);

// Create, update, delete customers (admin and manager only)
router.use(requireRole(['ADMIN', 'MANAGER']));

router.post('/', validateCreateCustomer, CustomerController.createCustomer);
router.put('/:id', validateUpdateCustomer, CustomerController.updateCustomer);
router.delete('/:id', CustomerController.deleteCustomer);

export default router;