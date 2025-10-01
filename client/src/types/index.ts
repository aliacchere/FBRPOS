// User and Authentication Types
export interface User {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  role: 'ADMIN' | 'MANAGER' | 'CASHIER';
}

export interface Client {
  id: string;
  name: string;
  businessName: string;
  businessAddress: string;
  businessProvince: string;
  hasFbrToken: boolean;
  fbrBaseUrl?: string;
}

export interface AuthState {
  user: User | null;
  client: Client | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

// API Response Types
export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
  errors?: string[];
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
export interface Product {
  id: string;
  name: string;
  sku: string;
  description?: string;
  categoryId?: string;
  category?: Category;
  purchasePrice: number;
  sellingPrice: number;
  currentStock: number;
  minStockLevel: number;
  maxStockLevel?: number;
  hsCode?: string;
  uom?: string;
  taxRate: number;
  sroScheduleNo?: string;
  sroItemSerialNo?: string;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface Category {
  id: string;
  name: string;
  description?: string;
  createdAt: string;
  updatedAt: string;
}

// Customer Types
export interface Customer {
  id: string;
  name: string;
  email?: string;
  phone?: string;
  address?: string;
  buyerNTNCNIC?: string;
  buyerBusinessName?: string;
  buyerProvince?: string;
  buyerAddress?: string;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

// Sale Types
export interface SaleItem {
  id: string;
  quantity: number;
  unitPrice: number;
  totalPrice: number;
  hsCode?: string;
  productDescription: string;
  rate?: string;
  uom?: string;
  totalValues: number;
  valueSalesExcludingST: number;
  salesTaxApplicable: boolean;
  salesTaxWithheldAtSource: number;
  extraTax: number;
  furtherTax: number;
  sroScheduleNo?: string;
  fedPayable: number;
  discount: number;
  saleType?: string;
  sroItemSerialNo?: string;
  productId?: string;
  product?: {
    id: string;
    name: string;
    sku: string;
  };
}

export interface Sale {
  id: string;
  invoiceNumber: string;
  invoiceType: 'SALE' | 'DEBIT' | 'CREDIT';
  invoiceDate: string;
  subtotal: number;
  taxAmount: number;
  discountAmount: number;
  totalAmount: number;
  paymentMethod: 'CASH' | 'CARD' | 'BANK_TRANSFER' | 'MOBILE_WALLET';
  paymentStatus: 'PENDING' | 'COMPLETED' | 'FAILED' | 'REFUNDED';
  notes?: string;
  fbrInvoiceNumber?: string;
  fbrDated?: string;
  fbrStatus: 'PENDING' | 'VALIDATED' | 'SUBMITTED' | 'APPROVED' | 'REJECTED' | 'ERROR';
  fbrError?: string;
  isHeld: boolean;
  heldAt?: string;
  customerId?: string;
  customer?: Customer;
  userId: string;
  user: {
    id: string;
    firstName: string;
    lastName: string;
  };
  saleItems: SaleItem[];
  createdAt: string;
  updatedAt: string;
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
    createdAt: string;
  }>;
  salesChart: Array<{
    date: string;
    amount: number;
  }>;
}

// FBR Types
export interface FBRProvince {
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

export interface FBRDocType {
  id: string;
  name: string;
  code: string;
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

// Form Types
export interface CreateProductForm {
  name: string;
  sku: string;
  description?: string;
  categoryId?: string;
  purchasePrice: number;
  sellingPrice: number;
  currentStock: number;
  minStockLevel: number;
  maxStockLevel?: number;
  hsCode?: string;
  uom?: string;
  taxRate: number;
  sroScheduleNo?: string;
  sroItemSerialNo?: string;
}

export interface CreateCustomerForm {
  name: string;
  email?: string;
  phone?: string;
  address?: string;
  buyerNTNCNIC?: string;
  buyerBusinessName?: string;
  buyerProvince?: string;
  buyerAddress?: string;
}

export interface CreateSaleForm {
  customerId?: string;
  items: Array<{
    productId?: string;
    productName: string;
    quantity: number;
    unitPrice: number;
    hsCode?: string;
    uom?: string;
    taxRate?: number;
    sroScheduleNo?: string;
    sroItemSerialNo?: string;
  }>;
  paymentMethod: 'CASH' | 'CARD' | 'BANK_TRANSFER' | 'MOBILE_WALLET';
  notes?: string;
  discountAmount?: number;
}

// Filter Types
export interface ProductFilters {
  search?: string;
  categoryId?: string;
  lowStock?: boolean;
  isActive?: boolean;
  page?: number;
  limit?: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

export interface CustomerFilters {
  search?: string;
  province?: string;
  isActive?: boolean;
  page?: number;
  limit?: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

export interface SaleFilters {
  startDate?: string;
  endDate?: string;
  customerId?: string;
  productId?: string;
  paymentMethod?: string;
  fbrStatus?: string;
  page?: number;
  limit?: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

// Report Types
export interface SalesReportData {
  summary: {
    totalSales: number;
    totalSubtotal: number;
    totalTax: number;
    totalDiscount: number;
    totalAmount: number;
  };
  sales: Sale[];
  filters: {
    startDate: string;
    endDate: string;
    customerId?: string;
    productId?: string;
    paymentMethod?: string;
    fbrStatus?: string;
  };
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
    fbrStatus: string;
  }>;
}

export interface FBRComplianceReportData {
  period: string;
  totalInvoices: number;
  submittedToFBR: number;
  fbrSuccess: number;
  fbrErrors: number;
  pendingFBR: number;
  complianceRate: number;
  successRate: number;
  sales: Array<{
    invoiceNumber: string;
    date: string;
    customer: string;
    totalAmount: number;
    fbrStatus: string;
    fbrInvoiceNumber?: string;
    fbrDated?: string;
    fbrError?: string;
  }>;
  fbrLogs: Array<{
    id: string;
    endpoint: string;
    statusCode: number;
    success: boolean;
    error?: string;
    createdAt: string;
  }>;
}

export interface InventoryReportData {
  totalProducts: number;
  totalInventoryValue: number;
  lowStockProducts: number;
  products: Array<{
    id: string;
    name: string;
    sku: string;
    category: string;
    currentStock: number;
    minStockLevel: number;
    maxStockLevel?: number;
    purchasePrice: number;
    sellingPrice: number;
    inventoryValue: number;
    salesCount: number;
    isLowStock: boolean;
    hsCode?: string;
    uom?: string;
    taxRate: number;
  }>;
}

export interface CustomerReportData {
  period: string;
  totalCustomers: number;
  activeCustomers: number;
  customersWithSales: number;
  customers: Array<{
    id: string;
    name: string;
    email?: string;
    phone?: string;
    buyerBusinessName?: string;
    buyerProvince?: string;
    buyerNTNCNIC?: string;
    isActive: boolean;
    salesCount: number;
    createdAt: string;
  }>;
}

// UI State Types
export interface LoadingState {
  [key: string]: boolean;
}

export interface ErrorState {
  [key: string]: string | null;
}

// Component Props Types
export interface TableColumn<T> {
  key: keyof T | string;
  title: string;
  dataIndex?: keyof T;
  render?: (value: any, record: T, index: number) => React.ReactNode;
  sorter?: boolean;
  width?: string | number;
  align?: 'left' | 'center' | 'right';
}

export interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title?: string;
  children: React.ReactNode;
  size?: 'sm' | 'md' | 'lg' | 'xl';
}

export interface ConfirmDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  type?: 'info' | 'warning' | 'error' | 'success';
}