# ValidationHelper Documentation

A comprehensive utility class for input validation, file uploads, image handling, and data sanitization using GUMP validation library.

## Installation

The class uses GUMP (wixel/gump) which is already in composer.json. Run `composer install` to ensure all dependencies are installed.

## Usage Overview

### Basic Input Validation

```php
use Ordinatrack\Api\Helpers\ValidationHelper;

// Validate login data
$data = [
    'email' => 'user@example.com',
    'password' => 'secure_password'
];

$rules = ValidationHelper::loginRules();
$result = ValidationHelper::validate($data, $rules);

if ($result['is_valid']) {
    $validatedData = $result['data'];
} else {
    $errors = $result['errors'];
}
```

### Return Format

All validation methods return:
```php
[
    'is_valid' => bool,        // Validation passed
    'errors' => array,         // Field-level errors
    'data' => array           // Validated and filtered data
]
```

## Validation Methods

### Validate Data

```php
ValidationHelper::validate(array $data, array $rules, array $filters = []): array
```

Generic validation using GUMP rules and optional filters.

**Example:**
```php
$data = ['email' => ' user@example.com ', 'age' => '25'];
$rules = [
    'email' => 'required|valid_email',
    'age' => 'required|integer|min_numeric,18|max_numeric,120'
];
$filters = [
    'email' => 'trim',
    'age' => 'trim'
];

$result = ValidationHelper::validate($data, $rules, $filters);
```

### Pre-built Rule Sets

#### Email Validation
```php
$rules = ValidationHelper::emailRules();
// Validates required, valid email format
```

#### Password Validation
```php
$rules = ValidationHelper::passwordRules();
// Validates required, 8-128 characters
```

#### Name Validation
```php
$rules = ValidationHelper::nameRules();
// Validates first_name and last_name: required, alphabetic with spaces, max 50 chars
```

#### Registration Validation
```php
$rules = ValidationHelper::registrationRules();
// Combines email, password, and name rules
```

#### Login Validation
```php
$rules = ValidationHelper::loginRules();
// Validates email (required, valid) and password (required)
```

#### Profile Update Validation
```php
$rules = ValidationHelper::profileUpdateRules();
// Optional fields: first_name, last_name, email (with validation)
```

## Available GUMP Validation Rules

The following GUMP validators are available:

| Rule | Description |
|------|-------------|
| `required` | Field is required |
| `valid_email` | Valid email format |
| `min_len,n` | Minimum string length |
| `max_len,n` | Maximum string length |
| `integer` | Must be integer |
| `min_numeric,n` | Minimum numeric value |
| `max_numeric,n` | Maximum numeric value |
| `alpha_space` | Alphabetic characters and spaces only |
| `alpha_dash` | Alphabetic, dash, underscore |
| `numeric` | Numeric characters only |
| `url` | Valid URL format |
| `ip` | Valid IP address |
| `validate_ip` | Validate IP |
| `url_exists` | Check if URL exists |
| `matches,field` | Must match another field |
| `regex,pattern` | Regex pattern match |

## File Upload Methods

### Upload Generic File

```php
ValidationHelper::uploadFile(
    array $file,
    string $uploadDir,
    string $allowedMimeTypes = 'all',
    ?int $maxSize = null
): array
```

**Parameters:**
- `$file` - $_FILES array element
- `$uploadDir` - Directory to save file
- `$allowedMimeTypes` - 'image', 'document', 'all', or comma-separated MIME types
- `$maxSize` - Maximum file size in bytes (defaults to 10MB)

**Example:**
```php
$result = ValidationHelper::uploadFile(
    $_FILES['document'],
    'uploads/documents/',
    'document',
    5242880  // 5MB
);

if ($result['success']) {
    echo "File saved at: " . $result['file_path'];
    echo "File name: " . $result['file_name'];
} else {
    echo "Error: " . $result['message'];
}
```

### Upload Image

```php
ValidationHelper::uploadImage(
    array $file,
    string $uploadDir,
    ?int $maxWidth = 4096,
    ?int $maxHeight = 4096
): array
```

**Parameters:**
- `$file` - $_FILES array element
- `$uploadDir` - Directory to save image
- `$maxWidth` - Maximum image width (pixels)
- `$maxHeight` - Maximum image height (pixels)

