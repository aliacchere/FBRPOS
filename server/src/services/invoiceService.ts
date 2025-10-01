import { PrismaClient } from '@prisma/client';
import { FBRService, FBRReferenceDataService } from './fbrService';
import { 
  FBRInvoiceData, 
  FBRInvoiceHeader, 
  FBRInvoiceItem,
  CreateSaleInput,
  SaleItemInput,
  AppError
} from '../types';
import { encryptionService } from '../utils/encryption';
import logger from '../utils/logger';

export class InvoiceService {
  private prisma: PrismaClient;
  private fbrService: FBRService;
  private referenceDataService: FBRReferenceDataService;

  constructor(prisma: PrismaClient, fbrService: FBRService) {
    this.prisma = prisma;
    this.fbrService = fbrService;
    this.referenceDataService = new FBRReferenceDataService(fbrService);
  }

  // Generate FBR-compliant invoice data
  async generateFBRInvoiceData(saleId: string, clientId: string): Promise<FBRInvoiceData> {
    const sale = await this.prisma.sale.findFirst({
      where: { id: saleId, clientId },
      include: {
        client: true,
        customer: true,
        saleItems: {
          include: {
            product: true,
          },
        },
      },
    });

    if (!sale) {
      throw new AppError('Sale not found', 404);
    }

    if (!sale.client.fbrToken) {
      throw new AppError('FBR API token not configured for this client', 400);
    }

    // Decrypt FBR token
    const fbrToken = encryptionService.decrypt(sale.client.fbrToken);

    // Create FBR service instance with client's token
    const clientFBRService = new FBRService(fbrToken, sale.client.fbrBaseUrl);

    // Get reference data
    const provinces = await this.referenceDataService.getProvinces();
    const hsCodes = await this.referenceDataService.getHSCodes();
    const uomCodes = await this.referenceDataService.getUOMCodes();

    // Build invoice header
    const invoiceHeader: FBRInvoiceHeader = {
      invoiceType: sale.invoiceType,
      invoiceDate: sale.invoiceDate.toISOString().split('T')[0],
      sellerBusinessName: sale.client.businessName,
      sellerAddress: sale.client.businessAddress,
      sellerProvince: sale.client.businessProvince,
      buyerNTNCNIC: sale.customer?.buyerNTNCNIC || '0000000000000',
      buyerBusinessName: sale.customer?.buyerBusinessName || 'Walk-in Customer',
      buyerProvince: sale.customer?.buyerProvince || 'Punjab',
      buyerAddress: sale.customer?.buyerAddress || 'Not Provided',
      invoiceRefNo: sale.invoiceType !== 'SALE' ? sale.invoiceNumber : undefined,
    };

    // Build invoice items
    const invoiceItems: FBRInvoiceItem[] = sale.saleItems.map(item => {
      const product = item.product;
      const taxRate = product?.taxRate || 17.0;
      const valueSalesExcludingST = item.valueSalesExcludingST || (item.totalPrice / (1 + taxRate / 100));
      const salesTaxApplicable = item.salesTaxApplicable;
      const salesTaxAmount = salesTaxApplicable ? (valueSalesExcludingST * taxRate / 100) : 0;

      return {
        hsCode: item.hsCode || product?.hsCode || '0000000000',
        productDescription: item.productDescription,
        rate: item.rate || taxRate.toString(),
        uom: item.uom || product?.uom || 'PCS',
        quantity: item.quantity,
        totalValues: item.totalPrice,
        valueSalesExcludingST: valueSalesExcludingST,
        salesTaxApplicable: salesTaxApplicable,
        salesTaxWithheldAtSource: item.salesTaxWithheldAtSource,
        extraTax: item.extraTax,
        furtherTax: item.furtherTax,
        sroScheduleNo: item.sroScheduleNo || product?.sroScheduleNo,
        fedPayable: item.fedPayable,
        discount: item.discount,
        saleType: item.saleType || 'RETAIL',
        sroItemSerialNo: item.sroItemSerialNo || product?.sroItemSerialNo,
      };
    });

    return {
      invoiceHeader,
      invoiceItems,
    };
  }

