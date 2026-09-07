# Email Service Setup Guide

This document describes the email service implementation for the OrdinaTrack API using PHPMailer.

## Overview

The API uses PHPMailer for sending emails. Configuration is managed through environment variables, allowing for flexible setup across development, staging, and production environments.

## Installation

PHPMailer has been added to `composer.json`:

```json
"phpmailer/phpmailer": "^6.9"
```

Run `composer install` to install the dependency.

## Configuration

Email settings are managed via environment variables in the `.env` file:

```env
# Email Configuration
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_FROM_ADDRESS=noreply@ordinatrack.com
MAIL_FROM_NAME=OrdinaTrack
```

### Supported SMTP Providers

The service works with any SMTP provider. Here are some popular options:

#### Development/Testing
- **Mailtrap** (Recommended for development)
  - URL: https://mailtrap.io
  - Host: `smtp.mailtrap.io`
  - Port: `587` or `2525`
  - Security: TLS

#### Production
- **SendGrid**
  - Host: `smtp.sendgrid.net`
  - Port: `587`
  - Username: `apikey`
  - Password: Your SendGrid API key
  
- **AWS SES**
  - Host: `email-smtp.{region}.amazonaws.com`
  - Port: `587`
  - Use your SMTP credentials

- **Gmail**
  - Host: `smtp.gmail.com`
  - Port: `587`
  - Username: Your Gmail address
  - Password: Your app-specific password
  - Note: Enable "Less secure app access" or use app passwords

## Email Service Class

Located at: `src/Services/MailService.php`

### Available Methods

#### Basic Email Sending

```php
use Ordinatrack\Api\Services\MailService;

$mailService = new MailService();

// Send a simple email
$result = $mailService->send(
    'user@example.com',
    'Welcome!',
    '<h1>Hello!</h1><p>Welcome to OrdinaTrack.</p>'
);
```

#### Send to Multiple Recipients

```php
$recipients = ['user1@example.com', 'user2@example.com'];

$result = $mailService->sendToMultiple(
    $recipients,
    'Group Notification',
    '<h1>Important Update</h1>'
);
```

#### Send with Attachments

```php
$attachments = [
    '/path/to/file1.pdf',
    '/path/to/file2.csv'
];

$result = $mailService->sendWithAttachments(
    'user@example.com',
    'Your Documents',
    '<h1>Here are your files</h1>',
    $attachments
);
```

#### Authentication Integration

The `AuthModel` automatically sends emails during authentication flows:

```php
// Password reset email sent automatically
AuthModel::requestPasswordReset('user@example.com', 'https://app.com/reset?token=');

// Welcome email sent automatically on registration
AuthModel::register([
    'email' => 'newuser@example.com',
    'password' => 'securepassword',
    'first_name' => 'John',
    'last_name' => 'Doe'
]);
```

### Pre-built Email Templates

The service includes ready-to-use templates for common scenarios:

#### Password Reset Email

```php
$mailService->sendPasswordResetEmail(
    'user@example.com',
    'John Doe',
    'token123abc',
    'https://app.com/reset?token=token123abc'
);
```

#### Welcome Email

```php
$mailService->sendWelcomeEmail(
    'user@example.com',
    'John Doe',
    'https://app.com/login'
);
```

#### Email Verification

```php
$mailService->sendVerificationEmail(
    'user@example.com',
    'John Doe',
    'https://app.com/verify?token=verificationtoken'
);
```

#### Account Locked Notification

```php
$mailService->sendAccountLockedNotification(
    'user@example.com',
    'John Doe',
    'support@ordinatrack.com'
);
```

## Return Format

All email methods return an array:

```php
[
    'success' => true,    // bool
    'message' => 'Email sent successfully'  // string
]
```

### Example Usage

```php
$result = $mailService->send('user@example.com', 'Hello', '<p>Hi there</p>');

if ($result['success']) {
    echo "Email sent: " . $result['message'];
} else {
    echo "Failed: " . $result['message'];
}
```

## Error Handling

