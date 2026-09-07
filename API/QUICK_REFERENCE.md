# OrdinaTrack API - Quick Reference Guide

## File Locations & Namespaces

| Component | File | Namespace |
|-----------|------|-----------|
| Database Connection | `src/Connections/Database.php` | `Ordinatrack\Api\Connections` |
| Authentication | `src/Model/AuthModel.php` | `Ordinatrack\Api\Model` |
| Email Service | `src/Services/MailService.php` | `Ordinatrack\Api\Services` |
| Validation Helper | `src/Helpers/ValidationHelper.php` | `Ordinatrack\Api\Helpers` |
| Configuration | `src/Config/Config.php` | `Ordinatrack\Api\Config` |

## Common Code Snippets

### Database Queries

```php
use Ordinatrack\Api\Connections\Database;

// Fetch single row
$user = Database::fetch('SELECT * FROM users WHERE id = ?', [1]);

// Fetch all rows
$users = Database::fetchAll('SELECT * FROM users WHERE role = ?', ['admin']);

// Execute insert/update/delete
Database::execute('UPDATE users SET name = ? WHERE id = ?', ['John', 1]);

// Get last insert ID
$id = Database::lastInsertId();

// Transactions
Database::beginTransaction();
try {
    // Multiple operations
    Database::commit();
} catch (Exception $e) {
    Database::rollback();
}
```

### Authentication

```php
use Ordinatrack\Api\Model\AuthModel;

// Register user (sends welcome email)
$result = AuthModel::register([
    'email' => 'user@example.com',
    'password' => 'SecurePass123!',
    'first_name' => 'John',
    'last_name' => 'Doe'
]);

// Login (returns JWT token)
$result = AuthModel::login('user@example.com', 'SecurePass123!');
$token = $result['token'];

// Verify token
$decoded = AuthModel::verifyToken($token);

// Password reset flow
AuthModel::requestPasswordReset('user@example.com');
AuthModel::resetPassword($resetToken, 'NewPassword123!');

// Change password
AuthModel::changePassword($userId, 'OldPassword', 'NewPassword123!');

// Get user info
$user = AuthModel::getUserById($userId);

// Update profile
AuthModel::updateProfile($userId, [
    'first_name' => 'Jane',
    'last_name' => 'Smith'
]);
```

### Email Sending

```php
use Ordinatrack\Api\Services\MailService;

$mail = new MailService();

// Simple email
$mail->send('user@example.com', 'Subject', '<h1>Hello</h1>');

// Multiple recipients
$mail->sendToMultiple(['user1@example.com', 'user2@example.com'], 'Subject', 'Body');

// With attachments
$mail->sendWithAttachments('user@example.com', 'Subject', 'Body', [
    'path/to/file.pdf',
    'path/to/image.jpg'
]);

// Pre-built templates
$mail->sendPasswordResetEmail('user@example.com', 'John', 'token123', 'https://app.com/reset?token=token123');
$mail->sendWelcomeEmail('user@example.com', 'John', 'https://app.com/login');
$mail->sendVerificationEmail('user@example.com', 'John', 'https://app.com/verify?token=token123');
```

### Input Validation

```php
use Ordinatrack\Api\Helpers\ValidationHelper;

// Pre-built rule sets
$result = ValidationHelper::validate($_POST, ValidationHelper::registrationRules());
$result = ValidationHelper::validate($_POST, ValidationHelper::loginRules());
$result = ValidationHelper::validate($_POST, ValidationHelper::profileUpdateRules());

// Custom validation
$rules = [
    'email' => 'required|valid_email',
    'age' => 'required|integer|min_numeric,18|max_numeric,120',
    'phone' => 'valid_phone'
];
$result = ValidationHelper::validate($_POST, $rules);

if ($result['is_valid']) {
    $data = $result['data'];
} else {
    $errors = $result['errors'];
}

// String utilities
$email = ValidationHelper::sanitize($_POST['email'], 'email');
$html = ValidationHelper::sanitize($_POST['message'], 'html');
$escaped = ValidationHelper::escape($input);

// Validation checks
if (ValidationHelper::isValidUuid($id)) { /* ... */ }
if (ValidationHelper::isValidPhone($phone)) { /* ... */ }
if (ValidationHelper::isStrongPassword($password)) { /* ... */ }
```

### File & Image Upload

```php
use Ordinatrack\Api\Helpers\ValidationHelper;

// Upload image
$result = ValidationHelper::uploadImage(
    $_FILES['avatar'],
    'uploads/avatars/',
    1920,  // max width
    1920   // max height
);

if ($result['success']) {
    $filePath = $result['file_path'];
    $dimensions = $result['dimensions'];
} else {
    echo "Error: " . $result['message'];
}

// Upload generic file
$result = ValidationHelper::uploadFile(
    $_FILES['document'],
    'uploads/documents/',
    'document',
    5242880  // 5MB
);

// Get image
$result = ValidationHelper::getImage('avatar.jpg', 'uploads/avatars/');
if ($result['success']) {
    $width = $result['dimensions']['width'];
    $height = $result['dimensions']['height'];
}

// Get any file
$result = ValidationHelper::getFile('document.pdf', 'uploads/documents/');
if ($result['success']) {
    $filePath = $result['file_path'];
    $mimeType = $result['mime_type'];
}

// Delete file
ValidationHelper::deleteFile('uploads/old_file.pdf');
```