  // Validate invoice with FBR
  async validateInvoice(saleId: string, clientId: string): Promise<{ success: boolean; errors?: string[] }> {
    try {
      const invoiceData = await this.generateFBRInvoiceData(saleId, clientId);
      
      const sale = await this.prisma.sale.findFirst({
        where: { id: saleId, clientId },
      });

      if (!sale) {
        throw new AppError('Sale not found', 404);
      }

      if (!sale.client.fbrToken) {
        throw new AppError('FBR API token not configured for this client', 400);
      }

      const fbrToken = encryptionService.decrypt(sale.client.fbrToken);
      const fbrService = new FBRService(fbrToken, sale.client.fbrBaseUrl);

      const validationResult = await fbrService.validateInvoiceData(invoiceData);

      // Log validation attempt
      await this.prisma.fBRLog.create({
        data: {
          clientId,
          endpoint: 'validateinvoicedata',
          requestData: invoiceData,
          responseData: validationResult,
          statusCode: validationResult.success ? 200 : 400,
          success: validationResult.success,
          error: validationResult.errors?.join(', '),
        },
      });

      return validationResult;
    } catch (error: any) {
      logger.error('Invoice validation error', {
        saleId,
        clientId,
        error: error.message,
      });

      // Log error
      await this.prisma.fBRLog.create({
        data: {
          clientId,
          endpoint: 'validateinvoicedata',
          statusCode: error.statusCode || 500,
          success: false,
          error: error.message,
        },
      });

      throw error;
    }
  }

  // Submit invoice to FBR
  async submitInvoice(saleId: string, clientId: string): Promise<{ success: boolean; fbrInvoiceNumber?: string; fbrDated?: string; errors?: string[] }> {
    try {
      const invoiceData = await this.generateFBRInvoiceData(saleId, clientId);
      
      const sale = await this.prisma.sale.findFirst({
        where: { id: saleId, clientId },
      });

      if (!sale) {
        throw new AppError('Sale not found', 404);
      }

      if (!sale.client.fbrToken) {
        throw new AppError('FBR API token not configured for this client', 400);
      }

      const fbrToken = encryptionService.decrypt(sale.client.fbrToken);
      const fbrService = new FBRService(fbrToken, sale.client.fbrBaseUrl);

      const submissionResult = await fbrService.submitInvoiceData(invoiceData);

      // Update sale with FBR response
      if (submissionResult.success && submissionResult.invoiceNumber) {
        await this.prisma.sale.update({
          where: { id: saleId },
          data: {
            fbrInvoiceNumber: submissionResult.invoiceNumber,
            fbrDated: submissionResult.dated ? new Date(submissionResult.dated) : null,
            fbrStatus: 'SUBMITTED',
            fbrError: null,
          },
        });
      } else {
        await this.prisma.sale.update({
          where: { id: saleId },
          data: {
            fbrStatus: 'ERROR',
            fbrError: submissionResult.errors?.join(', ') || 'Unknown error',
          },
        });
      }

      // Log submission attempt
      await this.prisma.fBRLog.create({
        data: {
          clientId,
          endpoint: 'postinvoicedata',
          requestData: invoiceData,
          responseData: submissionResult,
          statusCode: submissionResult.success ? 200 : 400,
          success: submissionResult.success,
          error: submissionResult.errors?.join(', '),
        },
      });

      return submissionResult;
    } catch (error: any) {
      logger.error('Invoice submission error', {
        saleId,
        clientId,
        error: error.message,
      });

      // Update sale with error status
      await this.prisma.sale.update({
        where: { id: saleId },
        data: {
          fbrStatus: 'ERROR',
          fbrError: error.message,
        },
      });

      // Log error
      await this.prisma.fBRLog.create({
        data: {
          clientId,
          endpoint: 'postinvoicedata',
          statusCode: error.statusCode || 500,
          success: false,
          error: error.message,
        },
      });

      throw error;
    }
  }