Errors are logged automatically to PHP's error log. The service doesn't throw exceptions for failed sends (except during initialization).

### Debug Mode

When `APP_DEBUG=true`, PHPMailer debug output is shown (SMTP traffic).
When `APP_DEBUG=false`, debug output is suppressed.

To enable detailed debugging:

```php
$mailService = new MailService();
// Errors are logged to error_log()
```

## Custom Email Templates

To create custom email templates, extend the `MailService` class:

```php
use Ordinatrack\Api\Services\MailService;

class CustomMailService extends MailService {
    public function sendCustomEmail($to, $data) {
        $body = '<h1>Custom Template</h1>';
        // Build your HTML body
        return $this->send($to, 'Subject', $body);
    }
}
```

Or create a new method in the existing class:

```php
public function sendInvoiceEmail(string $to, string $invoiceNumber): array {
    $subject = 'Invoice #' . $invoiceNumber;
    $body = $this->getInvoiceTemplate($invoiceNumber);
    return $this->send($to, $subject, $body);
}
```

## Best Practices

### 1. Use Environment-Specific Configurations
- Development: Use Mailtrap for testing
- Production: Use a reliable service like SendGrid or AWS SES

### 2. Secure Credentials
- Never commit `.env` files with real credentials
- Use `.env.example` as a template
- Rotate API keys regularly

### 3. Handle Failures Gracefully
- Email failures shouldn't break core functionality
- Always check return values
- Log all email attempts

### 4. Validate Email Addresses
```php
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Invalid email
}
```

### 5. Use Reply-To for User Responses
```php
$mailService->send($to, $subject, $body, $userEmail);
```

### 6. Avoid Spam
- Use consistent "From" address
- Include unsubscribe links in bulk emails
- Don't use suspicious subject lines
- Follow CAN-SPAM or GDPR regulations

### 7. Test in Development
- Use Mailtrap inbox to verify email formatting
- Test with different email clients
- Check spam folder appearances

## Troubleshooting

### Emails Not Sending

1. **Check SMTP credentials**
   ```
   MAIL_HOST=your_actual_host
   MAIL_PORT=587
   MAIL_USERNAME=correct_username
   MAIL_PASSWORD=correct_password
   ```

2. **Verify port is open**
   - Port 587 (TLS) is most common
   - Port 465 (SSL) for some providers
   - Port 25 (often blocked by ISPs)

3. **Check firewall rules**
   - Server must be able to reach SMTP host
   - Verify outbound port rules

4. **Review error logs**
   ```bash
   tail -f /var/log/php-errors.log
   ```

5. **Test connection manually**
   ```php
   $mail = new PHPMailer(true);
   $mail->isSMTP();
   $mail->Host = 'smtp.mailtrap.io';
   $mail->Port = 587;
   // ... add more config
   ```

### Emails Going to Spam

- Add SPF record: `v=spf1 include:sendgrid.net ~all`
- Add DKIM record: Check your email provider
- Add DMARC record: `v=DMARC1; p=none;`
- Use consistent From address
- Add unsubscribe links in bulk emails

### Authentication Failures

- Verify username/password
- Check for special characters in password (URL encode if needed)
- Confirm SMTP service is active
- Check IP whitelist (some services require it)

## Performance Considerations

- Email sending is synchronous; consider async processing for high volume
- Batch emails for improved performance
- Use connection pooling if available
- Consider a queue system (Redis, database) for reliability

## Security Considerations

1. **Never log sensitive data**
   - Passwords are hashed with bcrypt
   - Reset tokens are secure random bytes
   - Email content is not logged

2. **Use HTTPS only**
   - Set `SESSION_SECURE=1` in production
   - Ensure all reset/verification links use HTTPS

3. **Token expiration**
   - Reset tokens expire after 1 hour
   - Verification tokens should have reasonable expiration

4. **Rate limiting**
   - Implement rate limiting on password reset
   - Prevent email bombing attacks

## Support

For PHPMailer documentation: https://github.com/PHPMailer/PHPMailer
For email provider documentation, see their respective sites.
