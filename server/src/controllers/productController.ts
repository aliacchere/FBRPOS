import { Request, Response, NextFunction } from 'express';
import { PrismaClient } from '@prisma/client';
import { AppError, ProductFilters, PaginationParams } from '../types';
import { InvoiceService } from '../services/invoiceService';
import { FBRService } from '../services/fbrService';
import { encryptionService } from '../utils/encryption';
import { validateRequest, commonValidations, fbrValidations } from '../utils/validation';

const prisma = new PrismaClient();

export class ProductController {
  // Get all products with filtering and pagination
  static async getProducts(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const {
        page = 1,
        limit = 10,
        search,
        categoryId,
        lowStock,
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
          { sku: { contains: search, mode: 'insensitive' } },
          { description: { contains: search, mode: 'insensitive' } },
        ];
      }

      if (categoryId) {
        filters.categoryId = categoryId;
      }

      if (isActive !== undefined) {
        filters.isActive = isActive === 'true';
      }

      if (lowStock === 'true') {
        filters.currentStock = {
          lte: await this.getLowStockThreshold(clientId),
        };
      }

      const skip = (parseInt(page) - 1) * parseInt(limit);

      const [products, total] = await Promise.all([
        prisma.product.findMany({
          where: filters,
          include: {
            category: true,
          },
          orderBy: { [sortBy]: sortOrder },
          skip,
          take: parseInt(limit),
        }),
        prisma.product.count({ where: filters }),
      ]);

