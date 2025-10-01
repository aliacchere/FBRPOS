# FBR POS System - Advanced Web-Based Point of Sale with FBR Digital Invoicing Integration

A comprehensive, modern Point of Sale (POS) system specifically designed for businesses in Pakistan with seamless integration to the Federal Board of Revenue (FBR) Digital Invoicing API. This system enables businesses to manage sales, inventory, customers, and ensure full compliance with Pakistan's tax regulations.

## 🚀 Features

### Core FBR Integration
- **FBR Digital Invoicing API Integration**: Complete integration with Pakistan's FBR DI API
- **Multi-tenant Architecture**: Each business can configure their own FBR API credentials
- **Invoice Validation & Submission**: Pre-validation and submission of invoices to FBR
- **Reference Data Integration**: Automatic population of provinces, HS codes, UOMs, and tax rates
- **QR Code Generation**: FBR-compliant QR codes for printed invoices
- **Compliance Reporting**: Detailed FBR submission logs and compliance tracking

### Standard POS Features
- **User Management**: Role-based access control (Admin, Manager, Cashier)
- **Product Management**: Complete inventory with FBR HS codes and tax rates
- **Customer Management**: Customer database with FBR province integration
- **Sales Processing**: Intuitive sales interface with real-time calculations
- **Payment Processing**: Multiple payment methods (Cash, Card, Bank Transfer, Mobile Wallet)
- **Hold/Resume Sales**: Ability to hold and resume incomplete sales
- **Returns & Refunds**: Process returns with automatic credit note generation
- **Reporting**: Comprehensive sales, tax, and FBR compliance reports

### Modern UI/UX
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Modern Interface**: Clean, intuitive design with subtle 3D effects
- **Real-time Updates**: Live dashboard with real-time data
- **Accessibility**: WCAG compliant design
- **Dark/Light Mode**: Theme switching capability

## 🛠 Technology Stack

### Backend
- **Node.js** with **Express.js** and **TypeScript**
- **PostgreSQL** database with **Prisma ORM**
- **JWT** authentication with **bcrypt** password hashing
- **Axios** for FBR API integration
- **Winston** for logging
- **Rate limiting** and security middleware

### Frontend
- **React 18** with **TypeScript**
- **Vite** for fast development and building
- **Tailwind CSS** for styling
- **Framer Motion** for animations
- **React Query** for data fetching
- **Zustand** for state management
- **React Hook Form** for form handling

### Additional Tools
- **QR Code Generation**: qrcode library
- **PDF Generation**: jsPDF + html2canvas
- **Date Handling**: date-fns
- **Charts**: Recharts
- **Icons**: Heroicons

## 📋 Prerequisites

Before you begin, ensure you have the following installed:
- **Node.js** (v18 or higher)
- **PostgreSQL** (v13 or higher)
- **npm** or **yarn** package manager
- **Git**

## 🚀 Quick Start

### 1. Clone the Repository
```bash
git clone <repository-url>
cd fbr-pos-system
```

### 2. Install Dependencies
```bash
# Install root dependencies
npm install

# Install all project dependencies
npm run install:all
```

### 3. Environment Setup

#### Backend Environment
Create a `.env` file in the `server` directory:

```env
# Database
DATABASE_URL="postgresql://username:password@localhost:5432/fbr_pos_db?schema=public"

# JWT
JWT_SECRET="your-super-secret-jwt-key-here"
JWT_EXPIRES_IN="7d"

# Server
PORT=3001
NODE_ENV="development"

# FBR API (Default URLs - can be overridden per client)
FBR_BASE_URL="https://gw.fbr.gov.pk/di_data/v1/di"
FBR_SANDBOX_URL="https://sandbox.fbr.gov.pk/di_data/v1/di"

# Encryption
ENCRYPTION_KEY="your-32-character-encryption-key-here"

# Rate Limiting
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=100

# Logging
LOG_LEVEL="info"
LOG_FILE="logs/app.log"
```

#### Frontend Environment
Create a `.env` file in the `client` directory:

```env
VITE_API_URL=http://localhost:3001/api
VITE_APP_NAME=FBR POS System
```

### 4. Database Setup

```bash
# Navigate to server directory
cd server

# Generate Prisma client
npm run db:generate

# Run database migrations
npm run db:migrate

# (Optional) Open Prisma Studio to view data
npm run db:studio
```

### 5. Start Development Servers

```bash
# From root directory - starts both frontend and backend
npm run dev

# Or start individually:
# Backend only
npm run dev:server

# Frontend only
npm run dev:client
```

### 6. Access the Application

- **Frontend**: http://localhost:5173
- **Backend API**: http://localhost:3001
- **API Documentation**: http://localhost:3001/health

## 📱 Usage

### 1. Initial Setup
1. Visit http://localhost:5173
2. Click "Register here" to create a new business account
3. Fill in your business and admin user information
4. Optionally add your FBR API token (can be added later in settings)

### 2. FBR Integration Setup
1. Go to Settings → Client Settings
2. Enter your FBR API token
3. Test the connection to ensure it's working
4. Configure your business details for FBR compliance

