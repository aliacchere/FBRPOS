import { Request, Response, NextFunction } from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { PrismaClient } from '@prisma/client';
import { AppError } from '../types';
import { encryptionService } from '../utils/encryption';
import { validateRequest, commonValidations } from '../utils/validation';

const prisma = new PrismaClient();

export class AuthController {
  // Register new client and admin user
  static async register(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const {
        clientName,
        clientEmail,
        clientPhone,
        clientAddress,
        clientProvince,
        businessName,
        businessAddress,
        businessProvince,
        fbrToken,
        fbrBaseUrl,
        adminFirstName,
        adminLastName,
        adminEmail,
        adminPassword,
      } = req.body;

      // Check if client already exists
      const existingClient = await prisma.client.findUnique({
        where: { email: clientEmail },
      });

      if (existingClient) {
        throw new AppError('Client with this email already exists', 400);
      }

      // Check if admin user already exists
      const existingUser = await prisma.user.findUnique({
        where: { email: adminEmail },
      });

      if (existingUser) {
        throw new AppError('User with this email already exists', 400);
      }

      // Hash password
      const hashedPassword = await bcrypt.hash(adminPassword, 12);

      // Encrypt FBR token if provided
      const encryptedFbrToken = fbrToken ? encryptionService.encrypt(fbrToken) : null;

      // Create client and admin user in a transaction
      const result = await prisma.$transaction(async (tx) => {
        // Create client
        const client = await tx.client.create({
          data: {
            name: clientName,
            email: clientEmail,
            phone: clientPhone,
            address: clientAddress,
            province: clientProvince,
            businessName,
            businessAddress,
            businessProvince,
            fbrToken: encryptedFbrToken,
            fbrBaseUrl,
          },
        });

        // Create admin user
        const user = await tx.user.create({
          data: {
            email: adminEmail,
            password: hashedPassword,
            firstName: adminFirstName,
            lastName: adminLastName,
            role: 'ADMIN',
            clientId: client.id,
          },
        });

        // Create client settings
        await tx.clientSettings.create({
          data: {
            clientId: client.id,
          },
        });

        return { client, user };
      });

      // Generate JWT token
      const token = jwt.sign(
        { 
          userId: result.user.id, 
          clientId: result.client.id,
          role: result.user.role 
        },
        process.env.JWT_SECRET!,
        { expiresIn: process.env.JWT_EXPIRES_IN || '7d' }
      );

      res.status(201).json({
        success: true,
        data: {
          token,
          user: {
            id: result.user.id,
            email: result.user.email,
            firstName: result.user.firstName,
            lastName: result.user.lastName,
            role: result.user.role,
          },
          client: {
            id: result.client.id,
            name: result.client.name,
            businessName: result.client.businessName,
          },
        },
        message: 'Client and admin user created successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Login user
  static async login(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { email, password } = req.body;

      // Find user with client information
      const user = await prisma.user.findUnique({
        where: { email },
        include: {
          client: true,
        },
      });

      if (!user || !user.isActive) {
        throw new AppError('Invalid credentials', 401);
      }

      if (!user.client.isActive) {
        throw new AppError('Client account is inactive', 401);
      }

      // Check password
      const isPasswordValid = await bcrypt.compare(password, user.password);
      if (!isPasswordValid) {
        throw new AppError('Invalid credentials', 401);
      }

      // Generate JWT token
      const token = jwt.sign(
        { 
          userId: user.id, 
          clientId: user.clientId,
          role: user.role 
        },
        process.env.JWT_SECRET!,
        { expiresIn: process.env.JWT_EXPIRES_IN || '7d' }
      );

      res.json({
        success: true,
        data: {
          token,
          user: {
            id: user.id,
            email: user.email,
            firstName: user.firstName,
            lastName: user.lastName,
            role: user.role,
          },
          client: {
            id: user.client.id,
            name: user.client.name,
            businessName: user.client.businessName,
          },
        },
        message: 'Login successful',
      });
    } catch (error) {
      next(error);
    }
  }

  // Get current user profile
  static async getProfile(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const user = (req as any).user;
      const client = (req as any).client;

      res.json({
        success: true,
        data: {
          user: {
            id: user.id,
            email: user.email,
            firstName: user.firstName,
            lastName: user.lastName,
            role: user.role,
          },
          client: {
            id: client.id,
            name: client.name,
            businessName: client.businessName,
            businessAddress: client.businessAddress,
            businessProvince: client.businessProvince,
            hasFbrToken: !!client.fbrToken,
          },
        },
      });
    } catch (error) {
      next(error);
    }
  }

  // Update user profile
  static async updateProfile(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const userId = (req as any).user.id;
      const { firstName, lastName, currentPassword, newPassword } = req.body;

      const user = await prisma.user.findUnique({
        where: { id: userId },
      });

      if (!user) {
        throw new AppError('User not found', 404);
      }

      const updateData: any = {
        firstName,
        lastName,
      };

      // Update password if provided
      if (newPassword) {
        if (!currentPassword) {
          throw new AppError('Current password is required to change password', 400);
        }

        const isCurrentPasswordValid = await bcrypt.compare(currentPassword, user.password);
        if (!isCurrentPasswordValid) {
          throw new AppError('Current password is incorrect', 400);
        }

        updateData.password = await bcrypt.hash(newPassword, 12);
      }

      const updatedUser = await prisma.user.update({
        where: { id: userId },
        data: updateData,
        select: {
          id: true,
          email: true,
          firstName: true,
          lastName: true,
          role: true,
        },
      });

      res.json({
        success: true,
        data: updatedUser,
        message: 'Profile updated successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Update client settings
  static async updateClientSettings(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const {
        businessName,
        businessAddress,
        businessProvince,
        fbrToken,
        fbrBaseUrl,
        currency,
        taxRate,
        lowStockThreshold,
        invoicePrefix,
        timezone,
        dateFormat,
      } = req.body;

      // Encrypt FBR token if provided
      const encryptedFbrToken = fbrToken ? encryptionService.encrypt(fbrToken) : undefined;

      const updatedClient = await prisma.client.update({
        where: { id: clientId },
        data: {
          businessName,
          businessAddress,
          businessProvince,
          fbrToken: encryptedFbrToken,
          fbrBaseUrl,
        },
      });

      // Update client settings
      await prisma.clientSettings.upsert({
        where: { clientId },
        create: {
          clientId,
          currency,
          taxRate,
          lowStockThreshold,
          invoicePrefix,
          timezone,
          dateFormat,
        },
        update: {
          currency,
          taxRate,
          lowStockThreshold,
          invoicePrefix,
          timezone,
          dateFormat,
        },
      });

      res.json({
        success: true,
        data: {
          id: updatedClient.id,
          businessName: updatedClient.businessName,
          businessAddress: updatedClient.businessAddress,
          businessProvince: updatedClient.businessProvince,
          hasFbrToken: !!updatedClient.fbrToken,
          fbrBaseUrl: updatedClient.fbrBaseUrl,
        },
        message: 'Client settings updated successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Test FBR connection
  static async testFBRConnection(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const client = (req as any).client;

      if (!client.fbrToken) {
        throw new AppError('FBR API token not configured', 400);
      }

      const fbrToken = encryptionService.decrypt(client.fbrToken);
      const { FBRService } = await import('../services/fbrService');
      const fbrService = new FBRService(fbrToken, client.fbrBaseUrl);

      const isConnected = await fbrService.testConnection();

      res.json({
        success: true,
        data: { connected: isConnected },
        message: isConnected ? 'FBR connection successful' : 'FBR connection failed',
      });
    } catch (error) {
      next(error);
    }
  }
}

// Validation middleware
export const validateRegister = validateRequest([
  ...Object.values(commonValidations.requiredString('clientName')),
  ...Object.values(commonValidations.email('clientEmail')),
  ...Object.values(commonValidations.phone('clientPhone')),
  ...Object.values(commonValidations.requiredString('businessName')),
  ...Object.values(commonValidations.requiredString('businessAddress')),
  ...Object.values(commonValidations.requiredString('businessProvince')),
  ...Object.values(commonValidations.requiredString('adminFirstName')),
  ...Object.values(commonValidations.requiredString('adminLastName')),
  ...Object.values(commonValidations.email('adminEmail')),
  ...Object.values(commonValidations.password('adminPassword')),
]);

export const validateLogin = validateRequest([
  ...Object.values(commonValidations.email('email')),
  ...Object.values(commonValidations.requiredString('password')),
]);

export const validateUpdateProfile = validateRequest([
  ...Object.values(commonValidations.requiredString('firstName')),
  ...Object.values(commonValidations.requiredString('lastName')),
  ...Object.values(commonValidations.password('newPassword').password.optional()),
]);