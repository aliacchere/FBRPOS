import { Router } from 'express';
import { 
  ProductController, 
  validateCreateProduct, 
  validateUpdateProduct, 
  validateUpdateStock 
} from '../controllers/productController';
import { authenticateToken, requireClient, requireRole } from '../middleware/auth';

const router = Router();

// All product routes require authentication
router.use(authenticateToken);
router.use(requireClient);

// Get products (all roles)
router.get('/', ProductController.getProducts);
router.get('/low-stock', ProductController.getLowStockProducts);
router.get('/fbr-reference/:type', ProductController.getFBRReferenceData);
router.get('/:id', ProductController.getProduct);

// Create, update, delete products (admin and manager only)
router.use(requireRole(['ADMIN', 'MANAGER']));

router.post('/', validateCreateProduct, ProductController.createProduct);
router.put('/:id', validateUpdateProduct, ProductController.updateProduct);
router.delete('/:id', ProductController.deleteProduct);
router.put('/:id/stock', validateUpdateStock, ProductController.updateStock);

export default router;