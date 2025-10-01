# DPS POS FBR Integrated

A premier, multi-tenant, web-based Software as a Service (SaaS) Point of Sale (POS) platform specifically designed for Pakistani businesses. This system provides sophisticated sales, inventory, and compliance management with seamless integration to the FBR's Digital Invoicing (DI) API.

## Features

### 🏢 Multi-Tenant SaaS Architecture
- **Super Admin**: Platform-wide management and tenant oversight
- **Tenant Admin**: Business-specific management with FBR Integration Hub
- **Cashier**: Streamlined POS interface for daily operations

### 🇵🇰 Pakistani Business Focus
- **PKR Native**: All financial aspects configured for Pakistani Rupees
- **FBR Integration**: Seamless compliance with Pakistan's tax system
- **Mobile-First**: Optimized for tablets and smartphones
- **WhatsApp Integration**: Digital receipts and admin notifications

### 💳 Smart FBR Integration Engine
- **Automatic Tax Calculation**: Handles all FBR sale types (Standard, 3rd Schedule, Exempt, etc.)
- **Offline Resilience**: Continues operations even when FBR API is unavailable
- **Error Translation**: Converts FBR error codes to user-friendly messages
- **Background Sync**: Automatically retries failed submissions

### 🛒 Modern POS Interface
- **Intuitive Design**: Clean, fast interface requiring minimal training
- **Real-time Inventory**: Live stock tracking with low-stock alerts
- **Flexible Payments**: Support for Cash, Card, Easypaisa, and JazzCash
- **QR Code Generation**: FBR-compliant and custom verification codes

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- SSL certificate (recommended for production)

### Quick Installation

1. **Upload Files**
   ```bash
   # Upload all files to your web server
   # Ensure the web server has write permissions to the uploads/ directory
   ```

2. **Run Installation Wizard**
   - Navigate to `yourdomain.com/install/`
   - Follow the beautiful installation wizard
   - Configure database settings
   - Create super admin account
   - Set application preferences

3. **Configure FBR Integration**
   - Login as Tenant Admin
   - Go to FBR Integration Hub
   - Enter your FBR Bearer Token
   - Choose Sandbox or Production mode
   - Test the connection

### Manual Installation

1. **Database Setup**
   ```sql
   CREATE DATABASE dpspos_fbr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Schema**
   ```bash
   mysql -u username -p dpspos_fbr < install/database.sql
   ```

3. **Configure Database**
   ```php
   // Edit config/database.php
   $db_config = [
       'host' => 'localhost',
       'username' => 'your_username',
       'password' => 'your_password',
       'database' => 'dpspos_fbr'
   ];
   ```

4. **Set Permissions**
   ```bash
   chmod 755 uploads/
   chmod 644 config/*.php
   ```

## Configuration

### FBR API Setup

1. **Get FBR Credentials**
   - Register at FBR Digital Invoicing portal
   - Obtain your Bearer Token
   - Note your business NTN and other details

2. **Configure in DPS POS**
   - Login as Tenant Admin
   - Navigate to FBR Integration Hub
   - Enter Bearer Token
   - Select Sandbox (testing) or Production mode
   - Test connection

### Business Setup

1. **Complete Business Profile**
   - Business name and type
   - NTN and STRN numbers
   - Complete address information
   - Upload business logo

2. **Add Products**
   - Create product categories
   - Add products with proper tax classifications
   - Set HS codes and units of measure
   - Configure stock levels

3. **Setup Staff**
   - Create cashier accounts
   - Set appropriate permissions
   - Train staff on POS interface

## Usage

### For Cashiers

1. **Login to POS**
   - Navigate to POS interface
   - Use your assigned credentials

2. **Process Sales**
   - Add products to cart by clicking
   - Adjust quantities as needed
   - Select customer (optional)
   - Choose payment method
   - Complete checkout

3. **View Sales History**
   - Check today's sales
   - Monitor FBR sync status
   - Print receipts as needed

### For Tenant Admins

1. **Dashboard Overview**
   - Monitor daily sales and revenue
   - Check FBR sync status
   - View low stock alerts
   - Review recent activities

2. **FBR Management**
   - Configure FBR settings
   - Monitor sync status
   - Handle failed submissions
   - Sync reference data

3. **Business Management**
   - Manage products and inventory
   - Add/edit staff accounts
   - Configure business settings
   - Generate reports

### For Super Admins

1. **Platform Management**
   - Monitor all tenants
   - Manage subscriptions
   - View platform statistics
   - Handle support requests

2. **Remote Support**
   - Impersonate tenant accounts
   - Troubleshoot issues
   - Provide technical support

## FBR Integration Details

### Supported Sale Types

- **Standard Rate Goods (18%)**: Regular taxable items
- **Third Schedule Items**: Tax on retail price (MRP)
- **Reduced Rate Goods (5%)**: Items with reduced tax rate
- **Exempt Items**: Tax-free products
- **Steel Items**: Special category for steel products

### API Endpoints

- **Sandbox**: `https://gw.fbr.gov.pk/di_data/v1/di/`
- **Production**: `https://gw.fbr.gov.pk/di_data/v1/di/`
- **Reference Data**: `https://gw.fbr.gov.pk/pdi/v1/`

### Error Handling

The system automatically translates FBR error codes:
- `0001`: Business not registered for sales tax
- `0002`: Invalid customer NTN/CNIC
- `0021`: Missing value of sales
- `0052`: Incorrect HS code
- And many more...

## Security Features

- **Multi-tenant Isolation**: Complete data separation between businesses
- **Role-based Access**: Granular permissions for different user types
- **CSRF Protection**: Built-in protection against cross-site attacks
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: Input sanitization and output escaping
- **Secure File Uploads**: Validated file types and sizes

## WhatsApp Integration

### Digital Receipts
- Send receipt links via WhatsApp
- Professional receipt formatting
- QR code verification

### Admin Notifications
- Daily sales summaries
- Low stock alerts
- FBR sync status updates
- System notifications

## QR Code System

### FBR Fiscalized Mode
- Official FBR QR codes
- Contains FBR invoice number
- Compliant with FBR specifications

### DPS POS Verification
- Custom verification QR codes
- Links to public verification page
- "Verified by DPS POS" seal

### Non-Fiscalized Mode
- Standard POS without FBR
- Internal QR codes for verification
- Suitable for non-registered businesses

## Troubleshooting

### Common Issues

1. **FBR Connection Failed**
   - Check Bearer Token validity
   - Verify network connectivity
   - Ensure correct API endpoints

2. **Installation Issues**
   - Check PHP version compatibility
   - Verify database permissions
   - Ensure file upload limits

3. **Performance Issues**
   - Enable PHP OPcache
   - Optimize database queries
   - Use CDN for static assets

### Support

For technical support and questions:
- Check the documentation
- Review error logs
- Contact system administrator

## License

This software is proprietary and licensed for use according to the terms of purchase.

## Version History

### v1.0.0
- Initial release
- Multi-tenant SaaS architecture
- FBR Digital Invoicing integration
- Modern POS interface
- WhatsApp integration
- QR code system
- Mobile-first design

---

**DPS POS FBR Integrated** - Empowering Pakistani businesses with world-class POS technology and seamless tax compliance.