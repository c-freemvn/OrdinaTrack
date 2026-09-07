# OrdinaTrack Authentication Implementation Testing Guide

## Overview
This document describes how to test the complete authentication system including signin, signup, and password reset flows.

## Files Modified/Created

### HTML Pages
1. **App/pages/signin.html** - Updated with proper form IDs and two-step signup
   - Fixed form IDs: `signinEmail`, `signinPassword`, `signinBtn`
   - Updated forgot password panel to request only email (sends reset link)
   - Integrated tab switching for signin/signup
   - Hash navigation support (#signup)

2. **App/pages/signup.html** - Redirect page
   - Now redirects to `/App/pages/signin.html#signup`
   - Fallback links for manual navigation

### JavaScript Files
1. **App/Assets/js/auth-api.js** - NEW comprehensive authentication handler
   - Handles login/register/password-reset via API endpoints
   - Tab switching and panel management
   - Session token storage and management
   - Form validation (email, password strength)
   - Auto-redirect if user already logged in

2. **App/Assets/js/auth.js** - Existing file with province/district data
   - Contains `PROVINCE_DISTRICT_MAP` and `PROVINCES` for dropdowns
   - Used by auth-api.js for form population

### API Endpoints
The implementation uses these API endpoints (already implemented in API/src/Routes/auth.route.php):

- `POST /API/index.php/auth/login`
  - Input: `{ email, password }`
  - Output: `{ success: bool, message: string, data: { token, user, refresh_token } }`

- `POST /API/index.php/auth/register`
  - Input: `{ email, password, first_name, last_name, role, province_id, district_id, branch_name }`
  - Output: `{ success: bool, message: string, data: { user_id, email } }`

- `POST /API/index.php/auth/request-password-reset`
  - Input: `{ email, reset_url_base }`
  - Output: `{ success: bool, message: string }`

## Testing Procedures

### 1. Sign In Flow
**URL:** `http://localhost/App/pages/signin.html`

Steps:
1. Enter a valid email in the email field
2. Enter correct password
3. Click "Sign In" button
4. Verify:
   - Button shows "Signing in..." state
   - Success message displays
   - User is redirected to appropriate dashboard
   - Token is stored in localStorage
   - User info is stored in localStorage

Expected Behavior:
- Valid credentials → Redirects to dashboard (role-based)
- Invalid credentials → Shows error message
- Missing fields → Shows validation error

### 2. Sign Up Flow
**URL:** `http://localhost/App/pages/signin.html#signup` or `http://localhost/App/pages/signup.html`

Step 1 - Account Details:
1. Enter Secretary Name: "John Smith"
2. Enter Church Email: "john.smith@church.org"
3. Select Role: "Branch"
4. Select Province: "DIOBU PROVINCIAL HQ"
5. Select District: "DIOBU CENTRAL"
6. Enter Branch Name: "Main Branch"
7. Click "Next"

Expected Behavior:
- Form validates all required fields
- Office Summary updates dynamically
- Step indicator shows "1. Account Details" → "2. Create Password"
- Step One form hides, Step Two shows

Step 2 - Create Password:
1. Enter Password: "Test@Password123"
2. Confirm Password: "Test@Password123"
3. Click "Create Account"

Expected Behavior:
- Validates passwords match
- Validates password strength (upper, lower, number, special char)
- Shows "Creating account..." state on button
- On success:
  - Shows success message
  - Clears form and resets to Step 1
  - Switches to signin tab
  - Pre-fills email field
- On error:
  - Shows error message
  - Keeps button enabled

### 3. Forgot Password Flow
**URL:** `http://localhost/App/pages/signin.html`

Steps:
1. Click "Forgotten password?" link
2. Forgot password panel expands
3. Enter valid email: "john.smith@church.org"
4. Click "Send Reset Link"

Expected Behavior:
- Button shows "Sending..." state
- On success:
  - Shows message: "Password reset email sent! Check your inbox..."
  - Shows info box about checking email
  - Panel closes after 3 seconds
  - Email contains reset link (check server logs or email service)
- On error:
  - Shows error message
  - Button remains clickable
  - Panel stays open

### 4. Tab Switching
**URL:** `http://localhost/App/pages/signin.html`

Steps:
1. Click "Sign In" tab
   - Verify: Signin panel shows
   - Verify: URL has no hash (or #signin)

2. Click "Sign Up" tab
   - Verify: Signup panel shows
   - Verify: URL shows #signup

3. Navigate to `#signup` directly in URL
   - Verify: Page opens with signup tab active

### 5. Session Management
**Prerequisites:** Must be logged in

Steps:
1. Open browser DevTools → Application → localStorage
2. Verify tokens are stored:
   - `auth_token` - JWT token
   - `user_info` - JSON user object
   - `token_expiry` - Token expiration timestamp

3. Try visiting signin page while logged in
   - Expected: Redirects to dashboard

### 6. Form Validation
**URL:** `http://localhost/App/pages/signin.html`

Test cases:
- Empty email + empty password → "Please enter both email and password"
- Invalid email format → "Please enter a valid email address"
- Signup: Empty fields → "Please fill in all required fields"
- Signup: Passwords don't match → "Passwords do not match"
- Signup: Password < 8 chars → "Password must be at least 8 characters long"
- Signup: Weak password → "Password must contain uppercase, lowercase, number and special character"

## Browser Console Checks

1. Open DevTools → Console
2. Look for any errors in red
3. Verify no "undefined" errors

Common console logs (informational):
- API calls being made
- Token storage operations
- Navigation operations

## Common Issues & Solutions

### Issue: "API error: Network error"
- Verify API server is running
- Check API endpoint URL in auth-api.js matches your setup
- Verify CORS if API is on different domain

### Issue: Form not submitting
- Check if JavaScript file loaded: DevTools → Sources → auth-api.js
- Verify form IDs match: signinEmail, signinPassword, signinBtn
- Check console for JavaScript errors

### Issue: Signup dropdown empty
- Verify auth.js file loads before auth-api.js
- Check if PROVINCE_DISTRICT_MAP exists: console → `window.PROVINCE_DISTRICT_MAP`

### Issue: Redirect not working
- Verify localStorage permission enabled
- Check if dashboard page exists at expected path
- Verify user role matches dashboard path

## Success Criteria

✅ Sign In form submits to API and redirects on success
✅ Sign Up validates both steps and submits to API
✅ Forgot Password sends email and shows confirmation
✅ Tab switching works and maintains state
✅ Session tokens stored in localStorage
✅ Auto-redirect if user already logged in
✅ All form validation messages display
✅ No console errors
✅ Buttons show loading states
✅ Province/District dropdowns populate correctly

## Next Steps (If Issues Found)

1. Check browser console for specific error messages
2. Check API server logs for backend errors
3. Verify all form IDs match between HTML and JavaScript
4. Test API endpoints directly with curl or Postman:
   ```bash
   curl -X POST http://localhost/API/index.php/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"test@test.com","password":"Password123!"}'
   ```

## Notes
- All sensitive data (passwords) should be sent over HTTPS in production
- Tokens should have reasonable expiration times
- Implement rate limiting on authentication endpoints
- Log all authentication attempts for security audit