**Example:**
```php
$result = ValidationHelper::uploadImage(
    $_FILES['avatar'],
    'uploads/avatars/',
    1920,  // Max width
    1920   // Max height
);

if ($result['success']) {
    echo "Image saved at: " . $result['file_path'];
    echo "Dimensions: " . $result['dimensions']['width'] . "x" . $result['dimensions']['height'];
} else {
    echo "Error: " . $result['message'];
}
```

### Return Format (File Upload)

```php
[
    'success' => bool,
    'message' => string,
    'file_path' => string|null,
    'file_name' => string|null
]
```

### Return Format (Image Upload)

```php
[
    'success' => bool,
    'message' => string,
    'file_path' => string|null,
    'dimensions' => [
        'width' => int,
        'height' => int
    ]|null
]
```

## File Retrieval Methods

### Get File

```php
ValidationHelper::getFile(string $fileName, string $directory): array
```

Securely retrieve a file from a directory with path traversal protection.

**Example:**
```php
$result = ValidationHelper::getFile('document.pdf', 'uploads/documents/');

if ($result['success']) {
    // Serve file to user
    header('Content-Type: ' . $result['mime_type']);
    readfile($result['file_path']);
} else {
    echo "File not found";
}
```

### Get Image

```php
ValidationHelper::getImage(string $imageName, string $directory): array
```

Retrieve an image with validation and dimension information.

**Example:**
```php
$result = ValidationHelper::getImage('avatar.jpg', 'uploads/avatars/');

if ($result['success']) {
    echo "Width: " . $result['dimensions']['width'];
    echo "Height: " . $result['dimensions']['height'];
    // Serve image
    header('Content-Type: ' . $result['dimensions']['mime_type']);
    readfile($result['file_path']);
} else {
    echo "Image not found";
}
```

### Delete File

```php
ValidationHelper::deleteFile(string $filePath): array
```

**Example:**
```php
$result = ValidationHelper::deleteFile('uploads/old_file.pdf');

if ($result['success']) {
    echo "File deleted";
} else {
    echo "Error: " . $result['message'];
}
```

## String Sanitization

### Sanitize Input

```php
ValidationHelper::sanitize(string $input, string $type = 'string'): string
```

**Types:**
- `'email'` - Sanitize email
- `'url'` - Sanitize URL
- `'html'` - HTML encode with HTML entities
- `'string'` - Trim and remove slashes (default)

**Example:**
```php
$email = ValidationHelper::sanitize($_POST['email'], 'email');
$url = ValidationHelper::sanitize($_POST['url'], 'url');
$text = ValidationHelper::sanitize($_POST['message'], 'html');
```

### Escape String

```php
ValidationHelper::escape(string $input): string
```

Escape string for safe database insertion.

**Example:**
```php
$escaped = ValidationHelper::escape($userInput);
```

## Validation Utilities

### Check Valid UUID

```php
ValidationHelper::isValidUuid(string $uuid): bool
```

**Example:**
```php
if (ValidationHelper::isValidUuid($id)) {
    // Valid UUID
}
```

### Check Valid Phone Number

```php
ValidationHelper::isValidPhone(string $phone): bool
```

Supports international format with +1-14 digit patterns.

**Example:**
```php
if (ValidationHelper::isValidPhone('+1234567890')) {
    // Valid phone
}
```

### Check Strong Password

```php
ValidationHelper::isStrongPassword(string $password): bool
```

