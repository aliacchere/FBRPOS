import { Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import { PrismaClient } from '@prisma/client';
import { AuthenticatedRequest, AppError } from '../types';

const prisma = new PrismaClient();

export const authenticateToken = async (
  req: AuthenticatedRequest,
  res: Response,
  next: NextFunction
): Promise<void> => {
  try {
    const authHeader = req.headers.authorization;
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) {
      throw new AppError('Access token required', 401);
    }

    const decoded = jwt.verify(token, process.env.JWT_SECRET!) as any;
    
    // Get user with client information
    const user = await prisma.user.findUnique({
      where: { id: decoded.userId },
      include: {
        client: true,
      },
    });

    if (!user || !user.isActive) {
      throw new AppError('Invalid or inactive user', 401);
    }

    if (!user.client.isActive) {
      throw new AppError('Client account is inactive', 401);
    }

    req.user = user;
    req.client = user.client;
    next();
  } catch (error: any) {
    if (error.name === 'JsonWebTokenError') {
      next(new AppError('Invalid token', 401));
    } else if (error.name === 'TokenExpiredError') {
      next(new AppError('Token expired', 401));
    } else {
      next(error);
    }
  }
};

export const requireRole = (roles: string[]) => {
  return (req: AuthenticatedRequest, res: Response, next: NextFunction): void => {
    if (!req.user) {
      throw new AppError('Authentication required', 401);
    }

    if (!roles.includes(req.user.role)) {
      throw new AppError('Insufficient permissions', 403);
    }

    next();
  };
};

export const requireClient = (req: AuthenticatedRequest, res: Response, next: NextFunction): void => {
  if (!req.client) {
    throw new AppError('Client context required', 400);
  }
  next();
};