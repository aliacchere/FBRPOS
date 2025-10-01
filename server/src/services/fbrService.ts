import axios, { AxiosInstance, AxiosResponse } from 'axios';
import { 
  FBRInvoiceData, 
  FBRValidationResponse, 
  FBRSubmissionResponse,
  FBRProvince,
  FBRDocType,
  FBRHSCode,
  FBRUOM,
  FBRSROItem,
  FBRSaleTypeRate,
  AppError
} from '../types';
import { encryptionService } from '../utils/encryption';
import logger from '../utils/logger';

export class FBRService {
  private client: AxiosInstance;
  private baseUrl: string;
  private token: string;

  constructor(token: string, baseUrl?: string) {
    this.token = token;
    this.baseUrl = baseUrl || process.env.FBR_BASE_URL || 'https://gw.fbr.gov.pk/di_data/v1/di';
    
    this.client = axios.create({
      baseURL: this.baseUrl,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    });

    // Add request interceptor for logging
    this.client.interceptors.request.use(
      (config) => {
        logger.info('FBR API Request', {
          method: config.method?.toUpperCase(),
          url: config.url,
          baseURL: config.baseURL,
        });
        return config;
      },
      (error) => {
        logger.error('FBR API Request Error', error);
        return Promise.reject(error);
      }
    );

    // Add response interceptor for logging and error handling
    this.client.interceptors.response.use(
      (response) => {
        logger.info('FBR API Response', {
          status: response.status,
          url: response.config.url,
        });
        return response;
      },
      (error) => {
        logger.error('FBR API Response Error', {
          status: error.response?.status,
          message: error.message,
          url: error.config?.url,
          data: error.response?.data,
        });
        return Promise.reject(error);
      }
    );
  }

  // Validate invoice data before submission
  async validateInvoiceData(invoiceData: FBRInvoiceData): Promise<FBRValidationResponse> {
    try {
      const response: AxiosResponse<FBRValidationResponse> = await this.client.post(
        '/validateinvoicedata',
        invoiceData
      );

      return response.data;
    } catch (error: any) {
      logger.error('FBR Validation Error', {
        error: error.message,
        response: error.response?.data,
        status: error.response?.status,
      });

      if (error.response?.status === 401) {
        throw new AppError('FBR API authentication failed. Please check your API token.', 401);
      }

      if (error.response?.status === 400) {
        const errorData = error.response.data;
        return {
          success: false,
          errors: errorData.errors || ['Validation failed'],
          invoiceStatuses: errorData.invoiceStatuses || [],
        };
      }

      throw new AppError('FBR validation service is currently unavailable', 503);
    }
  }

  // Submit invoice data to FBR
  async submitInvoiceData(invoiceData: FBRInvoiceData): Promise<FBRSubmissionResponse> {
    try {
      const response: AxiosResponse<FBRSubmissionResponse> = await this.client.post(
        '/postinvoicedata',
        invoiceData
      );

      return response.data;
    } catch (error: any) {
      logger.error('FBR Submission Error', {
        error: error.message,
        response: error.response?.data,
        status: error.response?.status,
      });

      if (error.response?.status === 401) {
        throw new AppError('FBR API authentication failed. Please check your API token.', 401);
      }

      if (error.response?.status === 400) {
        const errorData = error.response.data;
        return {
          success: false,
          errors: errorData.errors || ['Submission failed'],
          invoiceStatuses: errorData.invoiceStatuses || [],
        };
      }

      throw new AppError('FBR submission service is currently unavailable', 503);
    }
  }

  // Get provinces list
  async getProvinces(): Promise<FBRProvince[]> {
    try {
      const response = await this.client.get('/provinces');
      return response.data;
    } catch (error: any) {
      logger.error('FBR Provinces Error', error);
      throw new AppError('Failed to fetch provinces from FBR', 503);
    }
  }

  // Get document type codes
  async getDocTypeCodes(): Promise<FBRDocType[]> {
    try {
      const response = await this.client.get('/doctypecode');
      return response.data;
    } catch (error: any) {
      logger.error('FBR Doc Type Codes Error', error);
      throw new AppError('Failed to fetch document type codes from FBR', 503);
    }
  }

  // Get HS codes (item description codes)
  async getHSCodes(): Promise<FBRHSCode[]> {
    try {
      const response = await this.client.get('/itemdesccode');
      return response.data;
    } catch (error: any) {
      logger.error('FBR HS Codes Error', error);
      throw new AppError('Failed to fetch HS codes from FBR', 503);
    }
  }

  // Get SRO item codes
  async getSROItemCodes(): Promise<FBRSROItem[]> {
    try {
      const response = await this.client.get('/sroitemcode');
      return response.data;
    } catch (error: any) {
      logger.error('FBR SRO Item Codes Error', error);
      throw new AppError('Failed to fetch SRO item codes from FBR', 503);
    }
  }

  // Get transaction type codes
  async getTransactionTypeCodes(): Promise<any[]> {
    try {
      const response = await this.client.get('/transtypecode');
      return response.data;
    } catch (error: any) {
      logger.error('FBR Transaction Type Codes Error', error);
      throw new AppError('Failed to fetch transaction type codes from FBR', 503);
    }
  }

