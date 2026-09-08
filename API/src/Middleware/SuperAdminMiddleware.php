<?php

namespace Ordinatrack\Api\Middleware;

use Ordinatrack\Api\Connections\Database;

/**
 * SuperAdminMiddleware
 * 
 * Middleware to verify super admin access
 * Checks if the authenticated user is a super admin and can access protected endpoints
 */
class SuperAdminMiddleware
{
    /**
     * Check if user is super admin
     * 
     * Checks if user has the 'super_admin' role or is the hardcoded super admin email
     * 
     * @param object $user The authenticated user object
     * @return bool
     */
    public static function isSuperAdmin($user): bool
    {
        if (!$user || !isset($user->id)) {
            return false;
        }

        // Check by hardcoded email (legacy support)
        if (isset($user->email) && $user->email === 'super.admin@ordinatrack.com') {
            return true;
        }

        // Check by role in database
        try {
            $result = Database::fetch(
                "SELECT 1 FROM user_roles ur
                 INNER JOIN roles r ON ur.role_id = r.id
                 WHERE ur.user_id = ? AND r.slug = 'super_admin'",
                [$user->id]
            );

            return $result !== false;
        } catch (\Exception $e) {
            error_log('Error checking super admin role: ' . $e->getMessage());
            // Fallback to email check
            return isset($user->email) && $user->email === 'super.admin@ordinatrack.com';
        }
    }

