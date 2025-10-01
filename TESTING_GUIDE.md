# DPS POS FBR Integrated - Testing Guide

## 🚀 Quick Start Testing

### Step 1: Start Local Server
```bash
# Navigate to your project directory
cd /path/to/dpspos-fbr

# Start PHP built-in server
php -S localhost:8000

# Or if you have a web server, just navigate to:
# http://your-domain.com/install
```

### Step 2: Access the Installer
Open your browser and go to: `http://localhost:8000/install`

### Step 3: Test License Verification

#### Option A: Use Built-in Demo Licenses
1. **Commercial License (Full Features):**
   - License Key: `DEMO-1234-ABCD-EFGH`
   - Features: All features included
   - Expires: December 31, 2025
   - Max Tenants: Unlimited

2. **Standard License (Limited Features):**
   - License Key: `TEST-5678-WXYZ-1234`
   - Features: Basic features only
   - Expires: December 31, 2024
   - Max Tenants: 5

3. **Trial License (Minimal Features):**
   - License Key: `TRIAL-9999-XXXX-XXXX`
   - Features: POS and basic inventory
   - Expires: March 31, 2024
   - Max Tenants: 1

#### Option B: Test Invalid Scenarios
1. **Invalid Format:**
   - Try: `INVALID-KEY`
   - Expected: Format error message

2. **Non-existent License:**
   - Try: `FAKE-1234-XXXX-XXXX`
   - Expected: License not found error

3. **Expired License:**
   - Try: `EXPIRED-2023-XXXX-XXXX`
   - Expected: Expired license error

## 🧪 Complete Testing Workflow

### 1. System Requirements Test
- ✅ PHP Version Check (7.4+)
- ✅ MySQL Extension Check
- ✅ File Permissions Check
- ✅ Memory Limit Check
- ✅ All Extensions Check

### 2. Database Connection Test
- ✅ Test with localhost
- ✅ Test with custom port
- ✅ Test with different charset
- ✅ Test database creation
- ✅ Test privilege validation

### 3. License Verification Test
- ✅ Valid license format
- ✅ Invalid license format
- ✅ Expired license
- ✅ Non-existent license
- ✅ Server connection issues

### 4. Installation Test
- ✅ Complete installation process
- ✅ Database table creation
- ✅ Super admin creation
- ✅ Configuration file generation
- ✅ File permissions setup

## 🔧 Testing with Custom Licenses

### Adding Your Own Test License

1. **Edit the verification file:**
   ```bash
   nano /install/verify_license.php
   ```

2. **Add your license to the array:**
   ```php
   $validLicenses = [
       'DEMO-1234-ABCD-EFGH' => [
           'expires_at' => '2025-12-31',
           'max_tenants' => 'Unlimited',
           'features' => ['fbr_integration', 'multi_tenant', 'pos_system', 'inventory', 'hrm', 'reporting']
       ],
       // Add your custom license
       'YOUR-2024-TEST-001' => [
           'expires_at' => '2024-12-31',
           'max_tenants' => 3,
           'features' => ['pos_system', 'inventory', 'basic_reporting']
       ]
   ];
   ```

3. **Test your license:**
   - Use `YOUR-2024-TEST-001` in the installer
   - Verify it shows your custom features

## 🌐 Testing with License Server

### Setting Up Local License Server

1. **Create license server directory:**
   ```bash
   mkdir license-server
   cd license-server
   ```

2. **Create database:**
   ```sql
   CREATE DATABASE license_server;
   USE license_server;
   
   -- Create tables (use schema from main documentation)
   ```

3. **Create license server API:**
   ```php
   <?php
   // license-server/api/verify.php
   // (Use the code from main documentation)
   ?>
   ```

4. **Update client verification:**
   ```php
   // In /install/verify_license.php
   $licenseServerUrl = 'http://localhost:8001/api/verify';
   ```

5. **Start license server:**
   ```bash
   php -S localhost:8001
   ```

6. **Test with real license server:**
   - Generate a license in your database
   - Test verification through the installer

