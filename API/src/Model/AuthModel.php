<?php

namespace Ordinatrack\Api\Model;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ordinatrack\Api\Connections\Database;
use Ordinatrack\Api\Config\Config;
use Ordinatrack\Api\Services\MailService;
use PDOException;
use Exception;

/**
 * Authentication Model
 * 
 * Handles user authentication, registration, password management,
 * JWT token generation and validation.
 */
class AuthModel
{
    /**
     * Get JWT secret from config
     */
    private static function getJwtSecret(): string
    {
        Config::init();
        return Config::getString('JWT_SECRET');
    }

    /**
     * Get JWT algorithm from config
     */
    private static function getJwtAlgorithm(): string
    {
        Config::init();
        return Config::getString('JWT_ALGORITHM', 'HS256');
    }

    /**
     * Get JWT expiration from config
     */
    private static function getJwtExpiration(): int
    {
        Config::init();
        return Config::getInt('JWT_EXPIRATION', 86400);
    }

    /**
     * Get JWT refresh expiration from config
     */
    private static function getJwtRefreshExpiration(): int
    {
        Config::init();
        return Config::getInt('JWT_REFRESH_EXPIRATION', 604800);
    }

    /**
     * Get bcrypt cost from config
     */
    private static function getBcryptCost(): int
    {
        Config::init();
        return Config::getInt('BCRYPT_COST', 12);
    }

    /**
     * Register a new user
     * 
     * @param array $userData User data (email, password, first_name, last_name)
     * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
     */
    public static function register(array $userData): array
    {
        try {
            // Validate input
            if (empty($userData['email']) || empty($userData['password'])) {
                return [
                    'success' => false,
                    'message' => 'Email and password are required'
                ];
            }

            // Check if user already exists
            $existing = Database::fetch(
                'SELECT id FROM users WHERE email = ?',
                [$userData['email']]
            );

            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'User with this email already exists'
                ];
            }

