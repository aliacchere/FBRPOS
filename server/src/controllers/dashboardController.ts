import { Request, Response, NextFunction } from 'express';
import { PrismaClient } from '@prisma/client';
import { AppError, DashboardStats } from '../types';

const prisma = new PrismaClient();

export class DashboardController {
  // Get dashboard statistics
  static async getDashboardStats(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;

      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);

      const [
        todaySales,
        monthlySales,
        lowStockProducts,
        pendingFBRSubmissions,
        recentSales,
        salesChartData,
      ] = await Promise.all([
        // Today's sales
        prisma.sale.aggregate({
          where: {
            clientId,
            createdAt: { gte: today },
          },
          _count: { id: true },
          _sum: { totalAmount: true },
        }),

        // Monthly sales
        prisma.sale.aggregate({
          where: {
            clientId,
            createdAt: { gte: monthStart },
          },
          _count: { id: true },
          _sum: { totalAmount: true },
        }),

        // Low stock products
        prisma.product.count({
          where: {
            clientId,
            isActive: true,
            currentStock: {
              lte: await this.getLowStockThreshold(clientId),
            },
          },
        }),

        // Pending FBR submissions
        prisma.sale.count({
          where: {
            clientId,
            fbrStatus: 'PENDING',
          },
        }),

        // Recent sales
        prisma.sale.findMany({
          where: { clientId },
          include: {
            customer: {
              select: {
                name: true,
                buyerBusinessName: true,
              },
            },
          },
          orderBy: { createdAt: 'desc' },
          take: 5,
        }),

        // Sales chart data (last 7 days)
        this.getSalesChartData(clientId),
      ]);

      const stats: DashboardStats = {
        todaySales: {
          count: todaySales._count.id,
          amount: todaySales._sum.totalAmount || 0,
        },
        monthlySales: {
          count: monthlySales._count.id,
          amount: monthlySales._sum.totalAmount || 0,
        },
        lowStockProducts,
        pendingFBRSubmissions,
        recentSales: recentSales.map(sale => ({
          id: sale.id,
          invoiceNumber: sale.invoiceNumber,
          customerName: sale.customer?.buyerBusinessName || sale.customer?.name,
          totalAmount: sale.totalAmount,
          createdAt: sale.createdAt,
        })),
        salesChart: salesChartData,
      };

