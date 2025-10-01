import { Router } from 'express';
import { AuthController, validateRegister, validateLogin, validateUpdateProfile } from '../controllers/authController';
import { authenticateToken, requireClient } from '../middleware/auth';

const router = Router();

// Public routes
router.post('/register', validateRegister, AuthController.register);
router.post('/login', validateLogin, AuthController.login);

// Protected routes
router.use(authenticateToken);
router.use(requireClient);

router.get('/profile', AuthController.getProfile);
router.put('/profile', validateUpdateProfile, AuthController.updateProfile);
router.put('/client-settings', AuthController.updateClientSettings);
router.get('/test-fbr-connection', AuthController.testFBRConnection);

export default router;