# OrdinaTrack Authentication - Workflow Diagrams

## 1. Sign In Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    SIGN IN PAGE                             │
│            /App/pages/signin.html                           │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ User enters email & password
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              FRONTEND VALIDATION                            │
│  - Check for empty fields                                   │
│  - Validate email format                                    │
└──────────────────────┬──────────────────────────────────────┘
                       │
         ┌─────────────┴─────────────┐
         │                           │
      ERROR                       VALID
         │                           │
         ▼                           ▼
    ┌─────────┐         POST /API/index.php/auth/login
    │  Display│         {email, password}
    │  Error  │                   │
    │ Message │                   ▼
    └─────────┘         ┌──────────────────────────┐
                        │   API - AuthController   │
                        │    ::login()             │
                        │  - Validate credentials  │
                        │  - Hash password check   │
                        │  - Generate JWT token    │
                        └──────────────┬───────────┘
                                      │
                        ┌─────────────┴────────────┐
                        │                          │
                    INVALID                     VALID
                        │                          │
                        ▼                          ▼
                ┌─────────────────┐    ┌──────────────────────┐
                │  Return Error   │    │ Return JWT Token +   │
                │  Response       │    │ User Info            │
                └────────┬────────┘    └──────────┬───────────┘
                         │                         │
                         │ {success: false}        │ {success: true}
                         │ message: "..."          │ {token, user}
                         │                         │
                         ▼                         ▼
                ┌──────────────────┐   ┌───────────────────────┐
                │ Display Error    │   │ Store in localStorage │
                │ Message          │   │ - auth_token          │
                │ Button Re-enabled│   │ - user_info           │
                └──────────────────┘   │ - token_expiry        │
                                       └───────────┬───────────┘
                                                   │
                                                   ▼
                                       ┌──────────────────────┐
                                       │ Display Success Msg  │
                                       │ "Redirecting..."     │
                                       └───────────┬──────────┘
                                                   │
                                                   ▼ (after 1.5 sec)
                                       ┌──────────────────────┐
                                       │ Determine Dashboard  │
                                       │ Based on User Role   │
                                       │ - NHQ → nhq-dash     │
                                       │ - Province → prov-   │
                                       │ - District → dist-   │
                                       │ - Branch → branch-   │
                                       └───────────┬──────────┘
                                                   │
                                                   ▼
                                       ┌──────────────────────┐
                                       │ Redirect to          │
                                       │ Dashboard            │
                                       └──────────────────────┘
