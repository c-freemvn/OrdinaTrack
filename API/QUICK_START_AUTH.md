# Quick Start: Using AuthController

## The Pattern

All controllers follow this pattern:
```php
// 1. Create controller with input data in constructor
$controller = new AuthController($data);

// 2. Call the action method
$response = $controller->actionName();

// 3. Return JSON response
echo json_encode($response);
```

## Available Actions

### 1. Register
```php
$data = [
    'email' => 'user@example.com',
    'password' => 'SecurePass123!',
    'first_name' => 'John',
    'last_name' => 'Doe'
];
$controller = new AuthController($data);
$response = $controller->register();
```

### 2. Login
```php
$data = [
    'email' => 'user@example.com',
    'password' => 'SecurePass123!'
];
$controller = new AuthController($data);
$response = $controller->login();
```

### 3. Request Password Reset
```php
$data = [
    'email' => 'user@example.com',
    'reset_url_base' => 'https://app.com/reset?token='
];
$controller = new AuthController($data);
$response = $controller->requestPasswordReset();
```

### 4. Validate Reset Token
```php
$data = ['reset_token' => 'abc123...'];
$controller = new AuthController($data);
$response = $controller->validateResetToken();
```

### 5. Reset Password
```php
$data = [
    'reset_token' => 'abc123...',
    'password' => 'NewPass123!'
];
$controller = new AuthController($data);
$response = $controller->resetPassword();
```

### 6. Change Password
```php
$data = [
    'user_id' => 1,
    'current_password' => 'OldPass123!',
    'new_password' => 'NewPass456!'
];
$controller = new AuthController($data);
$response = $controller->changePassword();
```

### 7. Get Profile
```php
$data = ['user_id' => 1];
$controller = new AuthController($data);
$response = $controller->getProfile();
```

### 8. Update Profile
```php
$data = [
    'user_id' => 1,
    'first_name' => 'Jane',
    'last_name' => 'Smith',
    'email' => 'jane@example.com'
];
$controller = new AuthController($data);
$response = $controller->updateProfile();
```

### 9. Deactivate Account
```php
$data = ['user_id' => 1];
$controller = new AuthController($data);
$response = $controller->deactivateAccount();
```

### 10. Verify Token
```php
$data = ['token' => 'eyJ0eXAi...'];
$controller = new AuthController($data);
$response = $controller->verifyToken();
```

### 11. Refresh Token
```php
$data = ['refresh_token' => 'eyJ0eXAi...'];
$controller = new AuthController($data);
$response = $controller->refreshToken();
```

## Response Format

```php
// Success
[
    'success' => true,
    'message' => 'Operation successful',
    'data' => [/* operation data */]
]

// Error
[
    'success' => false,
    'message' => 'Error description',
    'errors' => ['field' => 'Error message']
]
```

## Via API Endpoint

```bash
# Register
curl -X POST http://localhost/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email":"user@example.com",
    "password":"SecurePass123!",
    "first_name":"John",
    "last_name":"Doe"
  }'

# Login
curl -X POST http://localhost/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email":"user@example.com",
    "password":"SecurePass123!"
  }'

# Get Profile
curl -X GET http://localhost/auth/get-profile \
  -H "Content-Type: application/json" \
  -d '{"user_id":1}'
```

## That's It!

Your authentication system is ready. Just:
1. Pass data to the constructor
2. Call the appropriate method
3. Get JSON response back

The controller handles:
- ✅ Input validation
- ✅ Database operations
- ✅ Password hashing
- ✅ JWT tokens
- ✅ Error handling
- ✅ Logging
