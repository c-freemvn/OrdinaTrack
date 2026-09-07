# AuthController Implementation Guide

## Overview

The `AuthController` is fully implemented and connected to the `AuthModel`. It follows a clean architecture where:
- **Input Data**: Passed through the controller's constructor
- **Processing**: Validation and business logic handled by the controller
- **Database Operations**: Delegated to `AuthModel`
- **Response Format**: Consistent JSON response structure

## Architecture

### Data Flow

```
HTTP Request
    ↓
index.php (Router)
    ↓
routes/auth.route.php (authRoutes function)
    ↓
AuthController (constructor receives data)
    ↓
AuthController method (register, login, etc.)
    ↓
ValidationHelper (validates input)
    ↓
AuthModel (interacts with database)
    ↓
JSON Response
```

## API Endpoints

### 1. Register User
**Endpoint**: `/auth/register`
**Method**: `POST`
**Auth Required**: No

**Request Data**:
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "first_name": "John",
  "last_name": "Doe"
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user_id": 1,
    "email": "user@example.com"
  }
}
```

**Error Response**:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": "Invalid email format",
    "password": "Password must be at least 8 characters"
  }
}
```

**Password Requirements**:
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (!@#$%^&*()_+\-=\[\]{};:\'",.<>?/\|`~)

---

### 2. Login User
**Endpoint**: `/auth/login`
**Method**: `POST`
**Auth Required**: No

**Request Data**:
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!"
}
```

**Success Response** (HTTP 200):
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": 1,
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe"
    }
  }
}
```

---

### 3. Request Password Reset
**Endpoint**: `/auth/request-password-reset`
**Method**: `POST`
**Auth Required**: No

**Request Data**:
```json
{
  "email": "user@example.com",
  "reset_url_base": "https://app.com/reset?token="
}
```

**Response** (HTTP 200):
```json
{
  "success": true,
  "message": "If the email exists, a reset link has been sent"
}
```

**Note**: Always returns success (even if email doesn't exist) for security reasons.

---

### 4. Validate Reset Token
**Endpoint**: `/auth/validate-reset-token`
**Method**: `POST`
**Auth Required**: No

**Request Data**:
```json
{
  "reset_token": "abc123def456..."
}
```

**Success Response**:
```json
{
  "success": true,
  "message": "Reset token is valid",
  "data": {
    "email": "user@example.com"
  }
}
```

**Error Response**:
```json
{
  "success": false,
  "message": "Invalid or expired reset token"
}
```

---

### 5. Reset Password
**Endpoint**: `/auth/reset-password`
**Method**: `POST`
**Auth Required**: No

**Request Data**:
```json
{
  "reset_token": "abc123def456...",
  "password": "NewPass123!"
}
```

**Success Response**:
```json
{
  "success": true,
  "message": "Password reset successful"
}
```

---

### 6. Change Password
**Endpoint**: `/auth/change-password`
**Method**: `POST`
**Auth Required**: Yes

**Request Data**:
```json
{
  "user_id": 1,
  "current_password": "OldPass123!",
  "new_password": "NewPass456!"
}
```

**Success Response**:
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

---

### 7. Get User Profile
**Endpoint**: `/auth/get-profile`
**Method**: `GET`
**Auth Required**: Yes

**Request Data**:
```json
{
  "user_id": 1
}
```

**Success Response**:
```json
{
  "success": true,
  "message": "User profile retrieved successfully",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "is_active": 1,
    "created_at": "2026-01-15 10:30:00",
    "last_login": "2026-01-20 15:45:00"
  }
}
```

---

### 8. Update User Profile
**Endpoint**: `/auth/update-profile`
**Method**: `PUT`
**Auth Required**: Yes

**Request Data**:
```json
{
  "user_id": 1,
  "first_name": "Jane",
  "last_name": "Smith",
  "email": "jane@example.com"
}
```

**Success Response**:
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "email": "jane@example.com",
    "first_name": "Jane",
    "last_name": "Smith",
    "is_active": 1,
    "created_at": "2026-01-15 10:30:00",
    "last_login": "2026-01-20 15:45:00"
  }
}
```

---

### 9. Deactivate Account
**Endpoint**: `/auth/deactivate`
**Method**: `POST`
**Auth Required**: Yes

**Request Data**:
```json
{
  "user_id": 1
}
```

**Success Response**:
```json
{
  "success": true,
  "message": "Account deactivated successfully"
}
```

---

### 10. Verify Token
**Endpoint**: `/auth/verify-token`
**Method**: `POST`
**Auth Required**: No

**Request Data**:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Success Response**:
```json
{
  "success": true,
  "message": "Token is valid",
  "data": {
    "iat": 1642345678,
    "exp": 1642432078,
    "user_id": 1,
    "type": "access"
  }
}
```

---

### 11. Refresh Access Token
**Endpoint**: `/auth/refresh-token`
**Method**: `POST`
**Auth Required**: No

**Request Data**:
```json
{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Success Response**:
```json
{
  "success": true,
  "message": "New token generated successfully",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user_id": 1
  }
}
```

---

## Usage Examples

### Example 1: Register a New User (Frontend)

```javascript
// JavaScript/Fetch
fetch('/auth/register', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    email: 'john@example.com',
    password: 'SecurePass123!',
    first_name: 'John',
    last_name: 'Doe'
  })
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    console.log('User registered:', data.data.user_id);
  } else {
    console.error('Registration failed:', data.errors);
  }
});
```

### Example 2: Login (Frontend)

```javascript
fetch('/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    email: 'john@example.com',
    password: 'SecurePass123!'
  })
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    // Store token in localStorage
    localStorage.setItem('auth_token', data.data.token);
    console.log('Logged in as:', data.data.user.first_name);
  } else {
    console.error('Login failed:', data.message);
  }
});
```

### Example 3: Using the Token in Authenticated Requests

```javascript
const token = localStorage.getItem('auth_token');

fetch('/auth/get-profile', {
  method: 'GET',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify({
    user_id: 1
  })
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    console.log('User profile:', data.data);
  }
});
```

### Example 4: Direct Controller Usage (Backend/Testing)

```php
<?php
use Ordinatrack\Api\Controller\AuthController;

// Simulate registration request
$registerData = [
    'email' => 'test@example.com',
    'password' => 'TestPass123!',
    'first_name' => 'Test',
    'last_name' => 'User'
];

$controller = new AuthController($registerData);
$response = $controller->register();

// Response will be:
// [
//     'success' => true,
//     'message' => 'User registered successfully',
//     'data' => ['user_id' => 1, 'email' => 'test@example.com']
// ]
?>
```

---

## Key Features

### ✅ Input Validation
- Uses GUMP validation library
- Pre-built rule sets for common scenarios
- Strong password requirements
- Email format validation

### ✅ Security
- Bcrypt password hashing (cost: 12)
- JWT token-based authentication
- Password reset tokens (1 hour expiry)
- Account deactivation support
- Secure password comparison

### ✅ Error Handling
- Consistent error response format
- Field-level validation errors
- Security-conscious error messages
- Comprehensive logging

### ✅ Database Integration
- PDO-based database access
- Transaction support (via AuthModel)
- Last login tracking
- Account deactivation flag

### ✅ Email Integration
- Welcome emails on registration
- Password reset emails
- Configurable mail service

### ✅ JWT Token Management
- Access tokens (default: 1 hour)
- Refresh tokens (default: 7 days)
- Token verification
- Token refresh functionality

---

## Configuration

The controller uses environment variables defined in `.env`:

```env
# JWT Configuration
JWT_SECRET=your_secret_key_here
JWT_ALGORITHM=HS256
JWT_EXPIRATION=86400          # 1 day in seconds
JWT_REFRESH_EXPIRATION=604800 # 7 days in seconds

# Password Hashing
BCRYPT_COST=12

# Application URL (for password reset links)
APP_URL=https://app.example.com

# Mail Configuration
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=OrdinaTrack
```

---

## Response Format

All endpoints follow a consistent response format:

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // endpoint-specific data
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": "Field-specific error message"
  }
}
```

---

## Testing the Implementation

### Test Registration
```bash
curl -X POST http://localhost/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "TestPass123!",
    "first_name": "Test",
    "last_name": "User"
  }'
