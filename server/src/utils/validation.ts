import { Request, Response, NextFunction } from 'express';
import { validationResult, ValidationChain } from 'express-validator';
import { AppError } from '../types';

export const handleValidationErrors = (req: Request, res: Response, next: NextFunction) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) {
    const errorMessages = errors.array().map(error => error.msg);
    throw new AppError(`Validation failed: ${errorMessages.join(', ')}`, 400);
  }
  next();
};

export const validateRequest = (validations: ValidationChain[]) => {
  return async (req: Request, res: Response, next: NextFunction) => {
    await Promise.all(validations.map(validation => validation.run(req)));
    handleValidationErrors(req, res, next);
  };
};

// Common validation rules
export const commonValidations = {
  email: (field: string = 'email') => ({
    [field]: {
      isEmail: {
        errorMessage: 'Please provide a valid email address',
      },
      normalizeEmail: true,
    },
  }),

  password: (field: string = 'password') => ({
    [field]: {
      isLength: {
        options: { min: 6, max: 128 },
        errorMessage: 'Password must be between 6 and 128 characters',
      },
      matches: {
        options: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/,
        errorMessage: 'Password must contain at least one lowercase letter, one uppercase letter, and one number',
      },
    },
  }),

  phone: (field: string = 'phone') => ({
    [field]: {
      optional: true,
      isMobilePhone: {
        options: ['en-PK'],
        errorMessage: 'Please provide a valid Pakistani phone number',
      },
    },
  }),

  ntnCnic: (field: string = 'ntnCnic') => ({
    [field]: {
      optional: true,
      isLength: {
        options: { min: 13, max: 15 },
        errorMessage: 'NTN/CNIC must be between 13 and 15 characters',
      },
      matches: {
        options: /^\d+$/,
        errorMessage: 'NTN/CNIC must contain only numbers',
      },
    },
  }),

  positiveNumber: (field: string) => ({
    [field]: {
      isFloat: {
        options: { min: 0 },
        errorMessage: `${field} must be a positive number`,
      },
    },
  }),

  positiveInteger: (field: string) => ({
    [field]: {
      isInt: {
        options: { min: 0 },
        errorMessage: `${field} must be a positive integer`,
      },
    },
  }),

  requiredString: (field: string, minLength: number = 1) => ({
    [field]: {
      notEmpty: {
        errorMessage: `${field} is required`,
      },
      isLength: {
        options: { min: minLength },
        errorMessage: `${field} must be at least ${minLength} characters long`,
      },
    },
  }),

  optionalString: (field: string, minLength: number = 1) => ({
    [field]: {
      optional: true,
      isLength: {
        options: { min: minLength },
        errorMessage: `${field} must be at least ${minLength} characters long`,
      },
    },
  }),

  date: (field: string) => ({
    [field]: {
      isISO8601: {
        errorMessage: `${field} must be a valid date in ISO 8601 format`,
      },
    },
  }),

  pagination: {
    page: {
      optional: true,
      isInt: {
        options: { min: 1 },
        errorMessage: 'Page must be a positive integer',
      },
    },
    limit: {
      optional: true,
      isInt: {
        options: { min: 1, max: 100 },
        errorMessage: 'Limit must be between 1 and 100',
      },
    },
  },
};

// FBR specific validations
export const fbrValidations = {
  hsCode: (field: string = 'hsCode') => ({
    [field]: {
      optional: true,
      isLength: {
        options: { min: 6, max: 10 },
        errorMessage: 'HS Code must be between 6 and 10 characters',
      },
      matches: {
        options: /^\d+$/,
        errorMessage: 'HS Code must contain only numbers',
      },
    },
  }),

  taxRate: (field: string = 'taxRate') => ({
    [field]: {
      isFloat: {
        options: { min: 0, max: 100 },
        errorMessage: 'Tax rate must be between 0 and 100',
      },
    },
  }),

  province: (field: string = 'province') => ({
    [field]: {
      isIn: {
        options: [['Punjab', 'Sindh', 'Khyber Pakhtunkhwa', 'Balochistan', 'Islamabad', 'Azad Kashmir', 'Gilgit-Baltistan']],
        errorMessage: 'Province must be one of the valid Pakistani provinces',
      },
    },
  }),

  invoiceType: (field: string = 'invoiceType') => ({
    [field]: {
      isIn: {
        options: [['SALE', 'DEBIT', 'CREDIT']],
        errorMessage: 'Invoice type must be SALE, DEBIT, or CREDIT',
      },
    },
  }),

  paymentMethod: (field: string = 'paymentMethod') => ({
    [field]: {
      isIn: {
        options: [['CASH', 'CARD', 'BANK_TRANSFER', 'MOBILE_WALLET']],
        errorMessage: 'Payment method must be CASH, CARD, BANK_TRANSFER, or MOBILE_WALLET',
      },
    },
  }),
};