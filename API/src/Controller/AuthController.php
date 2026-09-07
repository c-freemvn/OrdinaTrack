<?php

namespace Ordinatrack\Api\Controller;

use Ordinatrack\Api\Model\AuthModel;
use Ordinatrack\Api\Helpers\ValidationHelper;
use Exception;

/**
 * Authentication Controller
 * 
 * Handles authentication operations including:
 * - User registration
 * - User login
 * - Password reset
 * - Token refresh
 * - User profile management
 */
class AuthController
{
    /**
     * Request data passed through constructor
     */
    private array $data;

    /**
     * Response data array
     */
    private array $response = [
        'success' => false,
        'message' => '',
        'data' => null
    ];

    /**
     * Constructor - receives input data from route
     * 
     * @param array $data Input data from request
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Register a new user
     * 
     * Expected data:
     * - email: User email (required, valid email)
     * - password: User password (required, min 8 chars)
     * - first_name: First name (required, alphabetic)
     * - last_name: Last name (required, alphabetic)
     * 
     * @return array Response with success/error
     */
    public function register(): array
    {
        try {
            // Validate input
            $validation = ValidationHelper::validate(
                $this->data,
                ValidationHelper::registrationRules()
            );

            if (!$validation['is_valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            $validatedData = $validation['data'];

            // Check if password is strong
            if (!ValidationHelper::isStrongPassword($validatedData['password'])) {
                return [
                    'success' => false,
                    'message' => 'Password must contain uppercase, lowercase, number and special character',
                    'errors' => ['password' => 'Password too weak']
                ];
            }

            // Attempt registration
            $result = AuthModel::register($validatedData);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'user_id' => $result['user_id'],
                        'email' => $validatedData['email']
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => $result['message']
            ];
        } catch (Exception $e) {
            error_log("Registration controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during registration'
            ];
        }
    }

    /**
     * Login user
     * 
     * Expected data:
     * - email: User email (required, valid email)
     * - password: User password (required)
     * 
     * @return array Response with token and user info on success
     */
    public function login(): array
    {
        try {
            // Validate input
            $validation = ValidationHelper::validate(
                $this->data,
                ValidationHelper::loginRules()
            );

            if (!$validation['is_valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            $validatedData = $validation['data'];

            // Attempt login
            $result = AuthModel::login($validatedData['email'], $validatedData['password']);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'token' => $result['token'],
                        'user' => $result['user']
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => $result['message']
            ];
        } catch (Exception $e) {
            error_log("Login controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during login'
            ];
        }
    }

    /**
     * Request password reset
     * 
     * Expected data:
     * - email: User email (required, valid email)
     * - reset_url_base: Optional base URL for reset link
     * 
     * @return array Response indicating if reset email was sent
     */
    public function requestPasswordReset(): array
    {
        try {
            // Validate input
            $validation = ValidationHelper::validate(
                $this->data,
                ['email' => 'required|valid_email']
            );

            if (!$validation['is_valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            $validatedData = $validation['data'];

            // Request password reset
            $result = AuthModel::requestPasswordReset(
                $validatedData['email'],
                $this->data['reset_url_base'] ?? null
            );

            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => isset($result['reset_token']) ? ['reset_token' => $result['reset_token']] : null
            ];
        } catch (Exception $e) {
            error_log("Password reset request controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during password reset request'
            ];
        }
    }

    /**
     * Validate password reset token
     * 
     * Expected data:
     * - reset_token: Password reset token (required)
     * 
     * @return array Response indicating if token is valid
     */
    public function validateResetToken(): array
    {
        try {
            // Validate input
            if (empty($this->data['reset_token'])) {
                return [
                    'success' => false,
                    'message' => 'Reset token is required',
                    'errors' => ['reset_token' => 'Reset token is required']
                ];
            }

            $resetToken = $this->data['reset_token'];

            // Validate token
            $user = AuthModel::validateResetToken($resetToken);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ];
            }

            return [
                'success' => true,
                'message' => 'Reset token is valid',
                'data' => ['email' => $user['email']]
            ];
        } catch (Exception $e) {
            error_log("Validate reset token controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while validating reset token'
            ];
        }
    }

    /**
     * Reset password using token
     * 
     * Expected data:
     * - reset_token: Password reset token (required)
     * - password: New password (required, min 8 chars)
     * 
     * @return array Response indicating if password was reset
     */
    public function resetPassword(): array
    {
        try {
            // Validate input
            $validation = ValidationHelper::validate(
                $this->data,
                [
                    'reset_token' => 'required',
                    'password' => 'required|min_len,8|max_len,128'
                ]
            );

            if (!$validation['is_valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            $validatedData = $validation['data'];

            // Check if password is strong
            if (!ValidationHelper::isStrongPassword($validatedData['password'])) {
                return [
                    'success' => false,
                    'message' => 'Password must contain uppercase, lowercase, number and special character',
                    'errors' => ['password' => 'Password too weak']
                ];
            }

            // Reset password
            $result = AuthModel::resetPassword($validatedData['reset_token'], $validatedData['password']);

            return [
                'success' => $result['success'],
                'message' => $result['message']
            ];
        } catch (Exception $e) {
            error_log("Password reset controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during password reset'
            ];
        }
    }

    /**
     * Change password (requires current password verification)
     * 
     * Expected data:
     * - user_id: Current user ID (required)
     * - current_password: Current password (required)
     * - new_password: New password (required, min 8 chars)
     * 
     * @return array Response indicating if password was changed
     */
    public function changePassword(): array
    {
        try {
            // Validate input
            $validation = ValidationHelper::validate(
                $this->data,
                [
                    'user_id' => 'required|integer',
                    'current_password' => 'required',
                    'new_password' => 'required|min_len,8|max_len,128'
                ]
            );

            if (!$validation['is_valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            $validatedData = $validation['data'];

            // Check if new password is strong
            if (!ValidationHelper::isStrongPassword($validatedData['new_password'])) {
                return [
                    'success' => false,
                    'message' => 'Password must contain uppercase, lowercase, number and special character',
                    'errors' => ['new_password' => 'Password too weak']
                ];
            }

            // Change password
            $result = AuthModel::changePassword(
                (int)$validatedData['user_id'],
                $validatedData['current_password'],
                $validatedData['new_password']
            );

            return [
                'success' => $result['success'],
                'message' => $result['message']
            ];
        } catch (Exception $e) {
            error_log("Change password controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during password change'
            ];
        }
    }

    /**
     * Get user profile
     * 
     * Expected data:
     * - user_id: User ID (required, integer)
     * 
     * @return array Response with user profile data
     */
    public function getProfile(): array
    {
        try {
            // Validate input
            $validation = ValidationHelper::validate(
                $this->data,
                ['user_id' => 'required|integer']
            );

            if (!$validation['is_valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            $validatedData = $validation['data'];
            $userId = (int)$validatedData['user_id'];

            // Get user
            $user = AuthModel::getUserById($userId);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            return [
                'success' => true,
                'message' => 'User profile retrieved successfully',
                'data' => $user
            ];
        } catch (Exception $e) {
            error_log("Get profile controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving profile'
            ];
        }
    }

    /**
     * Update user profile
     * 
     * Expected data:
     * - user_id: User ID (required, integer)
     * - first_name: First name (optional, alphabetic)
     * - last_name: Last name (optional, alphabetic)
     * - email: Email (optional, valid email)
     * 
     * @return array Response indicating if profile was updated
     */
    public function updateProfile(): array
    {
        try {
            // Validate user_id separately
            if (empty($this->data['user_id'])) {
                return [
                    'success' => false,
                    'message' => 'User ID is required',
                    'errors' => ['user_id' => 'User ID is required']
                ];
            }

            $userId = (int)$this->data['user_id'];

            // Validate profile update data
            $validation = ValidationHelper::validate(
                $this->data,
                ValidationHelper::profileUpdateRules()
            );

            if (!$validation['is_valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            // Update profile
            $result = AuthModel::updateProfile($userId, $validation['data']);

            if ($result['success']) {
                // Retrieve updated profile
                $user = AuthModel::getUserById($userId);
                return [
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $user
                ];
            }

            return [
                'success' => false,
                'message' => $result['message']
            ];
        } catch (Exception $e) {
            error_log("Update profile controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during profile update'
            ];
        }
    }

    /**
     * Deactivate user account
     * 
     * Expected data:
     * - user_id: User ID (required, integer)
     * 
     * @return array Response indicating if account was deactivated
     */
    public function deactivateAccount(): array
    {
        try {
            // Validate input
            $validation = ValidationHelper::validate(
                $this->data,
                ['user_id' => 'required|integer']
            );

            if (!$validation['is_valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            $validatedData = $validation['data'];
            $userId = (int)$validatedData['user_id'];

            // Deactivate account
            $result = AuthModel::deactivateAccount($userId);

            return [
                'success' => $result['success'],
                'message' => $result['message']
            ];
        } catch (Exception $e) {
            error_log("Deactivate account controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during account deactivation'
            ];
        }
    }

    /**
     * Verify JWT token
     * 
     * Expected data:
     * - token: JWT token (required)
     * 
     * @return array Response with token verification result
     */
    public function verifyToken(): array
    {
        try {
            // Validate input
            if (empty($this->data['token'])) {
                return [
                    'success' => false,
                    'message' => 'Token is required',
                    'errors' => ['token' => 'Token is required']
                ];
            }

            $token = $this->data['token'];

            // Verify token
            $decoded = AuthModel::verifyToken($token);

            if (!$decoded) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ];
            }

            return [
                'success' => true,
                'message' => 'Token is valid',
                'data' => $decoded
            ];
        } catch (Exception $e) {
            error_log("Verify token controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during token verification'
            ];
        }
    }

    /**
     * Generate new access token from refresh token
     * 
     * Expected data:
     * - refresh_token: Refresh JWT token (required)
     * 
     * @return array Response with new access token
     */
    public function refreshToken(): array
    {
        try {
            // Validate input
            if (empty($this->data['refresh_token'])) {
                return [
                    'success' => false,
                    'message' => 'Refresh token is required',
                    'errors' => ['refresh_token' => 'Refresh token is required']
                ];
            }

            $refreshToken = $this->data['refresh_token'];

            // Verify refresh token
            $decoded = AuthModel::verifyToken($refreshToken);

            if (!$decoded || ($decoded['type'] ?? null) !== 'refresh') {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired refresh token'
                ];
            }

            // Generate new access token
            $newToken = AuthModel::generateToken($decoded['user_id']);

            return [
                'success' => true,
                'message' => 'New token generated successfully',
                'data' => [
                    'token' => $newToken,
                    'user_id' => $decoded['user_id']
                ]
            ];
        } catch (Exception $e) {
            error_log("Refresh token controller error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during token refresh'
            ];
        }
    }
}
