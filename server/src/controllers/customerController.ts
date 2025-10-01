import { Request, Response, NextFunction } from 'express';
import { PrismaClient } from '@prisma/client';
import { AppError, CustomerFilters, PaginationParams } from '../types';
import { FBRService } from '../services/fbrService';
import { encryptionService } from '../utils/encryption';
import { validateRequest, commonValidations, fbrValidations } from '../utils/validation';

const prisma = new PrismaClient();

export class CustomerController {
  // Get all customers with filtering and pagination
  static async getCustomers(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const {
        page = 1,
        limit = 10,
        search,
        province,
        isActive,
        sortBy = 'createdAt',
        sortOrder = 'desc',
      } = req.query as any;

      const filters: any = {
        clientId,
      };

      if (search) {
        filters.OR = [
          { name: { contains: search, mode: 'insensitive' } },
          { email: { contains: search, mode: 'insensitive' } },
          { phone: { contains: search, mode: 'insensitive' } },
          { buyerBusinessName: { contains: search, mode: 'insensitive' } },
          { buyerNTNCNIC: { contains: search, mode: 'insensitive' } },
        ];
      }

      if (province) {
        filters.buyerProvince = province;
      }

      if (isActive !== undefined) {
        filters.isActive = isActive === 'true';
      }

      const skip = (parseInt(page) - 1) * parseInt(limit);

      const [customers, total] = await Promise.all([
        prisma.customer.findMany({
          where: filters,
          orderBy: { [sortBy]: sortOrder },
          skip,
          take: parseInt(limit),
        }),
        prisma.customer.count({ where: filters }),
      ]);

      res.json({
        success: true,
        data: customers,
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total,
          totalPages: Math.ceil(total / parseInt(limit)),
        },
      });
    } catch (error) {
      next(error);
    }
  }

  // Get single customer
  static async getCustomer(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      const customer = await prisma.customer.findFirst({
        where: { id, clientId },
      });

      if (!customer) {
        throw new AppError('Customer not found', 404);
      }

      res.json({
        success: true,
        data: customer,
      });
    } catch (error) {
      next(error);
    }
  }

  // Create new customer
  static async createCustomer(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const {
        name,
        email,
        phone,
        address,
        buyerNTNCNIC,
        buyerBusinessName,
        buyerProvince,
        buyerAddress,
      } = req.body;

      // Check if customer with same NTN/CNIC already exists for this client
      if (buyerNTNCNIC) {
        const existingCustomer = await prisma.customer.findFirst({
          where: { buyerNTNCNIC, clientId },
        });

        if (existingCustomer) {
          throw new AppError('Customer with this NTN/CNIC already exists', 400);
        }
      }

      const customer = await prisma.customer.create({
        data: {
          name,
          email,
          phone,
          address,
          buyerNTNCNIC,
          buyerBusinessName,
          buyerProvince,
          buyerAddress,
          clientId,
        },
      });

      res.status(201).json({
        success: true,
        data: customer,
        message: 'Customer created successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Update customer
  static async updateCustomer(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;
      const updateData = req.body;

      // Check if customer exists
      const existingCustomer = await prisma.customer.findFirst({
        where: { id, clientId },
      });

      if (!existingCustomer) {
        throw new AppError('Customer not found', 404);
      }

      // Check NTN/CNIC uniqueness if NTN/CNIC is being updated
      if (updateData.buyerNTNCNIC && updateData.buyerNTNCNIC !== existingCustomer.buyerNTNCNIC) {
        const ntnExists = await prisma.customer.findFirst({
          where: { 
            buyerNTNCNIC: updateData.buyerNTNCNIC, 
            clientId, 
            id: { not: id } 
          },
        });

        if (ntnExists) {
          throw new AppError('Customer with this NTN/CNIC already exists', 400);
        }
      }

      const customer = await prisma.customer.update({
        where: { id },
        data: updateData,
      });

      res.json({
        success: true,
        data: customer,
        message: 'Customer updated successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Delete customer
  static async deleteCustomer(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      // Check if customer exists
      const customer = await prisma.customer.findFirst({
        where: { id, clientId },
      });

      if (!customer) {
        throw new AppError('Customer not found', 404);
      }

      // Check if customer is used in any sales
      const saleCount = await prisma.sale.count({
        where: { customerId: id },
      });

      if (saleCount > 0) {
        throw new AppError('Cannot delete customer that has sales records', 400);
      }

      await prisma.customer.delete({
        where: { id },
      });

      res.json({
        success: true,
        message: 'Customer deleted successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Get FBR provinces
  static async getFBRProvinces(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;

      const client = await prisma.client.findUnique({
        where: { id: clientId },
      });

      if (!client || !client.fbrToken) {
        throw new AppError('FBR API token not configured for this client', 400);
      }

      const fbrToken = encryptionService.decrypt(client.fbrToken);
      const fbrService = new FBRService(fbrToken, client.fbrBaseUrl);

      const provinces = await fbrService.getProvinces();

      res.json({
        success: true,
        data: provinces,
      });
    } catch (error) {
      next(error);
    }
  }

  // Search customers by name, phone, or NTN/CNIC
  static async searchCustomers(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { q } = req.query;

      if (!q || q.toString().length < 2) {
        res.json({
          success: true,
          data: [],
        });
        return;
      }

      const customers = await prisma.customer.findMany({
        where: {
          clientId,
          isActive: true,
          OR: [
            { name: { contains: q.toString(), mode: 'insensitive' } },
            { phone: { contains: q.toString(), mode: 'insensitive' } },
            { buyerNTNCNIC: { contains: q.toString(), mode: 'insensitive' } },
            { buyerBusinessName: { contains: q.toString(), mode: 'insensitive' } },
          ],
        },
        select: {
          id: true,
          name: true,
          phone: true,
          email: true,
          buyerNTNCNIC: true,
          buyerBusinessName: true,
          buyerProvince: true,
        },
        take: 10,
        orderBy: {
          name: 'asc',
        },
      });

      res.json({
        success: true,
        data: customers,
      });
    } catch (error) {
      next(error);
    }
  }

  // Get customer statistics
  static async getCustomerStats(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;

      const [
        totalCustomers,
        activeCustomers,
        customersWithSales,
        totalSalesAmount,
      ] = await Promise.all([
        prisma.customer.count({
          where: { clientId },
        }),
        prisma.customer.count({
          where: { clientId, isActive: true },
        }),
        prisma.customer.count({
          where: {
            clientId,
            sales: {
              some: {},
            },
          },
        }),
        prisma.sale.aggregate({
          where: { clientId },
          _sum: { totalAmount: true },
        }),
      ]);

      res.json({
        success: true,
        data: {
          totalCustomers,
          activeCustomers,
          customersWithSales,
          totalSalesAmount: totalSalesAmount._sum.totalAmount || 0,
        },
      });
    } catch (error) {
      next(error);
    }
  }
}

// Validation middleware
export const validateCreateCustomer = validateRequest([
  ...Object.values(commonValidations.requiredString('name')),
  ...Object.values(commonValidations.email('email')),
  ...Object.values(commonValidations.phone('phone')),
  ...Object.values(commonValidations.ntnCnic('buyerNTNCNIC')),
  ...Object.values(fbrValidations.province('buyerProvince')),
]);

export const validateUpdateCustomer = validateRequest([
  ...Object.values(commonValidations.requiredString('name')),
  ...Object.values(commonValidations.email('email')),
  ...Object.values(commonValidations.phone('phone')),
  ...Object.values(commonValidations.ntnCnic('buyerNTNCNIC')),
  ...Object.values(fbrValidations.province('buyerProvince')),
]);