      res.json({
        success: true,
        data: stats,
      });
    } catch (error) {
      next(error);
    }
  }

  // Get sales chart data
  static async getSalesChartData(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { period = '7d' } = req.query;

      const data = await this.getSalesChartData(clientId, period as string);

      res.json({
        success: true,
        data,
      });
    } catch (error) {
      next(error);
    }
  }

  // Get top products
  static async getTopProducts(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { limit = 10, period = '30d' } = req.query;

      const startDate = this.getStartDateForPeriod(period as string);

      const topProducts = await prisma.saleItem.groupBy({
        by: ['productId'],
        where: {
          sale: {
            clientId,
            createdAt: { gte: startDate },
          },
        },
        _sum: {
          quantity: true,
          totalPrice: true,
        },
        _count: {
          id: true,
        },
        orderBy: {
          _sum: {
            totalPrice: 'desc',
          },
        },
        take: parseInt(limit as string),
      });

      // Get product details
      const productIds = topProducts.map(item => item.productId).filter(Boolean);
      const products = await prisma.product.findMany({
        where: {
          id: { in: productIds },
          clientId,
        },
        select: {
          id: true,
          name: true,
          sku: true,
        },
      });

      const result = topProducts.map(item => {
        const product = products.find(p => p.id === item.productId);
        return {
          productId: item.productId,
          productName: product?.name || 'Unknown Product',
          productSku: product?.sku || 'N/A',
          totalQuantity: item._sum.quantity || 0,
          totalRevenue: item._sum.totalPrice || 0,
          salesCount: item._count.id,
        };
      });

      res.json({
        success: true,
        data: result,
      });
    } catch (error) {
      next(error);
    }
  }

  // Get recent activities
  static async getRecentActivities(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { limit = 20 } = req.query;

      // Get recent sales
      const recentSales = await prisma.sale.findMany({
        where: { clientId },
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
        },
        orderBy: { createdAt: 'desc' },
        take: parseInt(limit as string),
      });

      const activities = recentSales.map(sale => ({
        id: sale.id,
        type: 'sale',
        description: `Sale ${sale.invoiceNumber} created`,
        customer: sale.customer?.buyerBusinessName || sale.customer?.name || 'Walk-in Customer',
        user: `${sale.user.firstName} ${sale.user.lastName}`,
        amount: sale.totalAmount,
        timestamp: sale.createdAt,
        fbrStatus: sale.fbrStatus,
      }));

      res.json({
        success: true,
        data: activities,
      });
    } catch (error) {
      next(error);
    }
  }

  // Get FBR status summary
  static async getFBRStatusSummary(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;

      const [
        totalInvoices,
        fbrSubmitted,
        fbrPending,
        fbrError,
        fbrApproved,
        fbrRejected,
      ] = await Promise.all([
        prisma.sale.count({
          where: { clientId },
        }),
        prisma.sale.count({
          where: { clientId, fbrStatus: 'SUBMITTED' },
        }),
        prisma.sale.count({
          where: { clientId, fbrStatus: 'PENDING' },
        }),
        prisma.sale.count({
          where: { clientId, fbrStatus: 'ERROR' },
        }),
        prisma.sale.count({
          where: { clientId, fbrStatus: 'APPROVED' },
        }),
        prisma.sale.count({
          where: { clientId, fbrStatus: 'REJECTED' },
        }),
      ]);

      const fbrComplianceRate = totalInvoices > 0 ? 
        ((fbrSubmitted + fbrApproved) / totalInvoices) * 100 : 0;

      res.json({
        success: true,
        data: {
          totalInvoices,
          fbrSubmitted,
          fbrPending,
          fbrError,
          fbrApproved,
          fbrRejected,
          fbrComplianceRate: Math.round(fbrComplianceRate * 100) / 100,
        },
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

  // Helper method to get sales chart data
  private static async getSalesChartData(clientId: string, period: string = '7d'): Promise<any[]> {
    const startDate = this.getStartDateForPeriod(period);
    const endDate = new Date();

    // Generate date range
    const dates = [];
    const currentDate = new Date(startDate);
    
    while (currentDate <= endDate) {
      dates.push(new Date(currentDate));
      currentDate.setDate(currentDate.getDate() + 1);
    }

    // Get sales data for each date
    const salesData = await Promise.all(
      dates.map(async (date) => {
        const nextDate = new Date(date);
        nextDate.setDate(nextDate.getDate() + 1);

        const sales = await prisma.sale.aggregate({
          where: {
            clientId,
            createdAt: {
              gte: date,
              lt: nextDate,
            },
          },
          _sum: { totalAmount: true },
        });

        return {
          date: date.toISOString().split('T')[0],
          amount: sales._sum.totalAmount || 0,
        };
      })
    );

    return salesData;
  }

  // Helper method to get start date for period
  private static getStartDateForPeriod(period: string): Date {
    const now = new Date();
    const startDate = new Date(now);

    switch (period) {
      case '1d':
        startDate.setDate(now.getDate() - 1);
        break;
      case '7d':
        startDate.setDate(now.getDate() - 7);
        break;
      case '30d':
        startDate.setDate(now.getDate() - 30);
        break;
      case '90d':
        startDate.setDate(now.getDate() - 90);
        break;
      case '1y':
        startDate.setFullYear(now.getFullYear() - 1);
        break;
      default:
        startDate.setDate(now.getDate() - 7);
    }

    startDate.setHours(0, 0, 0, 0);
    return startDate;
  }
}