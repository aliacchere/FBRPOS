# 🚀 Quick Test Reference - DPS POS FBR Integrated

## ⚡ Immediate Testing (No Setup Required)

### 1. Start Testing Right Now
```bash
# Navigate to your project
cd /path/to/dpspos-fbr

# Start local server
php -S localhost:8000

# Open browser
# Go to: http://localhost:8000/install
```

### 2. Use These Demo Licenses

| License Key | Type | Features | Expires | Max Tenants |
|-------------|------|----------|---------|-------------|
| `DEMO-1234-ABCD-EFGH` | Commercial | All Features | 2025-12-31 | Unlimited |
| `TEST-5678-WXYZ-1234` | Standard | Basic Features | 2024-12-31 | 5 |
| `TRIAL-9999-XXXX-XXXX` | Trial | Limited Features | 2024-03-31 | 1 |

### 3. Test Invalid Scenarios
- **Wrong Format:** `INVALID-KEY` → Format error
- **Non-existent:** `FAKE-1234-XXXX-XXXX` → Not found error
- **Expired:** `EXPIRED-2023-XXXX-XXXX` → Expired error

## 🔧 Quick Fixes

### If License Verification Fails
1. Check license key format: `XXXX-XXXX-XXXX-XXXX`
2. Ensure uppercase letters only
3. No extra spaces or characters
4. Check browser console for errors

### If Database Connection Fails
1. Verify MySQL is running
2. Check credentials in installer
3. Ensure database exists
4. Check firewall settings

### If Installation Stops
1. Check file permissions (755 for directories)
2. Verify PHP extensions are installed
3. Check memory limit (128MB+)
4. Review error logs

## 📋 Testing Checklist

### ✅ System Requirements
- [ ] PHP 7.4+ installed
- [ ] MySQL/PDO extension available
- [ ] cURL extension available
- [ ] GD extension available
- [ ] MBString extension available
- [ ] OpenSSL extension available
- [ ] File permissions correct
- [ ] Memory limit 128MB+

### ✅ License Verification
- [ ] Valid license works
- [ ] Invalid format shows error
- [ ] Expired license shows error
- [ ] Non-existent license shows error
- [ ] Server connection error handled

### ✅ Installation Process
- [ ] Requirements check passes
- [ ] Database connection works
- [ ] Admin account created
- [ ] License verified
- [ ] Installation completes
- [ ] Application loads

## 🐛 Common Issues & Solutions

### Issue: "License verification failed"
**Solution:** Use exact license key: `DEMO-1234-ABCD-EFGH`

### Issue: "Database connection failed"
**Solution:** 
- Check MySQL is running
- Use correct credentials
- Ensure database exists

### Issue: "File permissions error"
**Solution:**
```bash
chmod 755 storage/
chmod 755 bootstrap/cache/
chmod 755 public/uploads/
```

### Issue: "PHP extension missing"
**Solution:**
```bash
# Ubuntu/Debian
sudo apt install php-mysql php-curl php-gd php-mbstring php-openssl

# CentOS/RHEL
sudo yum install php-mysql php-curl php-gd php-mbstring php-openssl
```

## 🚀 Quick Production Setup

### 1. Update License Server URL
Edit `/install/verify_license.php`:
```php
$licenseServerUrl = 'https://license.yourdomain.com/api/verify';
```

### 2. Generate Test License
```php
// Add to your license server
$licenseKey = 'YOUR-2024-TEST-001';
// Test with this key
```

### 3. Deploy to Production
1. Upload files to web server
2. Set up database
3. Configure license server
4. Test installation
5. Go live!

## 📞 Need Help?

### Quick Debug Commands
```bash
# Check PHP version
php -v

# Check extensions
php -m | grep -E "(mysql|curl|gd|mbstring|openssl)"

# Check file permissions
ls -la storage/

# Test license API
curl -X POST http://localhost:8000/install/verify_license.php \
  -H "Content-Type: application/json" \
  -d '{"key": "DEMO-1234-ABCD-EFGH"}'
```

### Error Logs
```bash
# Check PHP errors
tail -f /var/log/php_errors.log

# Check web server errors
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
```

---

**Ready to test?** Just run `php -S localhost:8000` and go to `http://localhost:8000/install`! 🎉