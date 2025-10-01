import React, { useEffect } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuthStore } from '@/store/authStore';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';
import { Layout } from '@/components/layout/Layout';
import { LoginPage } from '@/pages/auth/LoginPage';
import { RegisterPage } from '@/pages/auth/RegisterPage';
import { DashboardPage } from '@/pages/dashboard/DashboardPage';
import { ProductsPage } from '@/pages/products/ProductsPage';
import { ProductFormPage } from '@/pages/products/ProductFormPage';
import { CustomersPage } from '@/pages/customers/CustomersPage';
import { CustomerFormPage } from '@/pages/customers/CustomerFormPage';
import { SalesPage } from '@/pages/sales/SalesPage';
import { SaleFormPage } from '@/pages/sales/SaleFormPage';
import { SaleDetailPage } from '@/pages/sales/SaleDetailPage';
import { InvoicesPage } from '@/pages/invoices/InvoicesPage';
import { ReportsPage } from '@/pages/reports/ReportsPage';
import { SettingsPage } from '@/pages/settings/SettingsPage';
import { ProfilePage } from '@/pages/profile/ProfilePage';

function App() {
  const { isAuthenticated, isLoading, token } = useAuthStore();

  useEffect(() => {
    // Check if user is authenticated on app load
    if (token && !isAuthenticated) {
      // Token exists but user is not authenticated, try to get profile
      useAuthStore.getState().setLoading(true);
      apiService.getProfile()
        .then(response => {
          if (response.success && response.data) {
            useAuthStore.setState({
              user: response.data.user,
              client: response.data.client,
              isAuthenticated: true,
              isLoading: false,
            });
          } else {
            useAuthStore.getState().logout();
          }
        })
        .catch(() => {
          useAuthStore.getState().logout();
        });
    }
  }, [token, isAuthenticated]);

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <LoadingSpinner size="lg" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    );
  }

  return (
    <Layout>
      <Routes>
        {/* Dashboard */}
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<DashboardPage />} />

        {/* Products */}
        <Route path="/products" element={<ProductsPage />} />
        <Route path="/products/new" element={<ProductFormPage />} />
        <Route path="/products/:id/edit" element={<ProductFormPage />} />

        {/* Customers */}
        <Route path="/customers" element={<CustomersPage />} />
        <Route path="/customers/new" element={<CustomerFormPage />} />
        <Route path="/customers/:id/edit" element={<CustomerFormPage />} />

        {/* Sales */}
        <Route path="/sales" element={<SalesPage />} />
        <Route path="/sales/new" element={<SaleFormPage />} />
        <Route path="/sales/:id" element={<SaleDetailPage />} />

        {/* Invoices */}
        <Route path="/invoices" element={<InvoicesPage />} />

        {/* Reports */}
        <Route path="/reports" element={<ReportsPage />} />

        {/* Settings */}
        <Route path="/settings" element={<SettingsPage />} />
        <Route path="/profile" element={<ProfilePage />} />

        {/* Catch all route */}
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </Layout>
  );
}

export default App;