**Requirements:**
- At least 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (!@#$%^&*()_+-=[]{}';:",.<>?/\|`~)

**Example:**
```php
if (ValidationHelper::isStrongPassword($password)) {
    // Password is strong
} else {
    echo "Password must contain uppercase, lowercase, numbers, and special characters";
}
```

## Allowed File Types

### Image Types
- JPEG (.jpg)
- PNG (.png)
- GIF (.gif)
- WebP (.webp)
- SVG (.svg)

### Document Types
- PDF (.pdf)
- Word (.doc, .docx)
- Excel (.xls, .xlsx)
- CSV (.csv)

### File Size Limits
- Images: 5MB
- Documents: 10MB
- Default: 10MB

## Security Features

1. **Path Traversal Protection**
   - File names are validated to prevent ../ attacks
   - Files are checked to ensure they're within the upload directory

2. **Random File Names**
   - Uploaded files are renamed with random hex strings
   - Original file names are not preserved on server

3. **MIME Type Validation**
   - MIME types are checked to prevent executable uploads
   - Image files are validated with getimagesize()

4. **Image Dimension Validation**
   - Images are checked for excessive dimensions
   - Prevents zip bomb or other oversized uploads

5. **File Permissions**
   - Uploaded files are set to 0644 (readable, not executable)

6. **Error Handling**
   - Sensitive errors are logged but not exposed to users
   - All errors are logged for debugging

## Error Messages

### Upload Errors
- "Invalid file upload" - Missing file array elements
- "File size exceeds maximum allowed" - File too large
- "File type not allowed" - MIME type not in whitelist
- "Failed to create upload directory" - Permission issues
- "Failed to save file" - Disk write error
- "File not found" - During retrieval
- "Invalid image file" - Image validation failed

## Complete Usage Example

```php
use Ordinatrack\Api\Helpers\ValidationHelper;

class UserController {
    
    public function register() {
        // Validate input
        $rules = ValidationHelper::registrationRules();
        $result = ValidationHelper::validate($_POST, $rules);
        
        if (!$result['is_valid']) {
            return ['success' => false, 'errors' => $result['errors']];
        }
        
        $data = $result['data'];
        
        // Handle avatar upload
        if (isset($_FILES['avatar'])) {
            $uploadResult = ValidationHelper::uploadImage(
                $_FILES['avatar'],
                'uploads/avatars/'
            );
            
            if (!$uploadResult['success']) {
                return ['success' => false, 'message' => $uploadResult['message']];
            }
            
            $data['avatar'] = $uploadResult['file_name'];
        }
        
        // Sanitize data
        $data['email'] = ValidationHelper::sanitize($data['email'], 'email');
        $data['first_name'] = ValidationHelper::sanitize($data['first_name'], 'string');
        
        // Create user in database
        // ...
        
        return ['success' => true, 'message' => 'User registered'];
    }
    
    public function updateProfile() {
        $rules = ValidationHelper::profileUpdateRules();
        $result = ValidationHelper::validate($_POST, $rules);
        
        if (!$result['is_valid']) {
            return ['success' => false, 'errors' => $result['errors']];
        }
        
        // Update user
        // ...
        
        return ['success' => true];
    }
    
    public function getAvatar($userId, $fileName) {
        $result = ValidationHelper::getImage($fileName, 'uploads/avatars/');
        
        if (!$result['success']) {
            return ['success' => false, 'message' => 'Avatar not found'];
        }
        
        return ['success' => true, 'file' => $result['file_path']];
    }
}
```

## Best Practices

1. **Always Validate Input** - Never trust user input
2. **Sanitize Before Output** - Prevent XSS attacks
3. **Use Prepared Statements** - Prevent SQL injection
4. **Check File Permissions** - Ensure upload directory is writable
5. **Log All Attempts** - Track validation failures
6. **Reject Unknown Types** - Use whitelist approach
7. **Limit File Sizes** - Prevent storage exhaustion
8. **Use HTTPS** - Protect data in transit
9. **Regenerate File Names** - Prevent file discovery
10. **Validate on Server** - Never trust client validation

## Performance Considerations

- GUMP validation is fast for most rules
- Image dimension checking requires file I/O
- Large file uploads should have reasonable timeouts
- Consider async processing for batch operations

## Troubleshooting

### Files Not Uploading
- Check directory permissions (must be writable)
- Check PHP upload_max_filesize setting
- Verify file size doesn't exceed maxSize parameter

### Image Validation Failing
- Ensure GD library is installed (for getimagesize)
- Check image file isn't corrupted
- Verify image dimensions don't exceed maximums

### MIME Type Issues
- Some MIME types may vary by system
- Use mime_content_type() for debugging
- Consider using finfo functions as alternative

## Support

For GUMP documentation: https://github.com/Wixel/GUMP
For PHP file handling: https://www.php.net/manual/en/features.file-upload.php