## 📊 Testing Checklist

### Pre-Installation Tests
- [ ] Server requirements met
- [ ] Database connection works
- [ ] File permissions correct
- [ ] All PHP extensions available

### Installation Tests
- [ ] License verification works
- [ ] Database tables created
- [ ] Super admin created
- [ ] Configuration files generated
- [ ] Installation lock created

### Post-Installation Tests
- [ ] Application loads correctly
- [ ] Login works
- [ ] POS system functional
- [ ] FBR integration ready
- [ ] Multi-tenant setup working

## 🐛 Common Testing Issues

### Issue 1: License Verification Always Fails
**Symptoms:** All license keys show as invalid
**Solutions:**
- Check license key format (XXXX-XXXX-XXXX-XXXX)
- Verify case sensitivity (uppercase only)
- Check for extra spaces or characters
- Review server error logs

### Issue 2: Database Connection Fails
**Symptoms:** Cannot connect to database
**Solutions:**
- Verify database credentials
- Check if database exists
- Ensure MySQL service is running
- Check firewall settings

### Issue 3: File Permissions Errors
**Symptoms:** Cannot write to storage directories
**Solutions:**
- Set proper permissions (755 for directories, 644 for files)
- Check ownership (should be web server user)
- Verify SELinux settings (if applicable)

### Issue 4: PHP Extensions Missing
**Symptoms:** Requirements check fails
**Solutions:**
- Install missing PHP extensions
- Restart web server after installation
- Check PHP configuration

## 🔍 Debug Mode

### Enable Debug Logging

1. **Edit verify_license.php:**
   ```php
   // Add at the top
   define('DEBUG_MODE', true);
   
   if (DEBUG_MODE) {
       error_log("License verification attempt: " . $licenseKey);
   }
   ```

2. **Check logs:**
   ```bash
   tail -f /var/log/apache2/error.log
   # or
   tail -f /var/log/nginx/error.log
   ```

### Test API Directly

```bash
# Test license verification API
curl -X POST http://localhost:8000/install/verify_license.php \
  -H "Content-Type: application/json" \
  -d '{"key": "DEMO-1234-ABCD-EFGH"}'

# Expected response:
{
  "success": true,
  "message": "License verified successfully",
  "details": {
    "expires_at": "2025-12-31",
    "max_tenants": "Unlimited",
    "features": ["fbr_integration", "multi_tenant", "pos_system", "inventory", "hrm", "reporting"],
    "license_type": "Commercial"
  }
}
```

## 🚀 Production Testing

### Before Going Live

1. **Test with real license server:**
   - Set up production license server
   - Generate real licenses
   - Test verification process

2. **Test with different environments:**
   - Different PHP versions
   - Different database versions
   - Different operating systems

3. **Load testing:**
   - Test with multiple concurrent installations
   - Test license server under load
   - Monitor performance

4. **Security testing:**
   - Test with invalid license keys
   - Test with expired licenses
   - Test with malformed requests

## 📞 Getting Help

### If You're Stuck

1. **Check the logs:**
   - PHP error logs
   - Web server logs
   - Database logs

2. **Verify configuration:**
   - Database credentials
   - File permissions
   - PHP settings

3. **Test step by step:**
   - Test requirements check
   - Test database connection
   - Test license verification
   - Test installation

4. **Contact support:**
   - Email: support@dpspos.com
   - Include error messages and logs
   - Describe what you were trying to do

### Useful Commands

```bash
# Check PHP version
php -v

# Check PHP extensions
php -m

# Check file permissions
ls -la /path/to/dpspos-fbr

# Check database connection
mysql -u username -p -h hostname database_name

# Test license API
curl -X POST http://localhost:8000/install/verify_license.php \
  -H "Content-Type: application/json" \
  -d '{"key": "DEMO-1234-ABCD-EFGH"}'
```

---

This testing guide will help you verify that everything works correctly before deploying to production. Follow the steps carefully and test all scenarios to ensure a smooth experience for your customers.