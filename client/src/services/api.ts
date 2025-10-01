import axios, { AxiosInstance, AxiosResponse, AxiosError } from 'axios';
import toast from 'react-hot-toast';
import { ApiResponse } from '@/types';

class ApiService {
  private api: AxiosInstance;

  constructor() {
    this.api = axios.create({
      baseURL: '/api',
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    this.setupInterceptors();
  }

  private setupInterceptors() {
    // Request interceptor
    this.api.interceptors.request.use(
      (config) => {
        const token = localStorage.getItem('token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
      },
      (error) => {
        return Promise.reject(error);
      }
    );

    // Response interceptor
    this.api.interceptors.response.use(
      (response: AxiosResponse) => {
        return response;
      },
      (error: AxiosError) => {
        if (error.response?.status === 401) {
          // Unauthorized - clear token and redirect to login
          localStorage.removeItem('token');
          localStorage.removeItem('user');
          localStorage.removeItem('client');
          window.location.href = '/login';
          return Promise.reject(error);
        }

        if (error.response?.status === 403) {
          toast.error('You do not have permission to perform this action');
          return Promise.reject(error);
        }

        if (error.response?.status >= 500) {
          toast.error('Server error. Please try again later.');
          return Promise.reject(error);
        }

        // Handle validation errors
        if (error.response?.data && typeof error.response.data === 'object') {
          const errorData = error.response.data as any;
          if (errorData.errors && Array.isArray(errorData.errors)) {
            errorData.errors.forEach((err: string) => {
              toast.error(err);
            });
          } else if (errorData.error) {
            toast.error(errorData.error);
          }
        }

        return Promise.reject(error);
      }
    );
  }

  // Generic request methods
  async get<T = any>(url: string, params?: any): Promise<ApiResponse<T>> {
    const response = await this.api.get(url, { params });
    return response.data;
  }

  async post<T = any>(url: string, data?: any): Promise<ApiResponse<T>> {
    const response = await this.api.post(url, data);
    return response.data;
  }

  async put<T = any>(url: string, data?: any): Promise<ApiResponse<T>> {
    const response = await this.api.put(url, data);
    return response.data;
  }

  async delete<T = any>(url: string): Promise<ApiResponse<T>> {
    const response = await this.api.delete(url);
    return response.data;
  }

  // Auth endpoints
  async login(email: string, password: string) {
    return this.post('/auth/login', { email, password });
  }

  async register(data: any) {
    return this.post('/auth/register', data);
  }

  async getProfile() {
    return this.get('/auth/profile');
  }

  async updateProfile(data: any) {
    return this.put('/auth/profile', data);
  }

  async updateClientSettings(data: any) {
    return this.put('/auth/client-settings', data);
  }

  async testFBRConnection() {
    return this.get('/auth/test-fbr-connection');
  }

  // Product endpoints
  async getProducts(params?: any) {
    return this.get('/products', params);
  }

  async getProduct(id: string) {
    return this.get(`/products/${id}`);
  }

  async createProduct(data: any) {
    return this.post('/products', data);
  }

  async updateProduct(id: string, data: any) {
    return this.put(`/products/${id}`, data);
  }

  async deleteProduct(id: string) {
    return this.delete(`/products/${id}`);
  }

  async getLowStockProducts() {
    return this.get('/products/low-stock');
  }

  async updateProductStock(id: string, data: any) {
    return this.put(`/products/${id}/stock`, data);
  }

  async getFBRReferenceData(type: string) {
    return this.get(`/products/fbr-reference/${type}`);
  }

  // Customer endpoints
  async getCustomers(params?: any) {
    return this.get('/customers', params);
  }

  async getCustomer(id: string) {
    return this.get(`/customers/${id}`);
  }

  async createCustomer(data: any) {
    return this.post('/customers', data);
  }

  async updateCustomer(id: string, data: any) {
    return this.put(`/customers/${id}`, data);
  }

  async deleteCustomer(id: string) {
    return this.delete(`/customers/${id}`);
  }

  async searchCustomers(query: string) {
    return this.get('/customers/search', { q: query });
  }

  async getCustomerStats() {
    return this.get('/customers/stats');
  }

  async getFBRProvinces() {
    return this.get('/customers/fbr-provinces');
  }

  // Sale endpoints
  async getSales(params?: any) {
    return this.get('/sales', params);
  }

  async getSale(id: string) {
    return this.get(`/sales/${id}`);
  }

  async createSale(data: any) {
    return this.post('/sales', data);
  }

  async holdSale(id: string) {
    return this.put(`/sales/${id}/hold`);
  }

  async resumeSale(id: string) {
    return this.put(`/sales/${id}/resume`);
  }

  async getHeldSales() {
    return this.get('/sales/held');
  }

  async processReturn(id: string, data: any) {
    return this.post(`/sales/${id}/return`, data);
  }

  async getSaleStats(period?: string) {
    return this.get('/sales/stats', { period });
  }

  // Invoice endpoints
  async validateInvoice(id: string) {
    return this.post(`/invoices/${id}/validate`);
  }

  async submitInvoice(id: string) {
    return this.post(`/invoices/${id}/submit`);
  }

  async getFBRReferenceData(type: string) {
    return this.get(`/invoices/fbr-reference/${type}`);
  }

  async generateInvoicePDF(id: string) {
    return this.get(`/invoices/${id}/pdf`, { responseType: 'blob' });
  }

  async getFBRLogs(params?: any) {
    return this.get('/invoices/fbr-logs', params);
  }

  async getInvoiceStats() {
    return this.get('/invoices/stats');
  }

  // Dashboard endpoints
  async getDashboardStats() {
    return this.get('/dashboard/stats');
  }

  async getSalesChartData(period?: string) {
    return this.get('/dashboard/sales-chart', { period });
  }

  async getTopProducts(limit?: number, period?: string) {
    return this.get('/dashboard/top-products', { limit, period });
  }

  async getRecentActivities(limit?: number) {
    return this.get('/dashboard/recent-activities', { limit });
  }

  async getFBRStatusSummary() {
    return this.get('/dashboard/fbr-status');
  }

  // Report endpoints
  async generateSalesReport(params: any) {
    return this.get('/reports/sales', params);
  }

  async generateTaxReport(params: any) {
    return this.get('/reports/tax', params);
  }

  async generateFBRComplianceReport(params: any) {
    return this.get('/reports/fbr-compliance', params);
  }

  async generateInventoryReport(params?: any) {
    return this.get('/reports/inventory', params);
  }

  async generateCustomerReport(params: any) {
    return this.get('/reports/customers', params);
  }
}

export const apiService = new ApiService();
export default apiService;