            // Hash password
            $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT, ['cost' => self::getBcryptCost()]);

            // Insert user
            $query = 'INSERT INTO users (email, password, first_name, last_name, created_at) 
                     VALUES (?, ?, ?, ?, NOW())';
            
            $params = [
                $userData['email'],
                $hashedPassword,
                $userData['first_name'] ?? '',
                $userData['last_name'] ?? ''
            ];

            Database::execute($query, $params);
            $userId = Database::lastInsertId();

            // Assign role if provided
            if (!empty($userData['role'])) {
                self::assignRoleToUser($userId, $userData['role']);
            }

            // Create organization if needed based on role
            if (!empty($userData['role'])) {
                self::setupOrganizationHierarchy($userId, $userData);
            }

            // Send welcome email (disabled for now to prevent timeout issues)
            // TODO: Implement async email sending or queue system
            /*try {
                $mailService = new MailService();
                $loginUrl = Config::getString('APP_URL') . '/login';
                $fullName = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
                $mailService->sendWelcomeEmail($userData['email'], $fullName ?: 'User', $loginUrl);
            } catch (Exception $e) {
                error_log("Failed to send welcome email: " . $e->getMessage());
                // Don't fail the registration if email fails
            }*/

            error_log("User registered successfully: {$userData['email']} with role: {$userData['role']}");

            return [
                'success' => true,
                'message' => 'User registered successfully',
                'user_id' => (int)$userId
            ];
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed'
            ];
        } catch (Exception $e) {
            error_log("Unexpected error during registration: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred'
            ];
        }
    }

    /**
     * Assign a role to a user
     * 
     * @param int $userId
     * @param string $role
     * @return bool
     */
    private static function assignRoleToUser(int $userId, string $role): bool
    {
        try {
            // Map frontend role names to database role slugs
            $roleMapping = [
                'NHQ' => 'nhq_admin',
                'Province' => 'province_lead',
                'District' => 'district_lead',
                'Branch' => 'branch_admin'
            ];

            $roleSlug = $roleMapping[$role] ?? strtolower($role);

            // Get role ID from database
            $roleRow = Database::fetch(
                'SELECT id FROM roles WHERE slug = ? OR name = ?',
                [$roleSlug, $role]
            );

            if (!$roleRow) {
                error_log("Role not found: $role");
                return false;
            }

            // Check if user already has this role
            $existing = Database::fetch(
                'SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?',
                [$userId, $roleRow['id']]
            );

            if (!$existing) {
                Database::execute(
                    'INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())',
                    [$userId, $roleRow['id']]
                );
                error_log("Role assigned to user $userId: $role");
            }

            return true;
        } catch (Exception $e) {
            error_log("Error assigning role to user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Setup organization hierarchy for user based on their role
     * 
     * @param int $userId
     * @param array $userData
     * @return bool
     */
    private static function setupOrganizationHierarchy(int $userId, array $userData): bool
    {
        try {
            $role = $userData['role'] ?? '';
            $branchName = $userData['branch_name'] ?? null;
            $provinceId = $userData['province_id'] ?? null;
            $districtId = $userData['district_id'] ?? null;

            // Create or get organization based on role
            $organizationId = null;

            if ($role === 'NHQ') {
                // Create National HQ organization if it doesn't exist
                $nhq = Database::fetch(
                    "SELECT id FROM organizations WHERE organization_type = 'NHQ' LIMIT 1"
                );

                if ($nhq) {
                    $organizationId = $nhq['id'];
                } else {
                    Database::execute(
                        "INSERT INTO organizations (name, organization_type, created_by, created_at) 
                         VALUES (?, ?, ?, NOW())",
                        ["National HQ", "NHQ", $userId]
                    );
                    $organizationId = Database::lastInsertId();
                }
            } elseif ($role === 'Branch' && $branchName && $districtId) {
                // Get or create branch organization
                $branch = Database::fetch(
                    "SELECT id FROM branches WHERE name = ? AND district_id = ? LIMIT 1",
                    [$branchName, $districtId]
                );

                if ($branch) {
                    $organizationId = $branch['organization_id'] ?? null;
                }

                if (!$organizationId) {
                    // Create organization for branch
                    Database::execute(
                        "INSERT INTO organizations (name, organization_type, created_by, created_at) 
                         VALUES (?, ?, ?, NOW())",
                        [$branchName, "Branch", $userId]
                    );
                    $organizationId = Database::lastInsertId();
                }
            } elseif ($role === 'District' && $districtId) {
                // Get district organization
                $district = Database::fetch(
                    "SELECT organization_id FROM districts WHERE id = ?",
                    [$districtId]
                );

                if ($district && $district['organization_id']) {
                    $organizationId = $district['organization_id'];
                }
            } elseif ($role === 'Province' && $provinceId) {
                // Get province organization
                $province = Database::fetch(
                    "SELECT organization_id FROM provinces WHERE id = ?",
                    [$provinceId]
                );

                if ($province && $province['organization_id']) {
                    $organizationId = $province['organization_id'];
                }
            }

            // Add user to organization if organization exists
            if ($organizationId) {
                $existing = Database::fetch(
                    "SELECT id FROM organization_members WHERE organization_id = ? AND user_id = ?",
                    [$organizationId, $userId]
                );

                if (!$existing) {
                    Database::execute(
                        "INSERT INTO organization_members (organization_id, user_id, role, joined_at) 
                         VALUES (?, ?, ?, NOW())",
                        [$organizationId, $userId, $role]
                    );
                    error_log("User $userId added to organization $organizationId with role $role");
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error setting up organization hierarchy: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Authenticate user with email and password
     * 
     * @param string $email User email
     * @param string $password User password
     * @return array ['success' => bool, 'message' => string, 'token' => string|null, 'user' => array|null]
     */
    public static function login(string $email, string $password): array
    {
        try {
            // Get user from database
            $user = Database::fetch(
                'SELECT id, email, password, first_name, last_name, is_active FROM users WHERE email = ?',
                [$email]
            );

            if (!$user) {
                error_log("Login attempt for non-existent user: {$email}");
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }

            // Check if account is active
            if (!$user['is_active']) {
                error_log("Login attempt for inactive user: {$email}");
                return [
                    'success' => false,
                    'message' => 'Account is inactive'
                ];
            }

            // Verify password
            if (!password_verify($password, $user['password'])) {
                error_log("Failed password verification for user: {$email}");
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }

            // Get user's role
            $roleResult = Database::fetch(
                'SELECT r.name, r.slug FROM user_roles ur 
                 JOIN roles r ON ur.role_id = r.id 
                 WHERE ur.user_id = ? 
                 LIMIT 1',
                [$user['id']]
            );

            $role = $roleResult ? $roleResult['slug'] : 'member';

            // Generate JWT token
            $token = self::generateToken($user['id']);

            // Update last login
            Database::execute(
                'UPDATE users SET last_login_at = NOW() WHERE id = ?',
                [$user['id']]
            );

            error_log("User logged in successfully: {$email} with role: {$role}");

            return [
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => (int)$user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $role
                ]
            ];
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Login failed'
            ];
        }
    }

    /**
     * Generate JWT token
     * 
     * @param int $userId User ID
     * @return string JWT token
     * @throws Exception
     */
    public static function generateToken(int $userId): string
    {
        $issuedAt = time();
        $expire = $issuedAt + self::getJwtExpiration();

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'user_id' => $userId,
            'type' => 'access'
        ];

        return JWT::encode($payload, self::getJwtSecret(), self::getJwtAlgorithm());
    }

    /**
     * Generate refresh token
     * 
     * @param int $userId User ID
     * @return string Refresh token
     * @throws Exception
     */
    public static function generateRefreshToken(int $userId): string
    {
        $issuedAt = time();
        $expire = $issuedAt + self::getJwtRefreshExpiration();

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'user_id' => $userId,
            'type' => 'refresh'
        ];

        return JWT::encode($payload, self::getJwtSecret(), self::getJwtAlgorithm());
    }

    /**
     * Verify and decode JWT token
     * 
     * @param string $token JWT token
     * @return array|null Decoded token payload or null if invalid
     */
    public static function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::getJwtSecret(), self::getJwtAlgorithm()));
            return (array)$decoded;
        } catch (Exception $e) {
            error_log("Token verification failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Request password reset
     * 
     * @param string $email User email
     * @param string|null $resetUrlBase Base URL for reset link (e.g., https://example.com/reset?token=)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function requestPasswordReset(string $email, ?string $resetUrlBase = null): array
    {
        try {
            $user = Database::fetch('SELECT id, first_name, last_name FROM users WHERE email = ?', [$email]);

            if (!$user) {
                // Don't reveal whether email exists (security best practice)
                return [
                    'success' => true,
                    'message' => 'If the email exists, a reset link has been sent'
                ];
            }

            // Generate reset token (valid for 1 hour)
            $resetToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600);

            Database::execute(
                'UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?',
                [$resetToken, $expiry, $user['id']]
            );

            // Send reset email (disabled to prevent timeout issues)
            // TODO: Implement async email sending or queue system
            /*try {
                $mailService = new MailService();
                $resetUrlBase = $resetUrlBase ?? Config::getString('APP_URL') . '/reset-password?token=';
                $resetUrl = $resetUrlBase . $resetToken;
                $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $mailService->sendPasswordResetEmail($email, $fullName ?: 'User', $resetToken, $resetUrl);
            } catch (Exception $e) {
                error_log("Failed to send reset email: " . $e->getMessage());
                // Don't fail the request if email fails
            }*/

            error_log("Password reset requested for user: {$email}");

            return [
                'success' => true,
                'message' => 'If the email exists, a reset link has been sent',
                'reset_token' => $resetToken // Only in development; in production, only send via email
            ];
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password reset request failed'
            ];
        }
    }

    /**
     * Validate reset token
     * 
     * @param string $resetToken Reset token
     * @return array|null User data if valid, null otherwise
     */
    public static function validateResetToken(string $resetToken): ?array
    {
        try {
            $user = Database::fetch(
                'SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()',
                [$resetToken]
            );

            return $user ?: null;
        } catch (Exception $e) {
            error_log("Reset token validation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Reset password with token
     * 
     * @param string $resetToken Reset token
     * @param string $newPassword New password
     * @return array ['success' => bool, 'message' => string]
     */
    public static function resetPassword(string $resetToken, string $newPassword): array
    {
        try {
            // Validate reset token
            $user = self::validateResetToken($resetToken);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ];
            }

            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => self::getBcryptCost()]);

            // Update password and clear reset token
            Database::execute(
                'UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?',
                [$hashedPassword, $user['id']]
            );

            error_log("Password reset successful for user: {$user['email']}");

            return [
                'success' => true,
                'message' => 'Password reset successful'
            ];
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password reset failed'
            ];
        }
    }

    /**
     * Change user password (requires current password verification)
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array ['success' => bool, 'message' => string]
     */
    public static function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        try {
            $user = Database::fetch('SELECT password FROM users WHERE id = ?', [$userId]);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }

            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => self::getBcryptCost()]);

            Database::execute('UPDATE users SET password = ? WHERE id = ?', [$hashedPassword, $userId]);

            error_log("Password changed successfully for user ID: {$userId}");

            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password change failed'
            ];
        }
    }

    /**
     * Get user by ID
     * 
     * @param int $userId User ID
     * @return array|null User data or null
     */
    public static function getUserById(int $userId): ?array
    {
        try {
            return Database::fetch(
                'SELECT id, email, first_name, last_name, is_active, created_at, last_login FROM users WHERE id = ?',
                [$userId]
            );
        } catch (Exception $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update user profile
     * 
     * @param int $userId User ID
     * @param array $updateData Fields to update
     * @return array ['success' => bool, 'message' => string]
     */
    public static function updateProfile(int $userId, array $updateData): array
    {
        try {
            $allowed = ['first_name', 'last_name', 'email'];
            $updates = [];
            $params = [];

            foreach ($allowed as $field) {
                if (isset($updateData[$field])) {
                    $updates[] = "{$field} = ?";
                    $params[] = $updateData[$field];
                }
            }

            if (empty($updates)) {
                return [
                    'success' => false,
                    'message' => 'No valid fields to update'
                ];
            }

            $params[] = $userId;
            $query = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';

            Database::execute($query, $params);

            error_log("Profile updated for user ID: {$userId}");

            return [
                'success' => true,
                'message' => 'Profile updated successfully'
            ];
        } catch (Exception $e) {
            error_log("Update profile error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Profile update failed'
            ];
        }
    }

    /**
     * Deactivate user account
     * 
     * @param int $userId User ID
     * @return array ['success' => bool, 'message' => string]
     */
    public static function deactivateAccount(int $userId): array
    {
        try {
            Database::execute('UPDATE users SET is_active = 0 WHERE id = ?', [$userId]);

            error_log("Account deactivated for user ID: {$userId}");

            return [
                'success' => true,
                'message' => 'Account deactivated successfully'
            ];
        } catch (Exception $e) {
            error_log("Deactivate account error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Account deactivation failed'
            ];
        }
    }
}