### 3. Basic Workflow
1. **Add Products**: Go to Products → Add Product
2. **Add Customers**: Go to Customers → Add Customer
3. **Process Sales**: Go to Sales → New Sale
4. **Submit to FBR**: Validate and submit invoices to FBR
5. **View Reports**: Check sales, tax, and FBR compliance reports

## 🔧 API Endpoints

### Authentication
- `POST /api/auth/register` - Register new business and admin user
- `POST /api/auth/login` - Login user
- `GET /api/auth/profile` - Get current user profile
- `PUT /api/auth/profile` - Update user profile
- `PUT /api/auth/client-settings` - Update client settings
- `GET /api/auth/test-fbr-connection` - Test FBR API connection

### Products
- `GET /api/products` - Get products with filtering and pagination
- `POST /api/products` - Create new product
- `PUT /api/products/:id` - Update product
- `DELETE /api/products/:id` - Delete product
- `GET /api/products/low-stock` - Get low stock products
- `PUT /api/products/:id/stock` - Update product stock

### Customers
- `GET /api/customers` - Get customers with filtering and pagination
- `POST /api/customers` - Create new customer
- `PUT /api/customers/:id` - Update customer
- `DELETE /api/customers/:id` - Delete customer
- `GET /api/customers/search` - Search customers

### Sales
- `GET /api/sales` - Get sales with filtering and pagination
- `POST /api/sales` - Create new sale
- `GET /api/sales/:id` - Get sale details
- `PUT /api/sales/:id/hold` - Hold sale
- `PUT /api/sales/:id/resume` - Resume held sale
- `POST /api/sales/:id/return` - Process return

### Invoices & FBR
- `POST /api/invoices/:id/validate` - Validate invoice with FBR
- `POST /api/invoices/:id/submit` - Submit invoice to FBR
- `GET /api/invoices/:id/pdf` - Generate invoice PDF
- `GET /api/invoices/fbr-logs` - Get FBR submission logs

### Reports
- `GET /api/reports/sales` - Generate sales report
- `GET /api/reports/tax` - Generate tax report
- `GET /api/reports/fbr-compliance` - Generate FBR compliance report
- `GET /api/reports/inventory` - Generate inventory report
- `GET /api/reports/customers` - Generate customer report

## 🏗 Project Structure

```
fbr-pos-system/
├── client/                 # React frontend
│   ├── src/
│   │   ├── components/     # Reusable UI components
│   │   ├── pages/         # Page components
│   │   ├── services/      # API services
│   │   ├── store/         # State management
│   │   ├── types/         # TypeScript types
│   │   └── utils/         # Utility functions
│   ├── public/            # Static assets
│   └── package.json
├── server/                # Node.js backend
│   ├── src/
│   │   ├── controllers/   # Route controllers
│   │   ├── middleware/    # Express middleware
│   │   ├── routes/        # API routes
│   │   ├── services/      # Business logic
│   │   ├── types/         # TypeScript types
│   │   └── utils/         # Utility functions
│   ├── prisma/            # Database schema and migrations
│   └── package.json
├── package.json           # Root package.json
└── README.md
```

## 🔒 Security Features

- **JWT Authentication**: Secure token-based authentication
- **Password Hashing**: bcrypt for secure password storage
- **Data Encryption**: Sensitive data encrypted at rest
- **Rate Limiting**: API rate limiting to prevent abuse
- **Input Validation**: Comprehensive input validation and sanitization
- **CORS Protection**: Configured CORS for secure cross-origin requests
- **Helmet Security**: Security headers with Helmet.js

## 📊 FBR Compliance Features

### Invoice Data Structure
- Complete FBR-compliant invoice header and item structure
- Automatic tax calculations based on FBR rates
- Support for all FBR invoice types (SALE, DEBIT, CREDIT)
- Proper handling of FBR reference data

### Reference Data Integration
- **Provinces**: Pakistani provinces for address validation
- **HS Codes**: Harmonized System codes for product classification
- **UOM Codes**: Unit of Measure codes
- **Tax Rates**: Dynamic tax rate fetching from FBR
- **SRO Schedules**: SRO schedule integration

### Compliance Reporting
- FBR submission status tracking
- Detailed error logging and reporting
- Compliance rate monitoring
- Audit trail for all FBR interactions

## 🚀 Deployment

### Production Build
```bash
# Build both frontend and backend
npm run build

# Start production server
npm start
```

### Environment Variables for Production
Ensure all environment variables are properly set for production:
- Use strong, unique secrets
- Configure proper database connection
- Set up proper CORS origins
- Configure logging levels
- Set up proper rate limiting

### Database Migration
```bash
cd server
npm run db:migrate
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:
- Create an issue in the repository
- Check the documentation
- Review the API endpoints

## 🔮 Roadmap

- [ ] Mobile app (React Native)
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] Advanced inventory management
- [ ] Integration with payment gateways
- [ ] Barcode scanning support
- [ ] Offline mode support
- [ ] Advanced reporting features

## 📞 Contact

For business inquiries or technical support, please contact the development team.

---

**Note**: This system is specifically designed for businesses operating in Pakistan and requires proper FBR API credentials for full functionality. Ensure compliance with local tax regulations when using this system.