```

---

## 2. Sign Up Flow

```
┌──────────────────────────────────────────────────────────────┐
│           SIGN UP PAGE - STEP 1 (Details)                    │
│            /App/pages/signin.html#signup                     │
└────────────────┬─────────────────────────────────────────────┘
                 │
                 │ User enters:
                 │ - Secretary Name
                 │ - Email
                 │ - Role Selection
                 │ - Location (Province/District/Branch)
                 │
                 ▼
         ┌──────────────────────┐
         │ FRONTEND VALIDATION  │
         │ - All required fields│
         │ - Email format       │
         │ - Location hierarchy │
         └────────┬─────────────┘
                  │
        ┌─────────┴────────────┐
        │                      │
     ERROR                  VALID
        │                      │
        ▼                      ▼
   ┌─────────┐           [Next Button]
   │ Display │                │
   │ Error   │           Moves to STEP 2
   └─────────┘
                 │
                 ▼
    ┌──────────────────────────────────────┐
    │  SIGN UP PAGE - STEP 2 (Password)    │
    │  Step 1 fields hidden, Step 2 shown  │
    └────────────┬───────────────────────────┘
                 │
                 │ User enters:
                 │ - Password
                 │ - Confirm Password
                 │
                 ▼
         ┌──────────────────────┐
         │ FRONTEND VALIDATION  │
         │ - Passwords match    │
         │ - Length >= 8 chars  │
         │ - Password strength: │
         │   • Uppercase letter │
         │   • Lowercase letter │
         │   • Number           │
         │   • Special char     │
         └────────┬─────────────┘
                  │
        ┌─────────┴─────────────┐
        │                       │
     ERROR                   VALID
        │                       │
        ▼                       ▼
   ┌─────────┐    [Create Account Button]
   │ Display │             │
   │ Error   │        POST /API/index.php/
   │ Message │        auth/register
   └─────────┘        {email, password,
                       first_name, last_name,
                       role, province_id,
                       district_id,
                       branch_name}
                           │
                           ▼
                ┌─────────────────────────┐
                │ API - AuthController    │
                │  ::register()           │
                │ - Validate all fields   │
                │ - Check email unique    │
                │ - Validate password     │
                │ - Hash password         │
                │ - Create user in DB     │
                └────────────┬────────────┘
                             │
                ┌────────────┴────────────┐
                │                         │
            DUPLICATE              VALID/NEW
            EMAIL                    USER
                │                     │
                ▼                     ▼
        ┌──────────────┐    ┌──────────────────┐
        │ Return Error │    │ Return Success   │
        │ "Email       │    │ {success: true,  │
        │ exists"      │    │  user_id, email} │
        └────┬─────────┘    └────────┬─────────┘
             │                       │
      {success: false}        {success: true}
             │                       │
             ▼                       ▼
        ┌──────────────┐    ┌──────────────────┐
        │ Display      │    │ Show Success Msg │
        │ Error Msg    │    │ "Account         │
        │ Keep Form    │    │ Created!"        │
        └──────────────┘    └────────┬─────────┘
                                     │
                    ┌────────────────┘
                    │
                    ▼ (after 1.5 sec)
            ┌──────────────────┐
            │ Clear form       │
            │ Reset to Step 1  │
            │ Switch to signin │
            │ Pre-fill email   │
            │ Focus password   │
            └──────────────────┘
```

---

## 3. Forgot Password Flow

```
┌────────────────────────────────────────────────────────────┐
│              SIGN IN PAGE                                  │
│          [Forgotten password?] link clicked                │
└────────────────┬─────────────────────────────────────────┘
                 │
                 ▼
    ┌──────────────────────────────────┐
    │ Forgot Password Panel Expands    │
    │ Email input field shown          │
    │ Panel was hidden (d-none)        │
    └────────────┬─────────────────────┘
                 │
                 │ User enters email
                 │
                 ▼
         ┌──────────────────────┐
         │ FRONTEND VALIDATION  │
         │ - Email not empty    │
         │ - Email format valid │
         └────────┬─────────────┘
                  │
        ┌─────────┴────────────┐
        │                      │
     ERROR                  VALID
        │                      │
        ▼                      ▼
   ┌──────────┐         [Send Reset Link]
   │ Display  │              │
   │ Error    │         POST /API/index.php/
   │          │         auth/request-password-reset
   └──────────┘         {email, reset_url_base}
                             │
                             ▼
                ┌──────────────────────────┐
                │ API - AuthController     │
                │  ::requestPasswordReset()│
                │ - Validate email exists  │
                │ - Generate reset token   │
                │ - Send email with link   │
                │ - Log request            │
                └────────────┬─────────────┘
                             │
                ┌────────────┴────────────┐
                │                         │
            NOT FOUND                  SENT
                │                        │
                ▼                        ▼
        ┌──────────────┐     ┌────────────────────┐
        │ Return Error │     │ Return Success     │
        │ "Email not   │     │ {success: true,    │
        │ found"       │     │  message: "..."}   │
        └────┬─────────┘     └────────┬───────────┘
             │                        │
             ▼                        ▼
        ┌──────────────┐     ┌────────────────────┐
        │ Display      │     │ Display Success:   │
        │ Error Msg    │     │ "Check your inbox" │
        │ Keep panel   │     │ "Reset link sent"  │
        │ open         │     └────────┬───────────┘
        └──────────────┘              │
                              ┌───────┴───────┐
                              │               │
                    User receives      Panel closes
                    email with        after 3 seconds
                    reset link             │
                              │            ▼
                              │       ┌──────────────┐
                              │       │ Form reset   │
                              │       │ Panel hidden │
                              │       └──────────────┘
                              │
                              ▼
                    ┌──────────────────────┐
                    │ User clicks link in  │
                    │ email (future)       │
                    │ Goes to reset page   │
                    │ Enter new password   │
                    │ Verifies token       │
                    │ Sets new password    │
                    └──────────────────────┘