  // Create sale with FBR-compliant data
  async createSale(input: CreateSaleInput, clientId: string, userId: string): Promise<any> {
    const client = await this.prisma.client.findUnique({
      where: { id: clientId },
      include: { settings: true },
    });

    if (!client) {
      throw new AppError('Client not found', 404);
    }

    const settings = client.settings || {
      invoicePrefix: 'INV',
      invoiceNumber: 1,
    };

    // Generate invoice number
    const invoiceNumber = `${settings.invoicePrefix}-${String(settings.invoiceNumber).padStart(6, '0')}`;

    // Calculate totals
    let subtotal = 0;
    let taxAmount = 0;
    let totalAmount = 0;

    const saleItems = input.items.map(item => {
      const itemTotal = item.quantity * item.unitPrice;
      const itemTaxRate = item.taxRate || 17.0;
      const itemTaxAmount = item.salesTaxApplicable ? (itemTotal * itemTaxRate / 100) : 0;
      const itemTotalWithTax = itemTotal + itemTaxAmount;

      subtotal += itemTotal;
      taxAmount += itemTaxAmount;
      totalAmount += itemTotalWithTax;

      return {
        quantity: item.quantity,
        unitPrice: item.unitPrice,
        totalPrice: itemTotalWithTax,
        hsCode: item.hsCode,
        productDescription: item.productName,
        rate: item.taxRate?.toString(),
        uom: item.uom,
        totalValues: itemTotalWithTax,
        valueSalesExcludingST: itemTotal,
        salesTaxApplicable: item.taxRate ? item.taxRate > 0 : true,
        salesTaxWithheldAtSource: 0,
        extraTax: 0,
        furtherTax: 0,
        sroScheduleNo: item.sroScheduleNo,
        fedPayable: itemTaxAmount,
        discount: 0,
        saleType: 'RETAIL',
        sroItemSerialNo: item.sroItemSerialNo,
        productId: item.productId,
      };
    });

    // Apply discount
    const discountAmount = input.discountAmount || 0;
    totalAmount -= discountAmount;

    // Create sale
    const sale = await this.prisma.sale.create({
      data: {
        invoiceNumber,
        invoiceType: 'SALE',
        subtotal,
        taxAmount,
        discountAmount,
        totalAmount,
        paymentMethod: input.paymentMethod,
        paymentStatus: 'COMPLETED',
        notes: input.notes,
        clientId,
        customerId: input.customerId,
        userId,
        saleItems: {
          create: saleItems,
        },
      },
      include: {
        saleItems: true,
        customer: true,
        user: true,
      },
    });

    // Update invoice number for next sale
    await this.prisma.clientSettings.upsert({
      where: { clientId },
      create: {
        clientId,
        invoiceNumber: settings.invoiceNumber + 1,
      },
      update: {
        invoiceNumber: settings.invoiceNumber + 1,
      },
    });

    // Update product stock
    for (const item of input.items) {
      if (item.productId) {
        await this.prisma.product.update({
          where: { id: item.productId },
          data: {
            currentStock: {
              decrement: item.quantity,
            },
          },
        });
      }
    }

    return sale;
  }

  // Get FBR reference data
  async getReferenceData(type: string, clientId: string): Promise<any> {
    const client = await this.prisma.client.findUnique({
      where: { id: clientId },
    });

    if (!client || !client.fbrToken) {
      throw new AppError('FBR API token not configured for this client', 400);
    }

    const fbrToken = encryptionService.decrypt(client.fbrToken);
    const fbrService = new FBRService(fbrToken, client.fbrBaseUrl);

    switch (type) {
      case 'provinces':
        return await this.referenceDataService.getProvinces();
      case 'hsCodes':
        return await this.referenceDataService.getHSCodes();
      case 'uomCodes':
        return await this.referenceDataService.getUOMCodes();
      case 'docTypeCodes':
        return await fbrService.getDocTypeCodes();
      case 'sroItemCodes':
        return await fbrService.getSROItemCodes();
      case 'transactionTypeCodes':
        return await fbrService.getTransactionTypeCodes();
      case 'sroSchedules':
        return await fbrService.getSROSchedules();
      default:
        throw new AppError('Invalid reference data type', 400);
    }
  }
}