    /**
     * Check if user has admin role
     * 
     * @param object $user The authenticated user object
     * @return bool
     */
    public static function isAdmin($user): bool
    {
        if (!$user || !isset($user->id)) {
            return false;
        }

        try {
            $result = Database::fetch(
                "SELECT 1 FROM user_roles ur
                 INNER JOIN roles r ON ur.role_id = r.id
                 WHERE ur.user_id = ? AND (r.slug = 'admin' OR r.slug = 'nhq_admin')",
                [$user->id]
            );

            return $result !== false;
        } catch (\Exception $e) {
            error_log('Error checking admin role: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has a specific permission
     * 
     * @param object $user The authenticated user object
     * @param string $permissionSlug The permission slug
     * @return bool
     */
    public static function hasPermission($user, string $permissionSlug): bool
    {
        if (!$user || !isset($user->id)) {
            return false;
        }

        try {
            // Super admin has all permissions
            if (self::isSuperAdmin($user)) {
                return true;
            }

            // Check if user has the permission through their roles
            $result = Database::fetch(
                "SELECT 1 FROM permissions p
                 INNER JOIN role_permissions rp ON p.id = rp.permission_id
                 INNER JOIN roles r ON rp.role_id = r.id
                 INNER JOIN user_roles ur ON r.id = ur.role_id
                 WHERE ur.user_id = ? AND p.slug = ? AND p.name IS NOT NULL",
                [$user->id, $permissionSlug]
            );

            return $result !== false;
        } catch (\Exception $e) {
            error_log('Error checking user permission: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has any of the given permissions
     * 
     * @param object $user The authenticated user object
     * @param array $permissionSlugs Array of permission slugs
     * @return bool
     */
    public static function hasAnyPermission($user, array $permissionSlugs): bool
    {
        if (!$user || !isset($user->id)) {
            return false;
        }

        // Super admin has all permissions
        if (self::isSuperAdmin($user)) {
            return true;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($permissionSlugs), '?'));
            $params = array_merge([$user->id], $permissionSlugs);

            $result = Database::fetch(
                "SELECT 1 FROM permissions p
                 INNER JOIN role_permissions rp ON p.id = rp.permission_id
                 INNER JOIN roles r ON rp.role_id = r.id
                 INNER JOIN user_roles ur ON r.id = ur.role_id
                 WHERE ur.user_id = ? AND p.slug IN ($placeholders)
                 LIMIT 1",
                $params
            );

            return $result !== false;
        } catch (\Exception $e) {
            error_log('Error checking user permissions: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has all of the given permissions
     * 
     * @param object $user The authenticated user object
     * @param array $permissionSlugs Array of permission slugs
     * @return bool
     */
    public static function hasAllPermissions($user, array $permissionSlugs): bool
    {
        if (!$user || !isset($user->id)) {
            return false;
        }

        // Super admin has all permissions
        if (self::isSuperAdmin($user)) {
            return true;
        }

        try {
            foreach ($permissionSlugs as $slug) {
                if (!self::hasPermission($user, $slug)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log('Error checking user permissions: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all user permissions
     * 
     * @param object $user The authenticated user object
     * @return array Array of permission slugs
     */
    public static function getUserPermissions($user): array
    {
        if (!$user || !isset($user->id)) {
            return [];
        }

        try {
            // Super admin gets all permissions
            if (self::isSuperAdmin($user)) {
                $allPerms = Database::fetchAll("SELECT slug FROM permissions");
                return array_map(function($p) { return $p['slug']; }, $allPerms);
            }

            // Get user permissions through roles
            $permissions = Database::fetchAll(
                "SELECT DISTINCT p.slug FROM permissions p
                 INNER JOIN role_permissions rp ON p.id = rp.permission_id
                 INNER JOIN roles r ON rp.role_id = r.id
                 INNER JOIN user_roles ur ON r.id = ur.role_id
                 WHERE ur.user_id = ?",
                [$user->id]
            );

            return array_map(function($p) { return $p['slug']; }, $permissions);
        } catch (\Exception $e) {
            error_log('Error getting user permissions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all user roles
     * 
     * @param object $user The authenticated user object
     * @return array Array of role slugs
     */
    public static function getUserRoles($user): array
    {
        if (!$user || !isset($user->id)) {
            return [];
        }

        try {
            $roles = Database::fetchAll(
                "SELECT DISTINCT r.slug FROM roles r
                 INNER JOIN user_roles ur ON r.id = ur.role_id
                 WHERE ur.user_id = ?",
                [$user->id]
            );

            return array_map(function($r) { return $r['slug']; }, $roles);
        } catch (\Exception $e) {
            error_log('Error getting user roles: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if access is allowed for a specific resource and action
     * 
     * @param object $user The authenticated user object
     * @param string $resource The resource name (e.g., 'users', 'roles')
     * @param string $action The action (e.g., 'create', 'read', 'update', 'delete')
     * @return bool
     */
    public static function canAccessResource($user, string $resource, string $action): bool
    {
        if (!$user || !isset($user->id)) {
            return false;
        }

        // Super admin can access everything
        if (self::isSuperAdmin($user)) {
            return true;
        }

        try {
            $result = Database::fetch(
                "SELECT 1 FROM permissions p
                 INNER JOIN role_permissions rp ON p.id = rp.permission_id
                 INNER JOIN roles r ON rp.role_id = r.id
                 INNER JOIN user_roles ur ON r.id = ur.role_id
                 WHERE ur.user_id = ? AND p.resource = ? AND p.action = ?",
                [$user->id, $resource, $action]
            );

            return $result !== false;
        } catch (\Exception $e) {
            error_log('Error checking resource access: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Require admin access (throws error if not admin)
     * 
     * @param object $user The authenticated user object
     * @return void
     * @throws \Exception
     */
    public static function requireAdmin($user): void
    {
        if (!self::isSuperAdmin($user) && !self::isAdmin($user)) {
            throw new \Exception('Admin access required', 403);
        }
    }

    /**
     * Require super admin access (throws error if not super admin)
     * 
     * @param object $user The authenticated user object
     * @return void
     * @throws \Exception
     */
    public static function requireSuperAdmin($user): void
    {
        if (!self::isSuperAdmin($user)) {
            throw new \Exception('Super admin access required', 403);
        }
    }

    /**
     * Require specific permission (throws error if not allowed)
     * 
     * @param object $user The authenticated user object
     * @param string $permissionSlug The permission slug
     * @return void
     * @throws \Exception
     */
    public static function requirePermission($user, string $permissionSlug): void
    {
        if (!self::hasPermission($user, $permissionSlug)) {
            throw new \Exception('Permission denied: ' . $permissionSlug, 403);
        }
    }

    /**
     * Require any of the given permissions (throws error if none match)
     * 
     * @param object $user The authenticated user object
     * @param array $permissionSlugs Array of permission slugs
     * @return void
     * @throws \Exception
     */
    public static function requireAnyPermission($user, array $permissionSlugs): void
    {
        if (!self::hasAnyPermission($user, $permissionSlugs)) {
            throw new \Exception('Permission denied', 403);
        }
    }

    /**
     * Log access attempt (for audit trail)
     * 
     * @param object $user The authenticated user object
     * @param string $action The action performed
     * @param string $resource The resource type
     * @param int|null $resourceId The resource ID (optional)
     * @param array|null $data Additional data to log
     * @return bool
     */
    public static function logAccess($user, string $action, string $resource, ?int $resourceId = null, ?array $data = null): bool
    {
        if (!$user || !isset($user->id)) {
            return false;
        }

        try {
            $ipAddress = self::getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $oldValues = null;
            $newValues = $data ? json_encode($data) : null;

            Database::execute(
                "INSERT INTO audit_logs (user_id, resource_type, resource_id, action, old_values, new_values, ip_address, user_agent) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $user->id,
                    $resource,
                    $resourceId,
                    $action,
                    $oldValues,
                    $newValues,
                    $ipAddress,
                    $userAgent
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log('Error logging access: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get client IP address
     * 
     * @return string
     */
    public static function getClientIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }
}
