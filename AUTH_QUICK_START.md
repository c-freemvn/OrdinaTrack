# OrdinaTrack Authentication - Quick Start Guide

## What Was Implemented

A complete authentication system for the OrdinaTrack application with three main flows:

### 1. Sign In
- Users enter email and password
- System validates against `/API/index.php/auth/login`
- On success: Stores JWT token and user info in localStorage
- Redirects to role-based dashboard (NHQ, Province, District, or Branch)

### 2. Sign Up (Two-Step Process)
**Step 1:** Account Details
- Secretary name
- Church email
- Role selection (NHQ, Province, District, Branch)
- Province selection (if applicable)
- District selection (if applicable)
- Branch name (if applicable)

**Step 2:** Create Password
- Password with strength validation (must include: uppercase, lowercase, number, special char)
- Confirm password
- Submits to `/API/index.php/auth/register`
- On success: Shows message and switches to signin tab with email pre-filled

### 3. Forgot Password
- User enters email address
- System sends password reset link via `/API/index.php/auth/request-password-reset`
- User receives email with reset instructions
- Can reset password from the link

## File Structure

```
App/
├── pages/
│   ├── signin.html          # Main auth page (signin + signup tabs)
│   ├── signup.html          # Redirect to signin.html#signup
│   └── [dashboard pages]
├── Assets/js/
│   ├── auth.js              # Province/district data & helper functions
│   ├── auth-api.js          # MAIN: API integration & form handling (NEW)
│   ├── authentication.js    # Legacy file (can be removed)
│   └── api.js               # Generic API wrapper
└── Assets/css/
    └── style.css            # Styling

API/
├── index.php                # Main router
└── src/Routes/
    └── auth.route.php       # Auth endpoint routing
```

## How It Works

### Sign In Flow
```
User enters credentials
         ↓
JavaScript validates form
         ↓
POST to /API/index.php/auth/login
         ↓
API validates credentials against database
         ↓
If valid: Returns JWT token + user info
         ↓
JavaScript stores in localStorage
         ↓
Redirect to dashboard
```

### Sign Up Flow
```
User fills Step 1 (account details)
         ↓
JavaScript validates all fields
         ↓
User clicks Next
         ↓
User fills Step 2 (password)
         ↓
JavaScript validates password strength
         ↓
POST to /API/index.php/auth/register
         ↓
API creates user in database
         ↓
JavaScript shows success message
         ↓
Redirect to signin tab with email pre-filled
```

### Forgot Password Flow
```
User enters email
         ↓
POST to /API/index.php/auth/request-password-reset
         ↓
API sends reset link to email
         ↓
JavaScript shows success message
         ↓
User clicks link in email to reset
```

## API Endpoints Used

All endpoints are under `/API/index.php/auth/`

### POST /auth/login
**Request:**
```json
{
  "email": "user@example.com",
  "password": "Password123!"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGc...",
    "user": {
      "id": 1,
      "email": "user@example.com",
      "first_name": "John",
      "role": "branch"
    },
    "refresh_token": "eyJhbGc..."
  }
}
```

### POST /auth/register
**Request:**
```json
{
  "email": "user@example.com",
  "password": "Password123!",
  "first_name": "John",
  "last_name": "Smith",
  "role": "Branch",
  "province_id": "5",
  "district_id": "12",
  "branch_name": "Main Branch"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user_id": 10,
    "email": "user@example.com"
  }
}
```

### POST /auth/request-password-reset
**Request:**
```json
{
  "email": "user@example.com",
  "reset_url_base": "http://localhost/App/pages/reset-password.html"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Password reset email sent"
}
```

## Testing the Implementation

### 1. Access Sign In Page
```
http://localhost/App/pages/signin.html
```

### 2. Test Sign In
- Use valid test credentials
- Watch the button show "Signing in..." state
- Verify localStorage has token

### 3. Test Sign Up
- Click "Sign Up" tab
- Fill in test account details
- Click "Next"
- Create password (e.g., "Test@123Pass")
- Click "Create Account"
- Verify success message and redirect to signin

### 4. Test Forgot Password
- Click "Forgotten password?" link
- Enter email
- Click "Send Reset Link"
- Check server logs or email service for reset link

### 5. Test Tab Navigation
- Click tabs to switch between signin/signup
- URL should change (no hash for signin, #signup for signup)
- Visit /App/pages/signup.html and verify redirect

## Storage

### localStorage Keys
- `auth_token` - JWT authentication token
- `user_info` - JSON-encoded user object
- `refresh_token` - Refresh token for getting new access tokens
- `token_expiry` - Timestamp when token expires (milliseconds)

These are used to:
- Maintain user session across page reloads
- Auto-redirect logged-in users away from auth page
- Store user info for display in dashboard
- Refresh expired tokens automatically

## Error Handling

The system handles and displays:
- Network errors
- Validation errors (empty fields, invalid email, weak password)
- API errors (user not found, password incorrect, email already registered)
- Form errors (passwords don't match, missing fields)

All errors display as Bootstrap alert messages under the form.

## Browser Compatibility

Requires:
- ES6 JavaScript support
- localStorage API
- Fetch API
- Promise support

Works on:
- Chrome 50+
- Firefox 44+
- Safari 10.1+
- Edge 14+

## Security Notes

1. **Passwords are validated on both frontend and backend**
   - Frontend: Must contain uppercase, lowercase, number, special char
   - Backend: Additional strength checks via ValidationHelper

2. **Tokens are stored in localStorage**
   - In production, consider using httpOnly cookies instead
   - Or implement a secure token exchange with backend

3. **All sensitive requests use HTTPS**
   - In development: HTTP works
   - In production: HTTPS is required

4. **CORS Protection**
   - API sets appropriate headers
   - Frontend uses same origin

## Next Steps / Future Enhancements

1. **Password Reset Completion Page**
   - Create `reset-password.html` to handle reset token verification
   - Implement `/auth/validate-reset-token` and `/auth/reset-password`

2. **Two-Factor Authentication**
   - Implement OTP/2FA via `/auth/request-otp`

3. **Email Verification**
   - Send verification email on signup
   - Implement `/auth/verify-email`

4. **Session Timeout**
   - Add idle timeout warning
   - Auto-logout after extended inactivity

5. **Social Login**
   - Add OAuth integration
   - Support Google, Microsoft, etc.

## Troubleshooting

**Q: Form not submitting?**
A: Check DevTools → Console for errors. Verify form IDs: signinEmail, signinPassword, signinBtn

**Q: Getting "Network error"?**
A: Verify API server is running at `/API/index.php`

**Q: Stuck on loading state?**
A: Check API response in DevTools → Network tab

**Q: Dropdown empty on signup?**
A: Verify `auth.js` loads before `auth-api.js`

**Q: Not redirecting to dashboard?**
A: Verify dashboard page exists at expected path for user role

## Support

For issues or questions:
1. Check browser console (F12)
2. Check API server logs
3. Review AUTH_QUICK_START.md and APP_AUTH_TEST.md
