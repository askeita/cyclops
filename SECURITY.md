# Security measures implemented for Login and Signup forms

## Security improvements added:

### 1. CSRF Protection (Cross-Site Request Forgery)
- **Login.vue**: Retrieval and sending of CSRF token with each request
- **Signup.vue**: Same for registrations
- **CsrfController.php**: Endpoint `/api/csrf-token` to generate tokens

### 2. Rate Limiting
- **Login**: Maximum 5 attempts, 15-minute lockout
- **Signup**: Maximum 3 attempts, 30-minute lockout
- Storage in localStorage with timeout management

### 3. Enhanced client-side validation
- **Email**: Robust validation with format verification, length (254 chars max)
- **Login Password**: 8-128 characters
- **Signup Password**: 
  - 8-128 characters
  - At least 1 uppercase, 1 lowercase, 1 digit, 1 special character
  - Common password detection
  - Prevention of repeated characters (>2 times)

### 4. XSS Protection (Cross-Site Scripting)
- `sanitizeInput()` function to escape dangerous characters
- Input sanitization before sending to server

### 5. Secure error handling
- Generic error messages to prevent enumeration
- No exposure of sensitive information
- Remaining attempts counter for the user

### 6. UX/Security improvements
- Loading states to prevent double submissions
- Form disabling during lockout
- Automatic cleanup of sensitive fields after success
- Appropriate `autocomplete` attributes
- Maximum lengths defined on inputs

### 7. Security headers
- `X-Requested-With: XMLHttpRequest` to identify AJAX requests
- `X-CSRF-Token` for CSRF protection
- `credentials: 'same-origin'` for session cookies

## Usage

Components automatically retrieve their CSRF token on mount and handle security transparently. No additional client-side configuration is required.

## Server-side recommendations

For complete security, also implement:
- Server-side CSRF validation
- Server-level rate limiting (Redis/Memcache)
- Secure password hashing (bcrypt/Argon2)
- Mandatory HTTPS
- Security headers (CSP, HSTS, etc.)