  // Get unit of measure codes
  async getUOMCodes(): Promise<FBRUOM[]> {
    try {
      const response = await this.client.get('/uom');
      return response.data;
    } catch (error: any) {
      logger.error('FBR UOM Codes Error', error);
      throw new AppError('Failed to fetch UOM codes from FBR', 503);
    }
  }

  // Get SRO schedules
  async getSROSchedules(): Promise<any[]> {
    try {
      const response = await this.client.get('/srosched');
      return response.data;
    } catch (error: any) {
      logger.error('FBR SRO Schedules Error', error);
      throw new AppError('Failed to fetch SRO schedules from FBR', 503);
    }
  }

  // Get sale type to rate mapping
  async getSaleTypeToRate(date: string, transTypeId: string, originationSupplier: string): Promise<FBRSaleTypeRate[]> {
    try {
      const response = await this.client.get('/SaleTypeToRate', {
        params: {
          date,
          transTypeId,
          originationSupplier,
        },
      });
      return response.data;
    } catch (error: any) {
      logger.error('FBR Sale Type to Rate Error', error);
      throw new AppError('Failed to fetch sale type to rate from FBR', 503);
    }
  }

  // Get HS UOM mapping
  async getHSUOM(hsCode: string, annexureId: string): Promise<any[]> {
    try {
      const response = await this.client.get('/HS_UOM', {
        params: {
          hs_code: hsCode,
          annexure_id: annexureId,
        },
      });
      return response.data;
    } catch (error: any) {
      logger.error('FBR HS UOM Error', error);
      throw new AppError('Failed to fetch HS UOM mapping from FBR', 503);
    }
  }

  // Get SRO item details
  async getSROItem(date: string, sroId: string): Promise<any[]> {
    try {
      const response = await this.client.get('/SROItem', {
        params: {
          date,
          sro_id: sroId,
        },
      });
      return response.data;
    } catch (error: any) {
      logger.error('FBR SRO Item Error', error);
      throw new AppError('Failed to fetch SRO item details from FBR', 503);
    }
  }

  // Test API connection
  async testConnection(): Promise<boolean> {
    try {
      await this.getProvinces();
      return true;
    } catch (error) {
      logger.error('FBR Connection Test Failed', error);
      return false;
    }
  }
}

// Factory function to create FBR service instance
export const createFBRService = (token: string, baseUrl?: string): FBRService => {
  return new FBRService(token, baseUrl);
};

// Reference data caching service
export class FBRReferenceDataService {
  private cache: Map<string, { data: any; expiresAt: number }> = new Map();
  private fbrService: FBRService;

  constructor(fbrService: FBRService) {
    this.fbrService = fbrService;
  }

  private isExpired(expiresAt: number): boolean {
    return Date.now() > expiresAt;
  }

  private getCacheKey(type: string, params?: any): string {
    return params ? `${type}_${JSON.stringify(params)}` : type;
  }

  async getProvinces(): Promise<FBRProvince[]> {
    const cacheKey = this.getCacheKey('provinces');
    const cached = this.cache.get(cacheKey);

    if (cached && !this.isExpired(cached.expiresAt)) {
      return cached.data;
    }

    const data = await this.fbrService.getProvinces();
    this.cache.set(cacheKey, {
      data,
      expiresAt: Date.now() + (24 * 60 * 60 * 1000), // 24 hours
    });

    return data;
  }

  async getHSCodes(): Promise<FBRHSCode[]> {
    const cacheKey = this.getCacheKey('hsCodes');
    const cached = this.cache.get(cacheKey);

    if (cached && !this.isExpired(cached.expiresAt)) {
      return cached.data;
    }

    const data = await this.fbrService.getHSCodes();
    this.cache.set(cacheKey, {
      data,
      expiresAt: Date.now() + (7 * 24 * 60 * 60 * 1000), // 7 days
    });

    return data;
  }

  async getUOMCodes(): Promise<FBRUOM[]> {
    const cacheKey = this.getCacheKey('uomCodes');
    const cached = this.cache.get(cacheKey);

    if (cached && !this.isExpired(cached.expiresAt)) {
      return cached.data;
    }

    const data = await this.fbrService.getUOMCodes();
    this.cache.set(cacheKey, {
      data,
      expiresAt: Date.now() + (7 * 24 * 60 * 60 * 1000), // 7 days
    });

    return data;
  }

  async getSaleTypeToRate(date: string, transTypeId: string, originationSupplier: string): Promise<FBRSaleTypeRate[]> {
    const cacheKey = this.getCacheKey('saleTypeToRate', { date, transTypeId, originationSupplier });
    const cached = this.cache.get(cacheKey);

    if (cached && !this.isExpired(cached.expiresAt)) {
      return cached.data;
    }

    const data = await this.fbrService.getSaleTypeToRate(date, transTypeId, originationSupplier);
    this.cache.set(cacheKey, {
      data,
      expiresAt: Date.now() + (60 * 60 * 1000), // 1 hour
    });

    return data;
  }

  clearCache(): void {
    this.cache.clear();
  }

  clearExpiredCache(): void {
    const now = Date.now();
    for (const [key, value] of this.cache.entries()) {
      if (this.isExpired(value.expiresAt)) {
        this.cache.delete(key);
      }
    }
  }
}