### Configuration Access

```php
use Ordinatrack\Api\Config\Config;

// Get individual values
$dbHost = Config::getString('DB_HOST');
$jwtSecret = Config::getString('JWT_SECRET');
$sessionTimeout = Config::getInt('SESSION_TIMEOUT');
$debugMode = Config::getBool('APP_DEBUG');

// Get grouped config
$db = Config::getDatabase();  // Returns array with host, port, name, user, pass, charset
$jwt = Config::getJwt();      // Returns array with secret, algorithm, expiration
$mail = Config::getMail();    // Returns array with smtp settings
$session = Config::getSession();  // Returns array with session settings

// Convenience checks
if (Config::isProduction()) { /* ... */ }
if (Config::isDebug()) { /* ... */ }
```

## Response Format Conventions

### Validation Response
```php
[
    'is_valid' => bool,
    'errors' => ['field' => 'error message'],
    'data' => ['field' => 'value']
]
```

### Success Response
```php
[
    'success' => true,
    'message' => 'Operation successful',
    'data' => ['field' => 'value']
]
```

### Error Response
```php
[
    'success' => false,
    'message' => 'Error description',
    'errors' => ['field' => 'error message']
]
```

### File Upload Response
```php
[
    'success' => bool,
    'message' => 'string',
    'file_path' => 'string|null',
    'file_name' => 'string|null',
    'dimensions' => ['width' => int, 'height' => int]  // Images only
]
```

## Environment Variables (.env)

### Database
```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ordinatrack
DB_USER=root
DB_PASS=password
DB_CHARSET=utf8mb4
```

### JWT
```env
JWT_SECRET=N/RcQxqFV8dWH0OI0LuolC6KhTGxcKC5y0s3OkFnynY=
JWT_ALGORITHM=HS256
JWT_EXPIRATION=86400
JWT_REFRESH_EXPIRATION=604800
```

### Email
```env
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@ordinatrack.com
MAIL_FROM_NAME=OrdinaTrack
```

### Application
```env
APP_ENV=development
APP_DEBUG=true
APP_NAME=OrdinaTrack
APP_URL=http://localhost
```

## GUMP Validation Rules Quick List

| Rule | Example | Purpose |
|------|---------|---------|
| `required` | `'field' => 'required'` | Field must have value |
| `valid_email` | `'email' => 'valid_email'` | Valid email format |
| `min_len,n` | `'name' => 'min_len,3'` | Minimum string length |
| `max_len,n` | `'name' => 'max_len,50'` | Maximum string length |
| `integer` | `'age' => 'integer'` | Must be integer |
| `numeric` | `'count' => 'numeric'` | Must be numeric |
| `alpha` | `'name' => 'alpha'` | Letters only |
| `alpha_space` | `'name' => 'alpha_space'` | Letters and spaces |
| `alpha_dash` | `'slug' => 'alpha_dash'` | Letters, dash, underscore |
| `url` | `'website' => 'url'` | Valid URL format |
| `ip` | `'address' => 'ip'` | Valid IP address |
| `regex,pattern` | `'code' => 'regex,^[A-Z]{3}$'` | Regex pattern match |

## Allowed File Types

### Images
- JPEG (.jpg)
- PNG (.png)
- GIF (.gif)
- WebP (.webp)
- SVG (.svg)

### Documents
- PDF (.pdf)
- Word (.doc, .docx)
- Excel (.xls, .xlsx)
- CSV (.csv)

### Size Limits
- Images: 5MB
- Documents: 10MB
- Default: 10MB

## Common Errors & Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| JWT token invalid | Expired or wrong secret | Regenerate token or check JWT_SECRET |
| Database connection failed | Credentials wrong or MySQL down | Check .env DB credentials and MySQL status |
| Email not sending | SMTP credentials wrong | Verify MAIL_* variables and SMTP provider |
| File upload failed | Directory not writable | Create uploads directory with 0755 permissions |
| Validation failed | Invalid input format | Review validation rules and error messages |
| Image dimensions error | Image too large | Upload smaller image or increase max dimensions |

## Useful Commands

### Install dependencies
```bash
composer install
```

### Generate strong JWT secret
```bash
openssl rand -base64 32
```

### Test database connection
```bash
mysql -h localhost -u root -p ordinatrack
```

### Check PHP version
```bash
php -v
```

### View error logs
```bash
tail -f /var/log/php-errors.log
```

## Documentation Files

- **SETUP_COMPLETE.md** - Complete setup guide
- **ENV_SETUP.md** - Environment configuration
- **MAIL_SETUP.md** - Email service guide
- **VALIDATION_HELPER.md** - Validation & file handling

## Security Reminders

✅ **Always:**
- Validate and sanitize user input
- Use prepared statements for database queries
- Hash passwords with bcrypt
- Use HTTPS in production
- Check JWT token expiration
- Validate file uploads

❌ **Never:**
- Commit `.env` with real credentials
- Log sensitive data
- Trust client-side validation
- Use plain text passwords
- Expose error details to users
- Accept arbitrary file types

---

**Last Updated:** September 7, 2026
**API Version:** 1.0.0
