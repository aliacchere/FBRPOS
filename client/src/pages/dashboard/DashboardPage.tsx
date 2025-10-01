import React from 'react';
import { useQuery } from 'react-query';
import { 
  CurrencyDollarIcon, 
  ShoppingBagIcon, 
  ExclamationTriangleIcon,
  ClockIcon,
  TrendingUpIcon,
  ChartBarIcon,
} from '@heroicons/react/24/outline';
import { apiService } from '@/services/api';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';
import { DashboardStats } from '@/types';

export const DashboardPage: React.FC = () => {
  const { data: stats, isLoading } = useQuery<DashboardStats>(
    'dashboard-stats',
    () => apiService.getDashboardStats().then(res => res.data),
    {
      refetchInterval: 30000, // Refetch every 30 seconds
    }
  );

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    );
  }

  if (!stats) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500">Failed to load dashboard data</p>
      </div>
    );
  }

  const statCards = [
    {
      name: 'Today\'s Sales',
      value: `PKR ${stats.todaySales.amount.toLocaleString()}`,
      count: stats.todaySales.count,
      icon: CurrencyDollarIcon,
      color: 'text-green-600',
      bgColor: 'bg-green-100',
    },
    {
      name: 'Monthly Sales',
      value: `PKR ${stats.monthlySales.amount.toLocaleString()}`,
      count: stats.monthlySales.count,
      icon: ChartBarIcon,
      color: 'text-blue-600',
      bgColor: 'bg-blue-100',
    },
    {
      name: 'Low Stock Products',
      value: stats.lowStockProducts.toString(),
      count: stats.lowStockProducts,
      icon: ExclamationTriangleIcon,
      color: 'text-yellow-600',
      bgColor: 'bg-yellow-100',
    },
    {
      name: 'Pending FBR Submissions',
      value: stats.pendingFBRSubmissions.toString(),
      count: stats.pendingFBRSubmissions,
      icon: ClockIcon,
      color: 'text-orange-600',
      bgColor: 'bg-orange-100',
    },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p className="mt-1 text-sm text-gray-500">
          Overview of your business performance
        </p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {statCards.map((stat) => (
          <div key={stat.name} className="card">
            <div className="card-content">
              <div className="flex items-center">
                <div className={`p-3 rounded-lg ${stat.bgColor}`}>
                  <stat.icon className={`h-6 w-6 ${stat.color}`} />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-500">{stat.name}</p>
                  <p className="text-2xl font-semibold text-gray-900">{stat.value}</p>
                  <p className="text-xs text-gray-500">{stat.count} items</p>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Charts and Recent Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Sales Chart */}
        <div className="card">
          <div className="card-header">
            <h3 className="text-lg font-medium text-gray-900">Sales Trend</h3>
            <p className="text-sm text-gray-500">Last 7 days</p>
          </div>
          <div className="card-content">
            <div className="h-64 flex items-center justify-center">
              <div className="text-center">
                <TrendingUpIcon className="h-12 w-12 text-gray-400 mx-auto mb-2" />
                <p className="text-gray-500">Sales chart will be displayed here</p>
                <p className="text-sm text-gray-400">
                  Total: PKR {stats.salesChart.reduce((sum, day) => sum + day.amount, 0).toLocaleString()}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Recent Sales */}
        <div className="card">
          <div className="card-header">
            <h3 className="text-lg font-medium text-gray-900">Recent Sales</h3>
            <p className="text-sm text-gray-500">Latest transactions</p>
          </div>
          <div className="card-content">
            <div className="space-y-4">
              {stats.recentSales.length > 0 ? (
                stats.recentSales.map((sale) => (
                  <div key={sale.id} className="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                    <div>
                      <p className="text-sm font-medium text-gray-900">
                        {sale.invoiceNumber}
                      </p>
                      <p className="text-xs text-gray-500">
                        {sale.customerName || 'Walk-in Customer'}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-medium text-gray-900">
                        PKR {sale.totalAmount.toLocaleString()}
                      </p>
                      <p className="text-xs text-gray-500">
                        {new Date(sale.createdAt).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                ))
              ) : (
                <div className="text-center py-8">
                  <ShoppingBagIcon className="h-12 w-12 text-gray-400 mx-auto mb-2" />
                  <p className="text-gray-500">No recent sales</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="card">
        <div className="card-header">
          <h3 className="text-lg font-medium text-gray-900">Quick Actions</h3>
        </div>
        <div className="card-content">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a
              href="/sales/new"
              className="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-colors"
            >
              <CurrencyDollarIcon className="h-8 w-8 text-primary-600 mb-2" />
              <span className="text-sm font-medium text-gray-900">New Sale</span>
            </a>
            <a
              href="/products/new"
              className="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-colors"
            >
              <ShoppingBagIcon className="h-8 w-8 text-primary-600 mb-2" />
              <span className="text-sm font-medium text-gray-900">Add Product</span>
            </a>
            <a
              href="/customers/new"
              className="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-colors"
            >
              <ShoppingBagIcon className="h-8 w-8 text-primary-600 mb-2" />
              <span className="text-sm font-medium text-gray-900">Add Customer</span>
            </a>
            <a
              href="/reports"
              className="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-colors"
            >
              <ChartBarIcon className="h-8 w-8 text-primary-600 mb-2" />
              <span className="text-sm font-medium text-gray-900">View Reports</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  );
};