```

---

## 4. Tab Navigation & Session Flow

```
                    ┌──────────────────────┐
                    │   SIGN IN PAGE       │
                    │   signin.html        │
                    │                      │
                    │ [Sign In] [Sign Up]  │
                    │    TAB      TAB      │
                    └────────┬─────────────┘
                             │
              ┌──────────────┴──────────────┐
              │                             │
          SIGN IN TAB              SIGN UP TAB
          CLICKED                  CLICKED
              │                             │
              ▼                             ▼
    ┌───────────────────┐      ┌─────────────────────┐
    │ signin-panel      │      │ signup-panel        │
    │ shows (remove     │      │ shows (remove d-no) │
    │ d-none)           │      │                     │
    │ signup-panel      │      │ signin-panel hidden │
    │ hidden (add       │      │ (add d-none)        │
    │ d-none)           │      │                     │
    │                   │      │ URL: #signup        │
    │ URL: no hash or   │      │                     │
    │ history.replace   │      │ Step indicator      │
    └───────┬───────────┘      │ updated             │
            │                  └──────────┬──────────┘
            │                            │
            ▼                            ▼
    ┌─────────────────┐        ┌──────────────────┐
    │ Email field     │        │ Step 1:          │
    │ Password field  │        │ Account Details  │
    │ Sign In button  │        │                  │
    │ Forgotten pwd   │        │ Step 2:          │
    │ link            │        │ Password         │
    └─────────────────┘        │ (hidden)         │
                               └──────────────────┘


    ┌──────────────────────────────────────────┐
    │     SESSION MANAGEMENT (localStorage)    │
    │                                          │
    │  On Login Success:                       │
    │  ├─ auth_token = "eyJhbGc..."           │
    │  ├─ user_info = {id, email, role}       │
    │  ├─ refresh_token = "eyJhbGc..."        │
    │  └─ token_expiry = 1234567890000        │
    │                                          │
    │  On Page Load:                           │
    │  ├─ Check if token exists                │
    │  ├─ Check if not expired                 │
    │  └─ If valid: Redirect to dashboard      │
    │     If invalid: Stay on auth page        │
    │                                          │
    │  On Logout:                              │
    │  ├─ Remove all tokens                    │
    │  └─ Redirect to signin                   │
    └──────────────────────────────────────────┘
```

---

## 5. Data Flow Diagram

```
┌─────────────────────────────────────┐
│         USER BROWSER                │
│                                     │
│  signin.html                        │
│  ├─ auth.js (data)                  │
│  └─ auth-api.js (handlers)          │
│      ├─ Form validation             │
│      ├─ Event binding               │
│      └─ localStorage management     │
└────────────────┬────────────────────┘
                 │
                 │ JSON over HTTPS/HTTP
                 │ POST requests
                 │
                 ▼
┌────────────────────────────────────────────┐
│            API SERVER                      │
│                                            │
│  /API/index.php (Router)                   │
│  ├─ Parses request                         │
│  ├─ Routes to /auth                        │
│  └─ Handles auth responses                 │
│                                            │
│  /API/src/Routes/auth.route.php            │
│  ├─ login → AuthController::login()        │
│  ├─ register → AuthController::register()  │
│  └─ request-password-reset → ...          │
│                                            │
│  /API/src/Controller/AuthController.php    │
│  ├─ Validates input                        │
│  ├─ Calls AuthModel methods                │
│  └─ Returns JSON responses                 │
│                                            │
│  /API/src/Model/AuthModel.php              │
│  ├─ Queries database                       │
│  ├─ Password hashing                       │
│  ├─ JWT generation                         │
│  └─ Email sending                          │
└────────────┬──────────────────────────────┘
             │
             ▼
    ┌─────────────────────────────┐
    │    DATABASE & SERVICES      │
    │                             │
    │ Users table                 │
    │ ├─ id, email, password      │
    │ ├─ first_name, last_name    │
    │ ├─ role, created_at         │
    │ └─ updated_at               │
    │                             │
    │ Reset Tokens table          │
    │ ├─ token, user_id           │
    │ ├─ expires_at               │
    │ └─ created_at               │
    │                             │
    │ Mail Service                │
    │ └─ Sends password reset     │
    │    emails                   │
    └─────────────────────────────┘
