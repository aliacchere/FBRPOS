import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { BuildingOfficeIcon } from '@heroicons/react/24/outline';
import { useAuthStore } from '@/store/authStore';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';

interface RegisterForm {
  clientName: string;
  clientEmail: string;
  clientPhone?: string;
  clientAddress?: string;
  clientProvince: string;
  businessName: string;
  businessAddress: string;
  businessProvince: string;
  fbrToken?: string;
  fbrBaseUrl?: string;
  adminFirstName: string;
  adminLastName: string;
  adminEmail: string;
  adminPassword: string;
  confirmPassword: string;
}

const provinces = [
  'Punjab',
  'Sindh',
  'Khyber Pakhtunkhwa',
  'Balochistan',
  'Islamabad',
  'Azad Kashmir',
  'Gilgit-Baltistan',
];

export const RegisterPage: React.FC = () => {
  const navigate = useNavigate();
  const { register: registerUser, isLoading } = useAuthStore();
  const [isSubmitting, setIsSubmitting] = useState(false);

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<RegisterForm>();

  const password = watch('adminPassword');

  const onSubmit = async (data: RegisterForm) => {
    if (data.adminPassword !== data.confirmPassword) {
      return;
    }

    setIsSubmitting(true);
    try {
      const success = await registerUser(data);
      if (success) {
        navigate('/dashboard');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-2xl w-full space-y-8">
        {/* Header */}
        <div className="text-center">
          <div className="flex justify-center">
            <BuildingOfficeIcon className="h-12 w-12 text-primary-600" />
          </div>
          <h2 className="mt-6 text-3xl font-bold text-gray-900">
            Register Your Business
          </h2>
          <p className="mt-2 text-sm text-gray-600">
            Create your FBR POS System account
          </p>
        </div>

        {/* Registration Form */}
        <form className="mt-8 space-y-6" onSubmit={handleSubmit(onSubmit)}>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Client Information */}
            <div className="space-y-4">
              <h3 className="text-lg font-medium text-gray-900">Client Information</h3>
              
              <div>
                <label className="label">Client Name *</label>
                <input
                  {...register('clientName', { required: 'Client name is required' })}
                  className="input w-full"
                  placeholder="Enter client name"
                />
                {errors.clientName && (
                  <p className="mt-1 text-sm text-error-600">{errors.clientName.message}</p>
                )}
              </div>

              <div>
                <label className="label">Client Email *</label>
                <input
                  {...register('clientEmail', {
                    required: 'Email is required',
                    pattern: {
                      value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                      message: 'Invalid email address',
                    },
                  })}
                  type="email"
                  className="input w-full"
                  placeholder="Enter client email"
                />
                {errors.clientEmail && (
                  <p className="mt-1 text-sm text-error-600">{errors.clientEmail.message}</p>
                )}
              </div>

              <div>
                <label className="label">Phone</label>
                <input
                  {...register('clientPhone')}
                  type="tel"
                  className="input w-full"
                  placeholder="Enter phone number"
                />
              </div>

              <div>
                <label className="label">Address</label>
                <textarea
                  {...register('clientAddress')}
                  className="input w-full"
                  rows={3}
                  placeholder="Enter address"
                />
              </div>

              <div>
                <label className="label">Province *</label>
                <select
                  {...register('clientProvince', { required: 'Province is required' })}
                  className="input w-full"
                >
                  <option value="">Select province</option>
                  {provinces.map((province) => (
                    <option key={province} value={province}>
                      {province}
                    </option>
                  ))}
                </select>
                {errors.clientProvince && (
                  <p className="mt-1 text-sm text-error-600">{errors.clientProvince.message}</p>
                )}
              </div>
            </div>

            {/* Business Information */}
            <div className="space-y-4">
              <h3 className="text-lg font-medium text-gray-900">Business Information</h3>
              
              <div>
                <label className="label">Business Name *</label>
                <input
                  {...register('businessName', { required: 'Business name is required' })}
                  className="input w-full"
                  placeholder="Enter business name"
                />
                {errors.businessName && (
                  <p className="mt-1 text-sm text-error-600">{errors.businessName.message}</p>
                )}
              </div>

              <div>
                <label className="label">Business Address *</label>
                <textarea
                  {...register('businessAddress', { required: 'Business address is required' })}
                  className="input w-full"
                  rows={3}
                  placeholder="Enter business address"
                />
                {errors.businessAddress && (
                  <p className="mt-1 text-sm text-error-600">{errors.businessAddress.message}</p>
                )}
              </div>

              <div>
                <label className="label">Business Province *</label>
                <select
                  {...register('businessProvince', { required: 'Business province is required' })}
                  className="input w-full"
                >
                  <option value="">Select province</option>
                  {provinces.map((province) => (
                    <option key={province} value={province}>
                      {province}
                    </option>
                  ))}
                </select>
                {errors.businessProvince && (
                  <p className="mt-1 text-sm text-error-600">{errors.businessProvince.message}</p>
                )}
              </div>

              <div>
                <label className="label">FBR API Token</label>
                <input
                  {...register('fbrToken')}
                  type="password"
                  className="input w-full"
                  placeholder="Enter FBR API token (optional)"
                />
                <p className="mt-1 text-xs text-gray-500">
                  You can add this later in settings
                </p>
              </div>

              <div>
                <label className="label">FBR Base URL</label>
                <input
                  {...register('fbrBaseUrl')}
                  className="input w-full"
                  placeholder="https://gw.fbr.gov.pk/di_data/v1/di"
                />
              </div>
            </div>
          </div>

          {/* Admin User Information */}
          <div className="space-y-4">
            <h3 className="text-lg font-medium text-gray-900">Admin User Information</h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="label">First Name *</label>
                <input
                  {...register('adminFirstName', { required: 'First name is required' })}
                  className="input w-full"
                  placeholder="Enter first name"
                />
                {errors.adminFirstName && (
                  <p className="mt-1 text-sm text-error-600">{errors.adminFirstName.message}</p>
                )}
              </div>

              <div>
                <label className="label">Last Name *</label>
                <input
                  {...register('adminLastName', { required: 'Last name is required' })}
                  className="input w-full"
                  placeholder="Enter last name"
                />
                {errors.adminLastName && (
                  <p className="mt-1 text-sm text-error-600">{errors.adminLastName.message}</p>
                )}
              </div>
            </div>

            <div>
              <label className="label">Email *</label>
              <input
                {...register('adminEmail', {
                  required: 'Email is required',
                  pattern: {
                    value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                    message: 'Invalid email address',
                  },
                })}
                type="email"
                className="input w-full"
                placeholder="Enter admin email"
              />
              {errors.adminEmail && (
                <p className="mt-1 text-sm text-error-600">{errors.adminEmail.message}</p>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="label">Password *</label>
                <input
                  {...register('adminPassword', {
                    required: 'Password is required',
                    minLength: {
                      value: 6,
                      message: 'Password must be at least 6 characters',
                    },
                  })}
                  type="password"
                  className="input w-full"
                  placeholder="Enter password"
                />
                {errors.adminPassword && (
                  <p className="mt-1 text-sm text-error-600">{errors.adminPassword.message}</p>
                )}
              </div>

              <div>
                <label className="label">Confirm Password *</label>
                <input
                  {...register('confirmPassword', {
                    required: 'Please confirm your password',
                    validate: (value) =>
                      value === password || 'Passwords do not match',
                  })}
                  type="password"
                  className="input w-full"
                  placeholder="Confirm password"
                />
                {errors.confirmPassword && (
                  <p className="mt-1 text-sm text-error-600">{errors.confirmPassword.message}</p>
                )}
              </div>
            </div>
          </div>

          <div>
            <button
              type="submit"
              disabled={isSubmitting || isLoading}
              className="btn-primary w-full btn-lg"
            >
              {isSubmitting || isLoading ? (
                <div className="flex items-center justify-center">
                  <LoadingSpinner size="sm" className="mr-2" />
                  Creating account...
                </div>
              ) : (
                'Create Account'
              )}
            </button>
          </div>

          <div className="text-center">
            <p className="text-sm text-gray-600">
              Already have an account?{' '}
              <Link
                to="/login"
                className="font-medium text-primary-600 hover:text-primary-500"
              >
                Sign in here
              </Link>
            </p>
          </div>
        </form>
      </div>
    </div>
  );
};