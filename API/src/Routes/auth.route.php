<?php

/**
 * Authentication Routes Handler
 * 
 * Handles the routing for authentication-related endpoints.
 * This follows the existing router pattern in index.php where:
 * - Routes are called with authRoutes($action, $data)
 * - The $action parameter determines which controller method to call
 * - Input data is passed directly to the controller via its constructor
 * 
 * Available actions:
 * - register: Register a new user
 * - login: Authenticate user with email/password
 * - request-password-reset: Request password reset email
 * - validate-reset-token: Verify a password reset token
 * - reset-password: Reset password using a valid token
 * - change-password: Change password for authenticated user
 * - get-profile: Get user profile by ID
 * - update-profile: Update user profile information
 * - deactivate: Deactivate user account
 * - verify-token: Verify JWT token validity
 * - refresh-token: Generate new access token from refresh token
 */

use Ordinatrack\Api\Controller\AuthController;

/**
 * Routes handler for authentication endpoints
 * 
 * @param string $action Action to perform
 * @param array $data Request data (query string, POST body, or path parameters)
 * @return string JSON response
 */
function authRoutes(string $action, array $data): string
{
    // Instantiate controller with request data
    $controller = new AuthController($data);

    // Route to the appropriate controller method
    $result = match ($action) {
        'register' => $controller->register(),
        'login' => $controller->login(),
        'request-password-reset' => $controller->requestPasswordReset(),
        'validate-reset-token' => $controller->validateResetToken(),
        'reset-password' => $controller->resetPassword(),
        'change-password' => $controller->changePassword(),
        'get-profile' => $controller->getProfile(),
        'update-profile' => $controller->updateProfile(),
        'deactivate' => $controller->deactivateAccount(),
        'verify-token' => $controller->verifyToken(),
        'refresh-token' => $controller->refreshToken(),
        default => [
            'success' => false,
            'message' => 'Invalid action: ' . $action,
            'statuscode' => 400
        ]
    };

    // Return JSON-encoded response
    return json_encode($result);
}