```

### Test Login
```bash
curl -X POST http://localhost/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "TestPass123!"
  }'
```

### Test Protected Endpoint
```bash
curl -X GET http://localhost/auth/get-profile \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1}'
```

---

## Files Created/Modified

### Created:
1. `/API/src/Controller/AuthController.php` - Main controller with 11 action methods
2. `/API/routes/auth.route.php` - Route handler that integrates with the router

### Already Existed:
- `/API/src/Model/AuthModel.php` - Model with all database operations
- `/API/src/Helpers/ValidationHelper.php` - Validation and input sanitization

---

## Next Steps

1. **Frontend Integration**: Connect your frontend forms to these endpoints
2. **Middleware Setup**: Ensure authentication middleware is properly configured
3. **Testing**: Run the curl examples above to verify functionality
4. **Database Migration**: Ensure the `users` table exists with required columns
5. **Email Configuration**: Set up SMTP credentials in `.env` for password reset and welcome emails

---

## Troubleshooting

### "Undefined method 'get_errors'"
This was already fixed in ValidationHelper.php. The GUMP method is `errors()`, not `get_errors()`.

### "User not found" on login
Check that:
- User exists in database
- User account is active (`is_active = 1`)
- Email is correctly stored

### "Invalid or expired reset token"
Check that:
- Token hasn't exceeded 1-hour expiry
- Token matches the one in the database
- Token hasn't been used already (cleared after reset)

### "Password must contain uppercase, lowercase, number and special character"
Password requirements are strict for security. Example valid password:
`SecurePass123!`

---

## Architecture Decisions

1. **Constructor Injection**: Input data is passed through the constructor, making it explicit what data the controller needs
2. **Consistent Response Format**: All endpoints return JSON in the same structure
3. **Validation First**: Input validation happens before any database operations
4. **Security by Default**: Strong passwords, bcrypt hashing, JWT tokens
5. **Clear Separation**: Controller handles validation/orchestration, Model handles database
6. **Comprehensive Logging**: All operations are logged for audit trails
