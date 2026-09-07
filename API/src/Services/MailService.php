<?php

namespace Ordinatrack\Api\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Ordinatrack\Api\Config\Config;

/**
 * Email Service
 * 
 * Handles email sending using PHPMailer with configuration from environment
 */
class MailService
{
    /**
     * @var PHPMailer
     */
    private PHPMailer $mailer;

    /**
     * Constructor
     * 
     * @throws Exception
     */
    public function __construct()
    {
        Config::init();
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }

    /**
     * Configure PHPMailer with environment settings
     * 
     * @return void
     * @throws Exception
     */
    private function configureMailer(): void
    {
        $mailConfig = Config::getMail();

        try {
            // Use SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = $mailConfig['host'];
            $this->mailer->Port = $mailConfig['port'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $mailConfig['username'];
            $this->mailer->Password = $mailConfig['password'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->CharSet = 'UTF-8';

            // Set From
            $this->mailer->setFrom(
                $mailConfig['from_address'],
                $mailConfig['from_name']
            );

            // Error handling
            $this->mailer->SMTPDebug = Config::isDebug() ? 2 : 0;
        } catch (Exception $e) {
            error_log("Mailer configuration error: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Send a simple email
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string|null $replyTo Reply-to address
     * @return array ['success' => bool, 'message' => string]
     */
    public function send(
        string $to,
        string $subject,
        string $body,
        ?string $replyTo = null
    ): array {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearReplyTos();

            $this->mailer->addAddress($to);

            if ($replyTo) {
                $this->mailer->addReplyTo($replyTo);
            }

            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;

            $this->mailer->send();

            error_log("Email sent successfully to: {$to}");

            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (Exception $e) {
            error_log("Failed to send email to {$to}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'Failed to send email'
            ];
        }
    }

    /**
     * Send email to multiple recipients
     * 
     * @param array $to Array of recipient email addresses
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string|null $replyTo Reply-to address
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendToMultiple(
        array $to,
        string $subject,
        string $body,
        ?string $replyTo = null
    ): array {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearReplyTos();

            foreach ($to as $recipient) {
                $this->mailer->addAddress($recipient);
            }

            if ($replyTo) {
                $this->mailer->addReplyTo($replyTo);
            }

            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;

            $this->mailer->send();

            error_log("Email sent to " . count($to) . " recipients");

            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (Exception $e) {
            error_log("Failed to send batch email: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'Failed to send email'
            ];
        }
    }

    /**
     * Send email with attachments
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $attachments Array of file paths to attach
     * @param string|null $replyTo Reply-to address
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendWithAttachments(
        string $to,
        string $subject,
        string $body,
        array $attachments = [],
        ?string $replyTo = null
    ): array {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearReplyTos();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($to);

            if ($replyTo) {
                $this->mailer->addReplyTo($replyTo);
            }

            // Add attachments
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $this->mailer->addAttachment($filePath);
                } else {
                    error_log("Attachment file not found: {$filePath}");
                }
            }

            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;

            $this->mailer->send();

            error_log("Email with attachments sent to: {$to}");

            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (Exception $e) {
            error_log("Failed to send email with attachments to {$to}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'Failed to send email'
            ];
        }
    }

    /**
     * Send password reset email
     * 
     * @param string $to Recipient email address
     * @param string $userName User's name
     * @param string $resetToken Reset token
     * @param string $resetUrl Reset URL (should include token)
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendPasswordResetEmail(
        string $to,
        string $userName,
        string $resetToken,
        string $resetUrl
    ): array {
        $subject = 'Password Reset Request - ' . Config::getString('APP_NAME');

        $body = $this->getPasswordResetTemplate($userName, $resetUrl);

        return $this->send($to, $subject, $body);
    }

    /**
     * Send welcome email to new user
     * 
     * @param string $to Recipient email address
     * @param string $userName User's name
     * @param string $loginUrl Login URL
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendWelcomeEmail(
        string $to,
        string $userName,
        string $loginUrl
    ): array {
        $subject = 'Welcome to ' . Config::getString('APP_NAME');

        $body = $this->getWelcomeTemplate($userName, $loginUrl);

        return $this->send($to, $subject, $body);
    }

    /**
     * Send account verification email
     * 
     * @param string $to Recipient email address
     * @param string $userName User's name
     * @param string $verificationUrl Verification URL (should include token)
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendVerificationEmail(
        string $to,
        string $userName,
        string $verificationUrl
    ): array {
        $subject = 'Verify Your Email - ' . Config::getString('APP_NAME');

        $body = $this->getVerificationTemplate($userName, $verificationUrl);

        return $this->send($to, $subject, $body);
    }

    /**
     * Send account locked notification
     * 
     * @param string $to Recipient email address
     * @param string $userName User's name
     * @param string $supportEmail Support email address
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendAccountLockedNotification(
        string $to,
        string $userName,
        string $supportEmail
    ): array {
        $subject = 'Your Account Has Been Locked - ' . Config::getString('APP_NAME');

        $body = $this->getAccountLockedTemplate($userName, $supportEmail);

        return $this->send($to, $subject, $body);
    }

    /**
     * Get password reset email template
     * 
     * @param string $userName User's name
     * @param string $resetUrl Reset URL
     * @return string HTML email body
     */
    private function getPasswordResetTemplate(string $userName, string $resetUrl): string
    {
        $appName = Config::getString('APP_NAME');
        $appUrl = Config::getString('APP_URL');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
        .button { display: inline-block; background-color: #4CAF50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .warning { color: #d32f2f; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$appName}</h1>
        </div>
        <div class="content">
            <p>Hello {$userName},</p>
            
            <p>We received a request to reset your password. If you didn't make this request, you can ignore this email.</p>
            
            <p>To reset your password, click the button below:</p>
            
            <a href="{$resetUrl}" class="button">Reset Password</a>
            
            <p style="margin-top: 20px; font-size: 14px;">
                Or copy and paste this link in your browser:<br>
                <code>{$resetUrl}</code>
            </p>
            
            <p class="warning">This link will expire in 1 hour.</p>
            
            <p style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                If you have any questions, please contact our support team.
            </p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$appName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get welcome email template
     * 
     * @param string $userName User's name
     * @param string $loginUrl Login URL
     * @return string HTML email body
     */
    private function getWelcomeTemplate(string $userName, string $loginUrl): string
    {
        $appName = Config::getString('APP_NAME');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
        .button { display: inline-block; background-color: #4CAF50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {$appName}!</h1>
        </div>
        <div class="content">
            <p>Hello {$userName},</p>
            
            <p>Welcome to {$appName}! Your account has been created successfully.</p>
            
            <p>You can now log in and start using all the features of our platform.</p>
            
            <a href="{$loginUrl}" class="button">Log In Now</a>
            
            <p style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                If you have any questions or need assistance, please don't hesitate to contact our support team.
            </p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$appName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get email verification template
     * 
     * @param string $userName User's name
     * @param string $verificationUrl Verification URL
     * @return string HTML email body
     */
    private function getVerificationTemplate(string $userName, string $verificationUrl): string
    {
        $appName = Config::getString('APP_NAME');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #2196F3; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
        .button { display: inline-block; background-color: #2196F3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verify Your Email</h1>
        </div>
        <div class="content">
            <p>Hello {$userName},</p>
            
            <p>Thank you for signing up! Please verify your email address to complete your registration.</p>
            
            <a href="{$verificationUrl}" class="button">Verify Email</a>
            
            <p style="margin-top: 20px; font-size: 14px;">
                Or copy and paste this link in your browser:<br>
                <code>{$verificationUrl}</code>
            </p>
            
            <p style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                If you didn't create this account, please ignore this email.
            </p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$appName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get account locked notification template
     * 
     * @param string $userName User's name
     * @param string $supportEmail Support email address
     * @return string HTML email body
     */
    private function getAccountLockedTemplate(string $userName, string $supportEmail): string
    {
        $appName = Config::getString('APP_NAME');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #d32f2f; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
        .warning { color: #d32f2f; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Security Alert</h1>
        </div>
        <div class="content">
            <p>Hello {$userName},</p>
            
            <p class="warning">Your account has been locked due to multiple failed login attempts.</p>
            
            <p>This is a security measure to protect your account. Please contact our support team to unlock your account:</p>
            
            <p><strong>Email:</strong> <a href="mailto:{$supportEmail}">{$supportEmail}</a></p>
            
            <p style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                If you did not attempt to log in, please change your password immediately after regaining access to your account.
            </p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$appName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
