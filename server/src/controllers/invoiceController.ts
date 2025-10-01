import { Request, Response, NextFunction } from 'express';
import { PrismaClient } from '@prisma/client';
import { AppError } from '../types';
import { InvoiceService } from '../services/invoiceService';
import { FBRService } from '../services/fbrService';
import { encryptionService } from '../utils/encryption';
import QRCode from 'qrcode';
import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';

const prisma = new PrismaClient();

export class InvoiceController {
  // Validate invoice with FBR
  static async validateInvoice(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      const client = await prisma.client.findUnique({
        where: { id: clientId },
      });

      if (!client || !client.fbrToken) {
        throw new AppError('FBR API token not configured for this client', 400);
      }

      const fbrToken = encryptionService.decrypt(client.fbrToken);
      const fbrService = new FBRService(fbrToken, client.fbrBaseUrl);
      const invoiceService = new InvoiceService(prisma, fbrService);

      const result = await invoiceService.validateInvoice(id, clientId);

      res.json({
        success: true,
        data: result,
        message: result.success ? 'Invoice validated successfully' : 'Invoice validation failed',
      });
    } catch (error) {
      next(error);
    }
  }

  // Submit invoice to FBR
  static async submitInvoice(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      const client = await prisma.client.findUnique({
        where: { id: clientId },
      });

      if (!client || !client.fbrToken) {
        throw new AppError('FBR API token not configured for this client', 400);
      }

      const fbrToken = encryptionService.decrypt(client.fbrToken);
      const fbrService = new FBRService(fbrToken, client.fbrBaseUrl);
      const invoiceService = new InvoiceService(prisma, fbrService);

      const result = await invoiceService.submitInvoice(id, clientId);

      res.json({
        success: true,
        data: result,
        message: result.success ? 'Invoice submitted successfully' : 'Invoice submission failed',
      });
    } catch (error) {
      next(error);
    }
  }

  // Get FBR reference data
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
      const invoiceService = new InvoiceService(prisma, fbrService);

      const data = await invoiceService.getReferenceData(type, clientId);

      res.json({
        success: true,
        data,
      });
    } catch (error) {
      next(error);
    }
  }

  // Generate invoice PDF
  static async generateInvoicePDF(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const { id } = req.params;
      const clientId = (req as any).client.id;

      const sale = await prisma.sale.findFirst({
        where: { id, clientId },
        include: {
          client: true,
          customer: true,
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
      });

      if (!sale) {
        throw new AppError('Sale not found', 404);
      }

      // Generate QR code if FBR invoice number exists
      let qrCodeDataUrl = '';
      if (sale.fbrInvoiceNumber) {
        try {
          qrCodeDataUrl = await QRCode.toDataURL(sale.fbrInvoiceNumber, {
            width: 100,
            margin: 2,
            color: {
              dark: '#000000',
              light: '#FFFFFF',
            },
          });
        } catch (error) {
          console.error('QR Code generation failed:', error);
        }
      }

      // Create HTML for invoice
      const invoiceHTML = `
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>Invoice ${sale.invoiceNumber}</title>
          <style>
            body {
              font-family: Arial, sans-serif;
              margin: 0;
              padding: 20px;
              color: #333;
            }
            .header {
              display: flex;
              justify-content: space-between;
              align-items: flex-start;
              margin-bottom: 30px;
              border-bottom: 2px solid #333;
              padding-bottom: 20px;
            }
            .company-info h1 {
              margin: 0;
              color: #2c3e50;
              font-size: 24px;
            }
            .company-info p {
              margin: 5px 0;
              color: #666;
            }
            .invoice-info {
              text-align: right;
            }
            .invoice-info h2 {
              margin: 0;
              color: #e74c3c;
              font-size: 20px;
            }
            .invoice-info p {
              margin: 5px 0;
              color: #666;
            }
            .customer-info {
              margin-bottom: 30px;
            }
            .customer-info h3 {
              margin: 0 0 10px 0;
              color: #2c3e50;
            }
            .customer-info p {
              margin: 2px 0;
              color: #666;
            }
            .items-table {
              width: 100%;
              border-collapse: collapse;
              margin-bottom: 30px;
            }
            .items-table th,
            .items-table td {
              border: 1px solid #ddd;
              padding: 12px;
              text-align: left;
            }
            .items-table th {
              background-color: #f8f9fa;
              font-weight: bold;
              color: #2c3e50;
            }
            .items-table tr:nth-child(even) {
              background-color: #f8f9fa;
            }
            .totals {
              display: flex;
              justify-content: flex-end;
              margin-bottom: 30px;
            }
            .totals-table {
              width: 300px;
              border-collapse: collapse;
            }
            .totals-table td {
              padding: 8px 12px;
              border: 1px solid #ddd;
            }
            .totals-table .label {
              background-color: #f8f9fa;
              font-weight: bold;
            }
            .totals-table .total {
              background-color: #e74c3c;
              color: white;
              font-weight: bold;
              font-size: 16px;
            }
            .footer {
              display: flex;
              justify-content: space-between;
              align-items: flex-start;
              margin-top: 50px;
              padding-top: 20px;
              border-top: 1px solid #ddd;
            }
            .qr-code {
              text-align: center;
            }
            .qr-code img {
              border: 1px solid #ddd;
              padding: 10px;
            }
            .fbr-logo {
              text-align: center;
              margin-bottom: 20px;
            }
            .fbr-logo h3 {
              color: #2c3e50;
              margin: 0;
            }
            @media print {
              body { margin: 0; }
              .no-print { display: none; }
            }
          </style>
        </head>
        <body>
          <div class="fbr-logo">
            <h3>FBR Digital Invoicing System</h3>
          </div>
          
          <div class="header">
            <div class="company-info">
              <h1>${sale.client.businessName}</h1>
              <p>${sale.client.businessAddress}</p>
              <p>${sale.client.businessProvince}</p>
              <p>Phone: ${sale.client.phone || 'N/A'}</p>
              <p>Email: ${sale.client.email}</p>
            </div>
            <div class="invoice-info">
              <h2>INVOICE</h2>
              <p><strong>Invoice #:</strong> ${sale.invoiceNumber}</p>
              <p><strong>Date:</strong> ${sale.invoiceDate.toLocaleDateString()}</p>
              <p><strong>Time:</strong> ${sale.invoiceDate.toLocaleTimeString()}</p>
              ${sale.fbrInvoiceNumber ? `<p><strong>FBR Invoice #:</strong> ${sale.fbrInvoiceNumber}</p>` : ''}
              ${sale.fbrDated ? `<p><strong>FBR Date:</strong> ${sale.fbrDated.toLocaleDateString()}</p>` : ''}
            </div>
          </div>

          <div class="customer-info">
            <h3>Bill To:</h3>
            <p><strong>${sale.customer?.buyerBusinessName || sale.customer?.name || 'Walk-in Customer'}</strong></p>
            ${sale.customer?.buyerAddress ? `<p>${sale.customer.buyerAddress}</p>` : ''}
            ${sale.customer?.buyerProvince ? `<p>${sale.customer.buyerProvince}</p>` : ''}
            ${sale.customer?.buyerNTNCNIC ? `<p>NTN/CNIC: ${sale.customer.buyerNTNCNIC}</p>` : ''}
            ${sale.customer?.phone ? `<p>Phone: ${sale.customer.phone}</p>` : ''}
          </div>

          <table class="items-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              ${sale.saleItems.map(item => `
                <tr>
                  <td>${item.product?.sku || 'N/A'}</td>
                  <td>${item.productDescription}</td>
                  <td>${item.quantity}</td>
                  <td>PKR ${item.unitPrice.toFixed(2)}</td>
                  <td>PKR ${item.totalPrice.toFixed(2)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>

          <div class="totals">
            <table class="totals-table">
              <tr>
                <td class="label">Subtotal:</td>
                <td>PKR ${sale.subtotal.toFixed(2)}</td>
              </tr>
              <tr>
                <td class="label">Tax:</td>
                <td>PKR ${sale.taxAmount.toFixed(2)}</td>
              </tr>
              ${sale.discountAmount > 0 ? `
                <tr>
                  <td class="label">Discount:</td>
                  <td>-PKR ${sale.discountAmount.toFixed(2)}</td>
                </tr>
              ` : ''}
              <tr>
                <td class="label total">Total:</td>
                <td class="total">PKR ${sale.totalAmount.toFixed(2)}</td>
              </tr>
            </table>
          </div>

          <div class="footer">
            <div>
              <p><strong>Payment Method:</strong> ${sale.paymentMethod}</p>
              <p><strong>Cashier:</strong> ${sale.user.firstName} ${sale.user.lastName}</p>
              ${sale.notes ? `<p><strong>Notes:</strong> ${sale.notes}</p>` : ''}
            </div>
            ${qrCodeDataUrl ? `
              <div class="qr-code">
                <p><strong>FBR QR Code</strong></p>
                <img src="${qrCodeDataUrl}" alt="FBR QR Code" />
              </div>
            ` : ''}
          </div>
        </body>
        </html>
      `;

      // Set response headers for PDF
      res.setHeader('Content-Type', 'application/pdf');
      res.setHeader('Content-Disposition', `attachment; filename="invoice-${sale.invoiceNumber}.pdf"`);

      // For now, return the HTML (in a real implementation, you'd convert to PDF)
      res.send(invoiceHTML);
    } catch (error) {
      next(error);
    }
  }

  // Get FBR submission logs
  static async getFBRLogs(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;
      const { page = 1, limit = 10, success, endpoint } = req.query as any;

      const filters: any = { clientId };

      if (success !== undefined) {
        filters.success = success === 'true';
      }

      if (endpoint) {
        filters.endpoint = { contains: endpoint, mode: 'insensitive' };
      }

      const skip = (parseInt(page) - 1) * parseInt(limit);

      const [logs, total] = await Promise.all([
        prisma.fBRLog.findMany({
          where: filters,
          orderBy: { createdAt: 'desc' },
          skip,
          take: parseInt(limit),
        }),
        prisma.fBRLog.count({ where: filters }),
      ]);

      res.json({
        success: true,
        data: logs,
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

  // Get invoice statistics
  static async getInvoiceStats(req: Request, res: Response, next: NextFunction): Promise<void> {
    try {
      const clientId = (req as any).client.id;

      const [
        totalInvoices,
        fbrSubmitted,
        fbrPending,
        fbrError,
        fbrSuccessRate,
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
        prisma.sale.aggregate({
          where: {
            clientId,
            fbrStatus: { in: ['SUBMITTED', 'ERROR'] },
          },
          _count: { fbrStatus: true },
        }),
      ]);

      const totalFbrAttempts = fbrSubmitted + fbrError;
      const successRate = totalFbrAttempts > 0 ? (fbrSubmitted / totalFbrAttempts) * 100 : 0;

      res.json({
        success: true,
        data: {
          totalInvoices,
          fbrSubmitted,
          fbrPending,
          fbrError,
          fbrSuccessRate: Math.round(successRate * 100) / 100,
        },
      });
    } catch (error) {
      next(error);
    }
  }
}