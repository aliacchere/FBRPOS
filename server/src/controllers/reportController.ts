import { Request, Response, NextFunction } from 'express';
import { PrismaClient } from '@prisma/client';
import { AppError, SalesReportFilters, TaxReportData } from '../types';

const prisma = new PrismaClient();

export class ReportController {
  // Generate sales report
  static async generateSalesReport(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const {
        startDate,
        endDate,
        customerId,
        productId,
        paymentMethod,
        fbrStatus,
      } = req.query as any;

      const filters: any = {
        clientId,
        createdAt: {
          gte: new Date(startDate),
          lte: new Date(endDate),
        },
      };

      if (customerId) filters.customerId = customerId;
      if (paymentMethod) filters.paymentMethod = paymentMethod;
      if (fbrStatus) filters.fbrStatus = fbrStatus;

      const [sales, summary] = await Promise.all([
        prisma.sale.findMany({
          where: filters,
          include: {
            customer: {
              select: {
                name: true,
                buyerBusinessName: true,
              },
            },
            user: {
              select: {
                firstName: true,
                lastName: true,
              },
            },
            saleItems: {
              include: {
                product: {
                  select: {
                    name: true,
                    sku: true,
                  },
                },
              },
            },
          },
          orderBy: { createdAt: 'desc' },
        }),
        prisma.sale.aggregate({
          where: filters,
          _count: { id: true },
          _sum: {
            subtotal: true,
            taxAmount: true,
            discountAmount: true,
            totalAmount: true,
          },
        }),
      ]);

      // Filter by product if specified
      let filteredSales = sales;
      if (productId) {
        filteredSales = sales.filter(sale =>
          sale.saleItems.some(item => item.productId === productId)
        );
      }

      res.json({
        success: true,
        data: {
          summary: {
            totalSales: summary._count.id,
            totalSubtotal: summary._sum.subtotal || 0,
            totalTax: summary._sum.taxAmount || 0,
            totalDiscount: summary._sum.discountAmount || 0,
            totalAmount: summary._sum.totalAmount || 0,
          },
          sales: filteredSales,
          filters: {
            startDate,
            endDate,
            customerId,
            productId,
            paymentMethod,
            fbrStatus,
          },
        },
      });
    } catch (error) {
      next(error);
    }
  }

  // Generate tax report
  static async generateTaxReport(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { startDate, endDate } = req.query as any;

      const filters = {
        clientId,
        createdAt: {
          gte: new Date(startDate),
          lte: new Date(endDate),
        },
      };

      const [sales, summary] = await Promise.all([
        prisma.sale.findMany({
          where: filters,
          include: {
            saleItems: true,
          },
          orderBy: { createdAt: 'desc' },
        }),
        prisma.sale.aggregate({
          where: filters,
          _sum: {
            totalAmount: true,
            taxAmount: true,
          },
        }),
      ]);

      // Calculate tax breakdown
      let totalSalesTaxApplicable = 0;
      let totalFurtherTax = 0;
      let totalExtraTax = 0;
      let totalSalesTaxWithheldAtSource = 0;
      let totalFedPayable = 0;

      sales.forEach(sale => {
        sale.saleItems.forEach(item => {
          if (item.salesTaxApplicable) {
            totalSalesTaxApplicable += item.valueSalesExcludingST;
          }
          totalFurtherTax += item.furtherTax;
          totalExtraTax += item.extraTax;
          totalSalesTaxWithheldAtSource += item.salesTaxWithheldAtSource;
          totalFedPayable += item.fedPayable;
        });
      });

      const taxReport: TaxReportData = {
        period: `${startDate} to ${endDate}`,
        totalSales: summary._sum.totalAmount || 0,
        totalTax: summary._sum.taxAmount || 0,
        salesTaxApplicable: totalSalesTaxApplicable,
        furtherTax: totalFurtherTax,
        extraTax: totalExtraTax,
        salesTaxWithheldAtSource: totalSalesTaxWithheldAtSource,
        fedPayable: totalFedPayable,
        invoices: sales.map(sale => ({
          invoiceNumber: sale.invoiceNumber,
          date: sale.createdAt.toISOString().split('T')[0],
          totalAmount: sale.totalAmount,
          taxAmount: sale.taxAmount,
          fbrStatus: sale.fbrStatus,
        })),
      };

      res.json({
        success: true,
        data: taxReport,
      });
    } catch (error) {
      next(error);
    }
  }

  // Generate FBR compliance report
  static async generateFBRComplianceReport(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { startDate, endDate } = req.query as any;

      const filters = {
        clientId,
        createdAt: {
          gte: new Date(startDate),
          lte: new Date(endDate),
        },
      };

      const [sales, fbrLogs] = await Promise.all([
        prisma.sale.findMany({
          where: filters,
          include: {
            customer: {
              select: {
                name: true,
                buyerBusinessName: true,
              },
            },
          },
          orderBy: { createdAt: 'desc' },
        }),
        prisma.fBRLog.findMany({
          where: {
            clientId,
            createdAt: {
              gte: new Date(startDate),
              lte: new Date(endDate),
            },
          },
          orderBy: { createdAt: 'desc' },
        }),
      ]);

      // Calculate compliance metrics
      const totalInvoices = sales.length;
      const submittedToFBR = sales.filter(sale => 
        ['SUBMITTED', 'APPROVED', 'REJECTED'].includes(sale.fbrStatus)
      ).length;
      const fbrSuccess = sales.filter(sale => sale.fbrStatus === 'APPROVED').length;
      const fbrErrors = sales.filter(sale => sale.fbrStatus === 'ERROR').length;
      const pendingFBR = sales.filter(sale => sale.fbrStatus === 'PENDING').length;

      const complianceRate = totalInvoices > 0 ? (submittedToFBR / totalInvoices) * 100 : 0;
      const successRate = submittedToFBR > 0 ? (fbrSuccess / submittedToFBR) * 100 : 0;

      res.json({
        success: true,
        data: {
          period: `${startDate} to ${endDate}`,
          totalInvoices,
          submittedToFBR,
          fbrSuccess,
          fbrErrors,
          pendingFBR,
          complianceRate: Math.round(complianceRate * 100) / 100,
          successRate: Math.round(successRate * 100) / 100,
          sales: sales.map(sale => ({
            invoiceNumber: sale.invoiceNumber,
            date: sale.createdAt.toISOString().split('T')[0],
            customer: sale.customer?.buyerBusinessName || sale.customer?.name || 'Walk-in Customer',
            totalAmount: sale.totalAmount,
            fbrStatus: sale.fbrStatus,
            fbrInvoiceNumber: sale.fbrInvoiceNumber,
            fbrDated: sale.fbrDated,
            fbrError: sale.fbrError,
          })),
          fbrLogs: fbrLogs.map(log => ({
            id: log.id,
            endpoint: log.endpoint,
            statusCode: log.statusCode,
            success: log.success,
            error: log.error,
            createdAt: log.createdAt,
          })),
        },
      });
    } catch (error) {
      next(error);
    }
  }

  // Generate inventory report
  static async generateInventoryReport(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { lowStockOnly = false } = req.query as any;

      const filters: any = { clientId };

      if (lowStockOnly === 'true') {
        const settings = await prisma.clientSettings.findUnique({
          where: { clientId },
        });
        const lowStockThreshold = settings?.lowStockThreshold || 10;
        
        filters.currentStock = {
          lte: lowStockThreshold,
        };
      }

      const products = await prisma.product.findMany({
        where: filters,
        include: {
          category: {
            select: {
              name: true,
            },
          },
          _count: {
            select: {
              saleItems: true,
            },
          },
        },
        orderBy: { name: 'asc' },
      });

      // Calculate inventory value
      const totalInventoryValue = products.reduce((sum, product) => {
        return sum + (product.currentStock * product.purchasePrice);
      }, 0);

      const lowStockProducts = products.filter(product => 
        product.currentStock <= product.minStockLevel
      );

      res.json({
        success: true,
        data: {
          totalProducts: products.length,
          totalInventoryValue,
          lowStockProducts: lowStockProducts.length,
          products: products.map(product => ({
            id: product.id,
            name: product.name,
            sku: product.sku,
            category: product.category?.name || 'Uncategorized',
            currentStock: product.currentStock,
            minStockLevel: product.minStockLevel,
            maxStockLevel: product.maxStockLevel,
            purchasePrice: product.purchasePrice,
            sellingPrice: product.sellingPrice,
            inventoryValue: product.currentStock * product.purchasePrice,
            salesCount: product._count.saleItems,
            isLowStock: product.currentStock <= product.minStockLevel,
            hsCode: product.hsCode,
            uom: product.uom,
            taxRate: product.taxRate,
          })),
        },
      });
    } catch (error) {
      next(error);
    }
  }

  // Generate customer report
  static async generateCustomerReport(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { startDate, endDate } = req.query as any;

      const dateFilters = startDate && endDate ? {
        createdAt: {
          gte: new Date(startDate),
          lte: new Date(endDate),
        },
      } : {};

      const [customers, customerStats] = await Promise.all([
        prisma.customer.findMany({
          where: { clientId },
          include: {
            _count: {
              select: {
                sales: {
                  where: dateFilters,
                },
              },
            },
          },
          orderBy: { name: 'asc' },
        }),
        prisma.customer.aggregate({
          where: {
            clientId,
            sales: {
              some: dateFilters,
            },
          },
          _sum: {
            sales: {
              _sum: {
                totalAmount: true,
              },
            },
          },
        }),
      ]);

      // Calculate customer statistics
      const customersWithSales = customers.filter(customer => customer._count.sales > 0);
      const totalCustomers = customers.length;
      const activeCustomers = customers.filter(customer => customer.isActive).length;

      res.json({
        success: true,
        data: {
          period: startDate && endDate ? `${startDate} to ${endDate}` : 'All time',
          totalCustomers,
          activeCustomers,
          customersWithSales: customersWithSales.length,
          customers: customers.map(customer => ({
            id: customer.id,
            name: customer.name,
            email: customer.email,
            phone: customer.phone,
            buyerBusinessName: customer.buyerBusinessName,
            buyerProvince: customer.buyerProvince,
            buyerNTNCNIC: customer.buyerNTNCNIC,
            isActive: customer.isActive,
            salesCount: customer._count.sales,
            createdAt: customer.createdAt,
          })),
        },
      });
    } catch (error) {
      next(error);
    }
  }
}