```

---

## 6. State Machine: Sign Up Form

```
                          START
                            │
                            ▼
                  ┌──────────────────┐
                  │ SIGNIN_TAB_ACTIVE│
                  │ SIGNUP_HIDDEN    │
                  └────────┬─────────┘
                           │
              [User clicks Sign Up tab]
                           │
                           ▼
                  ┌──────────────────┐
                  │ SIGNUP_TAB_ACTIVE│
                  │ STEP_1_VISIBLE   │
                  │ STEP_2_HIDDEN    │
                  └────────┬─────────┘
                           │
          ┌────────────────┼────────────────┐
          │                │                │
     [Back btn]      [Next btn]      [Cancel btn]
          │          (validation)          │
          │                │                │
    [Step 1]          [Valid]            [Reset]
    Stays on      [Invalid]            [to signin]
    step 1        Show error                │
          │           │                     ▼
          │           ▼              ┌──────────────┐
          │    ┌────────────┐        │ SIGNIN_ACTIVE│
          │    │ Stay in    │        └──────────────┘
          │    │ STEP_1     │
          │    └────────────┘
          │           │
          │     [Next] + [Valid]
          │           │
          └───────────┼─────────┐
                      │         │
                      ▼         │
            ┌──────────────────┐│
            │ STEP_2_VISIBLE   ││
            │ STEP_1_HIDDEN    ││
            │ STEPBAR UPDATED  ││
            └────────┬─────────┘│
                     │          │
      ┌──────────────┼──────────┘
      │              │
  [Back]      [Submit]
      │         (validation)
      │          │
      ▼      [Valid]
  STEP_1      [Invalid]
  SHOWS       Show error
      │          │
      │          ▼
      │     ┌──────────┐
      │     │ Stay in  │
      │     │ STEP_2   │
      │     └──────────┘
      │          │
      └──────────┴────────────┐
                              │
                    [Submit] + [Valid]
                              │
                              ▼
                    ┌──────────────────┐
                    │ POST to API      │
                    │ /auth/register   │
                    └────────┬─────────┘
                             │
                    ┌────────┴──────────┐
                    │                   │
                [Success]           [Error]
                    │                   │
                    ▼                   ▼
            ┌──────────────┐    ┌──────────────┐
            │ Show success │    │ Show error   │
            │ Reset form   │    │ Keep form    │
            │ to STEP_1    │    │ in STEP_2    │
            │ Switch tab   │    │ Re-enable btn│
            │ to signin    │    └──────────────┘
            │ Pre-fill     │
            │ email        │
            └────────┬─────┘
                     │
              (after delay)
                     │
                     ▼
              ┌──────────────┐
              │ SIGNIN_ACTIVE│
              │ EMAIL_FILLED │
              └──────────────┘
