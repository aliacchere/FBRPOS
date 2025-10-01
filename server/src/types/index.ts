import { Request } from 'express';
import { User, Client, FBRStatus, PaymentMethod, PaymentStatus, UserRole } from '@prisma/client';

// Extended Request interface with user and client
export interface AuthenticatedRequest extends Request {
  user?: User;
  client?: Client;
}

// FBR API Types
export interface FBRInvoiceHeader {
  invoiceType: string;
  invoiceDate: string;
  sellerBusinessName: string;
  sellerAddress: string;
  sellerProvince: string;
  buyerNTNCNIC: string;
  buyerBusinessName: string;
  buyerProvince: string;
  buyerAddress: string;
  invoiceRefNo?: string;
}

export interface FBRInvoiceItem {
  hsCode: string;
  productDescription: string;
  rate: string;
  uom: string;
  quantity: number;
  totalValues: number;
  valueSalesExcludingST: number;
  salesTaxApplicable: boolean;
  salesTaxWithheldAtSource: number;
  extraTax: number;
  furtherTax: number;
  sroScheduleNo?: string;
  fedPayable: number;
  discount: number;
  saleType: string;
  sroItemSerialNo?: string;
}

export interface FBRInvoiceData {
  invoiceHeader: FBRInvoiceHeader;
  invoiceItems: FBRInvoiceItem[];
}

export interface FBRValidationResponse {
  success: boolean;
  errors?: string[];
  invoiceStatuses?: Array<{
    remarks: string;
    status: string;
  }>;
}

export interface FBRSubmissionResponse {
  success: boolean;
  invoiceNumber?: string;
  dated?: string;
  errors?: string[];
  invoiceStatuses?: Array<{
    remarks: string;
    status: string;
  }>;
}

// API Response Types
export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
  errors?: string[];
}

// Pagination
export interface PaginationParams {
  page?: number;
  limit?: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

export interface PaginatedResponse<T> {
  data: T[];
  pagination: {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
  };
}

// Product Types
export interface ProductFilters {
  search?: string;
  categoryId?: string;
  lowStock?: boolean;
  isActive?: boolean;
}

// Sale Types
export interface SaleItemInput {
  productId?: string;
  productName: string;
  quantity: number;
  unitPrice: number;
  hsCode?: string;
  uom?: string;
  taxRate?: number;
  sroScheduleNo?: string;
  sroItemSerialNo?: string;
}

export interface CreateSaleInput {
  customerId?: string;
  items: SaleItemInput[];
  paymentMethod: PaymentMethod;
  notes?: string;
  discountAmount?: number;
}

// Customer Types
export interface CustomerFilters {
  search?: string;
  province?: string;
  isActive?: boolean;
}

// FBR Reference Data Types
export interface FBRProvince {
  id: string;
  name: string;
  code: string;
}

export interface FBRDocType {
  id: string;
  name: string;
  code: string;
}

export interface FBRHSCode {
  id: string;
  code: string;
  description: string;
}

export interface FBRUOM {
  id: string;
  code: string;
  description: string;
}

export interface FBRSROItem {
  id: string;
  code: string;
  description: string;
  scheduleNo: string;
}

export interface FBRSaleTypeRate {
  rate: string;
  rateValue: number;
  transTypeId: string;
  originationSupplier: string;
}

// Dashboard Types
export interface DashboardStats {
  todaySales: {
    count: number;
    amount: number;
  };
  monthlySales: {
    count: number;
    amount: number;
  };
  lowStockProducts: number;
  pendingFBRSubmissions: number;
  recentSales: Array<{
    id: string;
    invoiceNumber: string;
    customerName?: string;
    totalAmount: number;
    createdAt: Date;
  }>;
  salesChart: Array<{
    date: string;
    amount: number;
  }>;
}

// Report Types
export interface SalesReportFilters {
  startDate: string;
  endDate: string;
  customerId?: string;
  productId?: string;
  paymentMethod?: PaymentMethod;
  fbrStatus?: FBRStatus;
}

export interface TaxReportData {
  period: string;
  totalSales: number;
  totalTax: number;
  salesTaxApplicable: number;
  furtherTax: number;
  extraTax: number;
  salesTaxWithheldAtSource: number;
  fedPayable: number;
  invoices: Array<{
    invoiceNumber: string;
    date: string;
    totalAmount: number;
    taxAmount: number;
    fbrStatus: FBRStatus;
  }>;
}

// Error Types
export class AppError extends Error {
  public statusCode: number;
  public isOperational: boolean;

  constructor(message: string, statusCode: number = 500, isOperational: boolean = true) {
    super(message);
    this.statusCode = statusCode;
    this.isOperational = isOperational;

    Error.captureStackTrace(this, this.constructor);
  }
}

// Utility Types
export type DeepPartial<T> = {
  [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P];
};

export type Optional<T, K extends keyof T> = Omit<T, K> & Partial<Pick<T, K>>;

export type RequiredFields<T, K extends keyof T> = T & Required<Pick<T, K>>;