      res.json({
        success: true,
        data: products,
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

  // Get single product
  static async getProduct(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      const product = await prisma.product.findFirst({
        where: { id, clientId },
        include: {
          category: true,
        },
      });

      if (!product) {
        throw new AppError('Product not found', 404);
      }

      res.json({
        success: true,
        data: product,
      });
    } catch (error) {
      next(error);
    }
  }

  // Create new product
  static async createProduct(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const {
        name,
        sku,
        description,
        categoryId,
        purchasePrice,
        sellingPrice,
        currentStock,
        minStockLevel,
        maxStockLevel,
        hsCode,
        uom,
        taxRate,
        sroScheduleNo,
        sroItemSerialNo,
      } = req.body;

      // Check if SKU already exists for this client
      const existingProduct = await prisma.product.findFirst({
        where: { sku, clientId },
      });

      if (existingProduct) {
        throw new AppError('Product with this SKU already exists', 400);
      }

      const product = await prisma.product.create({
        data: {
          name,
          sku,
          description,
          categoryId: categoryId || null,
          purchasePrice: parseFloat(purchasePrice),
          sellingPrice: parseFloat(sellingPrice),
          currentStock: parseInt(currentStock) || 0,
          minStockLevel: parseInt(minStockLevel) || 0,
          maxStockLevel: maxStockLevel ? parseInt(maxStockLevel) : null,
          hsCode,
          uom,
          taxRate: parseFloat(taxRate) || 17.0,
          sroScheduleNo,
          sroItemSerialNo,
          clientId,
        },
        include: {
          category: true,
        },
      });

      res.status(201).json({
        success: true,
        data: product,
        message: 'Product created successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Update product
  static async updateProduct(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;
      const updateData = req.body;

      // Check if product exists
      const existingProduct = await prisma.product.findFirst({
        where: { id, clientId },
      });

      if (!existingProduct) {
        throw new AppError('Product not found', 404);
      }

      // Check SKU uniqueness if SKU is being updated
      if (updateData.sku && updateData.sku !== existingProduct.sku) {
        const skuExists = await prisma.product.findFirst({
          where: { sku: updateData.sku, clientId, id: { not: id } },
        });

        if (skuExists) {
          throw new AppError('Product with this SKU already exists', 400);
        }
      }

      // Convert numeric fields
      if (updateData.purchasePrice) updateData.purchasePrice = parseFloat(updateData.purchasePrice);
      if (updateData.sellingPrice) updateData.sellingPrice = parseFloat(updateData.sellingPrice);
      if (updateData.currentStock) updateData.currentStock = parseInt(updateData.currentStock);
      if (updateData.minStockLevel) updateData.minStockLevel = parseInt(updateData.minStockLevel);
      if (updateData.maxStockLevel) updateData.maxStockLevel = parseInt(updateData.maxStockLevel);
      if (updateData.taxRate) updateData.taxRate = parseFloat(updateData.taxRate);

      const product = await prisma.product.update({
        where: { id },
        data: updateData,
        include: {
          category: true,
        },
      });

      res.json({
        success: true,
        data: product,
        message: 'Product updated successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Delete product
  static async deleteProduct(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      // Check if product exists
      const product = await prisma.product.findFirst({
        where: { id, clientId },
      });

      if (!product) {
        throw new AppError('Product not found', 404);
      }

      // Check if product is used in any sales
      const saleItemCount = await prisma.saleItem.count({
        where: { productId: id },
      });

      if (saleItemCount > 0) {
        throw new AppError('Cannot delete product that has been used in sales', 400);
      }

      await prisma.product.delete({
        where: { id },
      });

      res.json({
        success: true,
        message: 'Product deleted successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Get FBR reference data for products
  static async getFBRReferenceData(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { type } = req.params;

      const client = await prisma.client.findUnique({
        where: { id: clientId },
      });

      if (!client || !client.fbrToken) {
        throw new AppError('FBR API token not configured for this client', 400);
      }

      const fbrToken = encryptionService.decrypt(client.fbrToken);
      const fbrService = new FBRService(fbrToken, client.fbrBaseUrl);

      let data;
      switch (type) {
        case 'hsCodes':
          data = await fbrService.getHSCodes();
          break;
        case 'uomCodes':
          data = await fbrService.getUOMCodes();
          break;
        case 'sroSchedules':
          data = await fbrService.getSROSchedules();
          break;
        default:
          throw new AppError('Invalid reference data type', 400);
      }

      res.json({
        success: true,
        data,
      });
    } catch (error) {
      next(error);
    }
  }

  // Get low stock products
  static async getLowStockProducts(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const lowStockThreshold = await this.getLowStockThreshold(clientId);

      const products = await prisma.product.findMany({
        where: {
          clientId,
          currentStock: {
            lte: lowStockThreshold,
          },
          isActive: true,
        },
        include: {
          category: true,
        },
        orderBy: {
          currentStock: 'asc',
        },
      });

      res.json({
        success: true,
        data: products,
      });
    } catch (error) {
      next(error);
    }
  }

  // Update product stock
  static async updateStock(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;
      const { quantity, type, reason } = req.body; // type: 'add', 'subtract', 'set'

      const product = await prisma.product.findFirst({
        where: { id, clientId },
      });

      if (!product) {
        throw new AppError('Product not found', 404);
      }

      let newStock = product.currentStock;
      switch (type) {
        case 'add':
          newStock += parseInt(quantity);
          break;
        case 'subtract':
          newStock -= parseInt(quantity);
          break;
        case 'set':
          newStock = parseInt(quantity);
          break;
        default:
          throw new AppError('Invalid stock update type', 400);
      }

      if (newStock < 0) {
        throw new AppError('Stock cannot be negative', 400);
      }

      const updatedProduct = await prisma.product.update({
        where: { id },
        data: {
          currentStock: newStock,
        },
        include: {
          category: true,
        },
      });

      res.json({
        success: true,
        data: updatedProduct,
        message: 'Stock updated successfully',
      });
    } catch (error) {
      next(error);
    }
  }

  // Helper method to get low stock threshold
  private static async getLowStockThreshold(clientId: string): Promise<number> {
    const settings = await prisma.clientSettings.findUnique({
      where: { clientId },
    });

    return settings?.lowStockThreshold || 10;
  }
}

// Validation middleware
export const validateCreateProduct = validateRequest([
  ...Object.values(commonValidations.requiredString('name')),
  ...Object.values(commonValidations.requiredString('sku')),
  ...Object.values(commonValidations.positiveNumber('purchasePrice')),
  ...Object.values(commonValidations.positiveNumber('sellingPrice')),
  ...Object.values(commonValidations.positiveInteger('currentStock')),
  ...Object.values(commonValidations.positiveInteger('minStockLevel')),
  ...Object.values(fbrValidations.hsCode('hsCode')),
  ...Object.values(fbrValidations.taxRate('taxRate')),
]);

export const validateUpdateProduct = validateRequest([
  ...Object.values(commonValidations.requiredString('name')),
  ...Object.values(commonValidations.requiredString('sku')),
  ...Object.values(commonValidations.positiveNumber('purchasePrice')),
  ...Object.values(commonValidations.positiveNumber('sellingPrice')),
  ...Object.values(commonValidations.positiveInteger('currentStock')),
  ...Object.values(commonValidations.positiveInteger('minStockLevel')),
  ...Object.values(fbrValidations.hsCode('hsCode')),
  ...Object.values(fbrValidations.taxRate('taxRate')),
]);

export const validateUpdateStock = validateRequest([
  ...Object.values(commonValidations.positiveInteger('quantity')),
  ...Object.values({
    type: {
      isIn: {
        options: [['add', 'subtract', 'set']],
        errorMessage: 'Type must be add, subtract, or set',
      },
    },
  }),
]);