```

---

## 7. Error Handling Flow

```
┌──────────────────────────────────────────┐
│           USER ACTION                    │
│  (Submit form, API call, etc)            │
└──────────────┬───────────────────────────┘
               │
               ▼
    ┌──────────────────────┐
    │  ERROR OCCURS?       │
    └──┬───────────────────┘
       │
       ├─ NO → Continue normal flow
       │
       └─ YES → Error handling
           │
           ▼
    ┌──────────────────────────────────┐
    │ ERROR TYPE?                      │
    └──┬───────────────────────────────┘
       │
       ├─ VALIDATION ERROR
       │  ├─ Empty field
       │  ├─ Invalid email
       │  ├─ Weak password
       │  ├─ Password mismatch
       │  └─ Missing required field
       │
       ├─ API ERROR
       │  ├─ Network error
       │  ├─ Server error (500)
       │  ├─ Not found (404)
       │  └─ Invalid request (400)
       │
       ├─ BUSINESS LOGIC ERROR
       │  ├─ Email already registered
       │  ├─ Invalid credentials
       │  ├─ User not found
       │  ├─ Token expired
       │  └─ Permission denied
       │
       └─ PARSING ERROR
          └─ JSON parse error
               │
               ▼
    ┌──────────────────────────────────┐
    │ DETERMINE ERROR MESSAGE          │
    │ - Use response.message if exists │
    │ - Use predefined message         │
    │ - Use generic fallback           │
    └──────────┬───────────────────────┘
               │
               ▼
    ┌──────────────────────────────────┐
    │ DISPLAY TO USER                  │
    │ showAuthStatus(msg, 'danger')    │
    │ - Show Bootstrap alert           │
    │ - Display in #authStatus         │
    │ - Color: Red (danger class)      │
    └──────────┬───────────────────────┘
               │
               ▼
    ┌──────────────────────────────────┐
    │ RE-ENABLE FORM                   │
    │ - Enable submit button           │
    │ - Restore original button text   │
    │ - Allow form input               │
    └──────────┬───────────────────────┘
               │
               ▼
    ┌──────────────────────────────────┐
    │ USER RETRIES OR FIXES            │
    │ (Correct input, try again, etc)  │
    └──────────────────────────────────┘
```

---

## Key State Variables

### In auth-api.js:

```javascript
// Constants (in AUTH_CONFIG)
- TOKEN_KEY = "auth_token"
- USER_INFO_KEY = "user_info"
- REFRESH_TOKEN_KEY = "refresh_token"
- TOKEN_EXPIRY_KEY = "token_expiry"

// Form States (DOM attributes)
- Form visibility: .d-none class
- Step indicators: Active class on badges
- Button states: disabled attribute, textContent change
- Panel visibility: .d-none on panels

// Data in localStorage
- auth_token: JWT token string
- user_info: JSON stringified user object
- refresh_token: Refresh token string
- token_expiry: Millisecond timestamp
```

---

## Integration Summary

```
User Browser
    │
    ├─ HTML (signin.html)
    │  └─ Form inputs, panels, buttons
    │
    ├─ CSS (style.css)
    │  └─ Bootstrap 5 classes
    │
    ├─ JavaScript
    │  ├─ auth.js (data: provinces, districts)
    │  └─ auth-api.js (handlers, validation, API)
    │
    └─ localStorage (session tokens)
         │
         └─ Persists across page reloads

              ↓ HTTPS/JSON

API Server (index.php)
    │
    ├─ Routes: /auth/login, /register, /request-password-reset
    │
    ├─ AuthController
    │  └─ Validation, token generation, responses
    │
    ├─ AuthModel
    │  └─ Database operations, password hashing
    │
    └─ Database
         └─ Users, tokens, logs

              ↓ Email Service

Email (for password reset)
    └─ Reset link with token
```

---

## Deployment Pipeline

```
Development
    ↓
Testing (manual + automated)
    ├─ Form validation tests
    ├─ API endpoint tests
    ├─ Session management tests
    └─ Error handling tests
    ↓
Staging
    ├─ Full integration test
    ├─ Performance test
    ├─ Security audit
    └─ User acceptance test
    ↓
Production
    ├─ Deploy API
    ├─ Deploy frontend
    ├─ Verify endpoints
    └─ Monitor logs
    ↓
Post-Deployment
    ├─ Smoke tests
    ├─ User testing
    ├─ Bug fix cycle
    └─ Optimization
```

---

*These diagrams show the complete authentication system workflow. For implementation details, see auth-api.js source code.*
