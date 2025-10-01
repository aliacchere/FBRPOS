import { Request, Response, NextFunction } from 'express';
import { PrismaClient } from '@prisma/client';
import { AppError, CreateSaleInput, PaginationParams } from '../types';
import { InvoiceService } from '../services/invoiceService';
import { FBRService } from '../services/fbrService';
import { encryptionService } from '../utils/encryption';
import { validateRequest, commonValidations, fbrValidations } from '../utils/validation';

const prisma = new PrismaClient();

export class SaleController {
  // Get all sales with filtering and pagination
  static async getSales(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const {
        page = 1,
        limit = 10,
        startDate,
        endDate,
        customerId,
        paymentMethod,
        fbrStatus,
        sortBy = 'createdAt',
        sortOrder = 'desc',
      } = req.query as any;

      const filters: any = {
        clientId,
      };

      if (startDate || endDate) {
        filters.createdAt = {};
        if (startDate) filters.createdAt.gte = new Date(startDate);
        if (endDate) filters.createdAt.lte = new Date(endDate);
      }

      if (customerId) {
        filters.customerId = customerId;
      }

      if (paymentMethod) {
        filters.paymentMethod = paymentMethod;
      }

      if (fbrStatus) {
        filters.fbrStatus = fbrStatus;
      }

      const skip = (parseInt(page) - 1) * parseInt(limit);

      const [sales, total] = await Promise.all([
        prisma.sale.findMany({
          where: filters,
          include: {
            customer: true,
            user: {
              select: {
                id: true,
                firstName: true,
                lastName: true,
              },
            },
            saleItems: {
              include: {
                product: {
                  select: {
                    id: true,
                    name: true,
                    sku: true,
                  },
                },
              },
            },
          },
          orderBy: { [sortBy]: sortOrder },
          skip,
          take: parseInt(limit),
        }),
        prisma.sale.count({ where: filters }),
      ]);

      res.json({
        success: true,
        data: sales,
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

  // Get single sale
  static async getSale(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      const sale = await prisma.sale.findFirst({
        where: { id, clientId },
        include: {
          customer: true,
          user: {
            select: {
              id: true,
              firstName: true,
              lastName: true,
            },
          },
          saleItems: {
            include: {
              product: {
                select: {
                  id: true,
                  name: true,
                  sku: true,
                  hsCode: true,
                  uom: true,
                  taxRate: true,
                },
              },
            },
          },
        },
      });

      if (!sale) {
        throw new AppError('Sale not found', 404);
      }

      res.json({
        success: true,
        data: sale,
      });
    } catch (error) {
      next(error);
    }
  }

  // Create new sale
  static async createSale(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const userId = (req as any).user.id;
      const saleData: CreateSaleInput = req.body;

      // Validate that items exist and have sufficient stock
      for (const item of saleData.items) {
        if (item.productId) {
          const product = await prisma.product.findFirst({
            where: { id: item.productId, clientId },
          });

          if (!product) {
            throw new AppError(`Product with ID ${item.productId} not found`, 404);
          }

          if (product.currentStock < item.quantity) {
            throw new AppError(`Insufficient stock for product ${product.name}. Available: ${product.currentStock}`, 400);
          }
        }
      }

      // Validate customer if provided
      if (saleData.customerId) {
        const customer = await prisma.customer.findFirst({
          where: { id: saleData.customerId, clientId },
        });

        if (!customer) {
          throw new AppError('Customer not found', 404);
        }
      }

      const client = await prisma.client.findUnique({
        where: { id: clientId },
      });

      if (!client) {
        throw new AppError('Client not found', 404);
      }

      const fbrToken = client.fbrToken ? encryptionService.decrypt(client.fbrToken) : null;
      const fbrService = fbrToken ? new FBRService(fbrToken, client.fbrBaseUrl) : null;
      const invoiceService = new InvoiceService(prisma, fbrService!);

      const sale = await invoiceService.createSale(saleData, clientId, userId);

      res.status(201).json({
        success: true,
        data: sale,
        message: 'Sale created successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Hold sale
  static async holdSale(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      const sale = await prisma.sale.findFirst({
        where: { id, clientId },
      });

      if (!sale) {
        throw new AppError('Sale not found', 404);
      }

      if (sale.isHeld) {
        throw new AppError('Sale is already held', 400);
      }

      const updatedSale = await prisma.sale.update({
        where: { id },
        data: {
          isHeld: true,
          heldAt: new Date(),
        },
      });

      res.json({
        success: true,
        data: updatedSale,
        message: 'Sale held successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Resume sale
  static async resumeSale(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      const sale = await prisma.sale.findFirst({
        where: { id, clientId },
      });

      if (!sale) {
        throw new AppError('Sale not found', 404);
      }

      if (!sale.isHeld) {
        throw new AppError('Sale is not held', 400);
      }

      const updatedSale = await prisma.sale.update({
        where: { id },
        data: {
          isHeld: false,
          heldAt: null,
        },
      });

      res.json({
        success: true,
        data: updatedSale,
        message: 'Sale resumed successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Get held sales
  static async getHeldSales(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;

      const heldSales = await prisma.sale.findMany({
        where: {
          clientId,
          isHeld: true,
        },
        include: {
          customer: true,
          user: {
            select: {
              id: true,
              firstName: true,
              lastName: true,
            },
          },
          saleItems: {
            include: {
              product: {
                select: {
                  id: true,
                  name: true,
                  sku: true,
                },
              },
            },
          },
        },
        orderBy: {
          heldAt: 'desc',
        },
      });

      res.json({
        success: true,
        data: heldSales,
      });
    } catch (error) {
      next(error);
    }
  }

  // Process return/refund
  static async processReturn(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;
      const { items, reason } = req.body;

      const originalSale = await prisma.sale.findFirst({
        where: { id, clientId },
        include: {
          saleItems: true,
        },
      });

      if (!originalSale) {
        throw new AppError('Original sale not found', 404);
      }

      // Validate return items
      for (const returnItem of items) {
        const originalItem = originalSale.saleItems.find(
          item => item.id === returnItem.saleItemId
        );

        if (!originalItem) {
          throw new AppError(`Sale item with ID ${returnItem.saleItemId} not found`, 404);
        }

        if (returnItem.quantity > originalItem.quantity) {
          throw new AppError(`Return quantity cannot exceed original quantity for item ${originalItem.productDescription}`, 400);
        }
      }

      // Calculate return totals
      let returnSubtotal = 0;
      let returnTaxAmount = 0;
      let returnTotalAmount = 0;

      const returnItems = items.map((returnItem: any) => {
        const originalItem = originalSale.saleItems.find(
          item => item.id === returnItem.saleItemId
        )!;

        const itemTotal = returnItem.quantity * originalItem.unitPrice;
        const itemTaxAmount = originalItem.salesTaxApplicable ? 
          (itemTotal * parseFloat(originalItem.rate || '0') / 100) : 0;
        const itemTotalWithTax = itemTotal + itemTaxAmount;

        returnSubtotal += itemTotal;
        returnTaxAmount += itemTaxAmount;
        returnTotalAmount += itemTotalWithTax;

        return {
          quantity: returnItem.quantity,
          unitPrice: originalItem.unitPrice,
          totalPrice: itemTotalWithTax,
          hsCode: originalItem.hsCode,
          productDescription: originalItem.productDescription,
          rate: originalItem.rate,
          uom: originalItem.uom,
          totalValues: itemTotalWithTax,
          valueSalesExcludingST: itemTotal,
          salesTaxApplicable: originalItem.salesTaxApplicable,
          salesTaxWithheldAtSource: originalItem.salesTaxWithheldAtSource,
          extraTax: originalItem.extraTax,
          furtherTax: originalItem.furtherTax,
          sroScheduleNo: originalItem.sroScheduleNo,
          fedPayable: itemTaxAmount,
          discount: originalItem.discount,
          saleType: originalItem.saleType,
          sroItemSerialNo: originalItem.sroItemSerialNo,
          productId: originalItem.productId,
        };
      });

      // Create return sale
      const returnSale = await prisma.sale.create({
        data: {
          invoiceNumber: `RET-${originalSale.invoiceNumber}`,
          invoiceType: 'CREDIT',
          subtotal: returnSubtotal,
          taxAmount: returnTaxAmount,
          totalAmount: returnTotalAmount,
          paymentMethod: originalSale.paymentMethod,
          paymentStatus: 'COMPLETED',
          notes: `Return for ${originalSale.invoiceNumber}. Reason: ${reason}`,
          clientId,
          customerId: originalSale.customerId,
          userId: (req as any).user.id,
          saleItems: {
            create: returnItems,
          },
        },
        include: {
          saleItems: true,
          customer: true,
        },
      });

      // Update product stock
      for (const returnItem of items) {
        const originalItem = originalSale.saleItems.find(
          item => item.id === returnItem.saleItemId
        )!;

        if (originalItem.productId) {
          await prisma.product.update({
            where: { id: originalItem.productId },
            data: {
              currentStock: {
                increment: returnItem.quantity,
              },
            },
          });
        }
      }

      res.status(201).json({
        success: true,
        data: returnSale,
        message: 'Return processed successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Get sale statistics
  static async getSaleStats(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { period = 'today' } = req.query;

      let startDate: Date;
      const endDate = new Date();

      switch (period) {
        case 'today':
          startDate = new Date();
          startDate.setHours(0, 0, 0, 0);
          break;
        case 'week':
          startDate = new Date();
          startDate.setDate(startDate.getDate() - 7);
          break;
        case 'month':
          startDate = new Date();
          startDate.setMonth(startDate.getMonth() - 1);
          break;
        case 'year':
          startDate = new Date();
          startDate.setFullYear(startDate.getFullYear() - 1);
          break;
        default:
          startDate = new Date();
          startDate.setHours(0, 0, 0, 0);
      }

      const [
        totalSales,
        totalAmount,
        averageOrderValue,
        fbrPendingCount,
        fbrErrorCount,
      ] = await Promise.all([
        prisma.sale.count({
          where: {
            clientId,
            createdAt: {
              gte: startDate,
              lte: endDate,
            },
          },
        }),
        prisma.sale.aggregate({
          where: {
            clientId,
            createdAt: {
              gte: startDate,
              lte: endDate,
            },
          },
          _sum: { totalAmount: true },
        }),
        prisma.sale.aggregate({
          where: {
            clientId,
            createdAt: {
              gte: startDate,
              lte: endDate,
            },
          },
          _avg: { totalAmount: true },
        }),
        prisma.sale.count({
          where: {
            clientId,
            fbrStatus: 'PENDING',
          },
        }),
        prisma.sale.count({
          where: {
            clientId,
            fbrStatus: 'ERROR',
          },
        }),
      ]);

      res.json({
        success: true,
        data: {
          period,
          totalSales,
          totalAmount: totalAmount._sum.totalAmount || 0,
          averageOrderValue: averageOrderValue._avg.totalAmount || 0,
          fbrPendingCount,
          fbrErrorCount,
        },
      });
    } catch (error) {
      next(error);
    }
  }
}

// Validation middleware
export const validateCreateSale = validateRequest([
  ...Object.values({
    items: {
      isArray: {
        errorMessage: 'Items must be an array',
      },
      custom: {
        options: (value: any[]) => {
          if (!value || value.length === 0) {
            throw new Error('At least one item is required');
          }
          return true;
        },
      },
    },
  }),
  ...Object.values(fbrValidations.paymentMethod('paymentMethod')),
]);

export const validateProcessReturn = validateRequest([
  ...Object.values({
    items: {
      isArray: {
        errorMessage: 'Items must be an array',
      },
      custom: {
        options: (value: any[]) => {
          if (!value || value.length === 0) {
            throw new Error('At least one item is required for return');
          }
          return true;
        },
      },
    },
    reason: {
      notEmpty: {
        errorMessage: 'Return reason is required',
      },
    },
  }),
]);