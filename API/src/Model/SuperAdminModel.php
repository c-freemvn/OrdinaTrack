<?php

namespace Ordinatrack\Api\Model;

use Ordinatrack\Api\Connections\Database;
use PDOException;

/**
 * SuperAdminModel
 * 
 * Handles all Super Admin operations including:
 * - Role management (CRUD)
 * - Permission management (CRUD)
 * - User management and privilege assignment
 * - Permission assignment to roles
 */
class SuperAdminModel
{
    /**
     * Get all roles with their permissions
     * 
     * @return array|false
     */
    public static function getAllRoles()
    {
        try {
            $query = "
                SELECT 
                    r.id,
                    r.name,
                    r.slug,
                    r.description,
                    r.created_at,
                    COUNT(rp.id) as permission_count
                FROM roles r
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                GROUP BY r.id, r.name, r.slug, r.description, r.created_at
                ORDER BY r.name ASC
            ";
            return Database::fetchAll($query);
        } catch (PDOException $e) {
            error_log('Get all roles error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single role with all its permissions
     * 
     * @param int $roleId
     * @return array|false
     */
    public static function getRoleWithPermissions($roleId)
    {
        try {
            // Get role details
            $roleQuery = "SELECT id, name, slug, description, created_at FROM roles WHERE id = ?";
            $role = Database::fetch($roleQuery, [$roleId]);

            if (!$role) {
                return false;
            }

            // Get permissions for this role
            $permQuery = "
                SELECT 
                    p.id,
                    p.name,
                    p.slug,
                    p.resource,
                    p.action,
                    p.description
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.resource, p.action
            ";
            $permissions = Database::fetchAll($permQuery, [$roleId]);

            $role['permissions'] = $permissions ?: [];
            return $role;
        } catch (PDOException $e) {
            error_log('Get role with permissions error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new role
     * 
     * @param array $data
     * @return array|false
     */
    public static function createRole($data)
    {
        try {
            if (empty($data['name']) || empty($data['slug'])) {
                return false;
            }

            // Check if role already exists
            $exists = Database::fetch(
                "SELECT id FROM roles WHERE slug = ?",
                [$data['slug']]
            );

            if ($exists) {
                return false;
            }

            $query = "
                INSERT INTO roles (name, slug, description)
                VALUES (?, ?, ?)
            ";

            Database::execute($query, [
                $data['name'],
                $data['slug'],
                $data['description'] ?? null
            ]);

            $id = Database::lastInsertId();
            return Database::fetch("SELECT * FROM roles WHERE id = ?", [$id]);
        } catch (PDOException $e) {
            error_log('Create role error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing role
     * 
     * @param int $roleId
     * @param array $data
     * @return array|false
     */
    public static function updateRole($roleId, $data)
    {
        try {
            $query = "
                UPDATE roles 
                SET name = ?, slug = ?, description = ?
                WHERE id = ?
            ";

            Database::execute($query, [
                $data['name'] ?? null,
                $data['slug'] ?? null,
                $data['description'] ?? null,
                $roleId
            ]);

            return Database::fetch("SELECT * FROM roles WHERE id = ?", [$roleId]);
        } catch (PDOException $e) {
            error_log('Update role error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a role (cannot delete admin roles)
     * 
     * @param int $roleId
     * @return bool
     */
    public static function deleteRole($roleId)
    {
        try {
            // Prevent deletion of critical roles
            $role = Database::fetch("SELECT slug FROM roles WHERE id = ?", [$roleId]);
            if (!$role) {
                return false;
            }

            if (in_array($role['slug'], ['admin', 'nhq_admin'])) {
                error_log('Cannot delete critical role: ' . $role['slug']);
                return false;
            }

            // Remove role permissions first
            Database::execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);

            // Remove user roles
            Database::execute("DELETE FROM user_roles WHERE role_id = ?", [$roleId]);

            // Delete the role
            Database::execute("DELETE FROM roles WHERE id = ?", [$roleId]);

            return true;
        } catch (PDOException $e) {
            error_log('Delete role error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all permissions
     * 
     * @return array|false
     */
    public static function getAllPermissions()
    {
        try {
            $query = "
                SELECT id, name, slug, resource, action, description, created_at
                FROM permissions
                ORDER BY resource, action ASC
            ";
            return Database::fetchAll($query);
        } catch (PDOException $e) {
            error_log('Get all permissions error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get permissions grouped by resource
     * 
     * @return array|false
     */
    public static function getPermissionsByResource()
    {
        try {
            $query = "
                SELECT 
                    resource,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', id,
                            'name', name,
                            'slug', slug,
                            'action', action,
                            'description', description
                        )
                    ) as permissions
                FROM permissions
                GROUP BY resource
                ORDER BY resource ASC
            ";
            return Database::fetchAll($query);
        } catch (PDOException $e) {
            error_log('Get permissions by resource error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new permission
     * 
     * @param array $data
     * @return array|false
     */
    public static function createPermission($data)
    {
        try {
            if (empty($data['name']) || empty($data['slug'])) {
                return false;
            }

            // Check if permission already exists
            $exists = Database::fetch(
                "SELECT id FROM permissions WHERE slug = ?",
                [$data['slug']]
            );

            if ($exists) {
                return false;
            }

            $query = "
                INSERT INTO permissions (name, slug, resource, action, description)
                VALUES (?, ?, ?, ?, ?)
            ";

            Database::execute($query, [
                $data['name'],
                $data['slug'],
                $data['resource'] ?? null,
                $data['action'] ?? null,
                $data['description'] ?? null
            ]);

            $id = Database::lastInsertId();
            return Database::fetch("SELECT * FROM permissions WHERE id = ?", [$id]);
        } catch (PDOException $e) {
            error_log('Create permission error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing permission
     * 
     * @param int $permissionId
     * @param array $data
     * @return array|false
     */
    public static function updatePermission($permissionId, $data)
    {
        try {
            $query = "
                UPDATE permissions 
                SET name = ?, slug = ?, resource = ?, action = ?, description = ?
                WHERE id = ?
            ";

            Database::execute($query, [
                $data['name'] ?? null,
                $data['slug'] ?? null,
                $data['resource'] ?? null,
                $data['action'] ?? null,
                $data['description'] ?? null,
                $permissionId
            ]);

            return Database::fetch("SELECT * FROM permissions WHERE id = ?", [$permissionId]);
        } catch (PDOException $e) {
            error_log('Update permission error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a permission
     * 
     * @param int $permissionId
     * @return bool
     */
    public static function deletePermission($permissionId)
    {
        try {
            // Remove from role_permissions
            Database::execute("DELETE FROM role_permissions WHERE permission_id = ?", [$permissionId]);

            // Delete the permission
            Database::execute("DELETE FROM permissions WHERE id = ?", [$permissionId]);

            return true;
        } catch (PDOException $e) {
            error_log('Delete permission error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign permissions to a role
     * 
     * @param int $roleId
     * @param array $permissionIds
     * @return bool
     */
    public static function assignPermissionsToRole($roleId, $permissionIds)
    {
        try {
            // Remove existing permissions
            Database::execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);

            // Add new permissions
            foreach ($permissionIds as $permissionId) {
                Database::execute(
                    "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                    [$roleId, $permissionId]
                );
            }

            return true;
        } catch (PDOException $e) {
            error_log('Assign permissions to role error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all users with their roles
     * 
     * @param int $limit
     * @param int $offset
     * @return array|false
     */
    public static function getAllUsers($limit = 50, $offset = 0)
    {
        try {
            $query = "
                SELECT 
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.phone,
                    u.is_active,
                    u.email_verified,
                    u.created_at,
                    u.last_login_at,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', r.id,
                            'name', r.name,
                            'slug', r.slug
                        )
                    ) as roles
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                GROUP BY u.id, u.email, u.first_name, u.last_name, u.phone, u.is_active, u.email_verified, u.created_at, u.last_login_at
                WHERE u.deleted_at IS NULL
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?
            ";
            return Database::fetchAll($query, [$limit, $offset]);
        } catch (PDOException $e) {
            error_log('Get all users error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user count
     * 
     * @return int
     */
    public static function getUserCount()
    {
        try {
            $result = Database::fetch(
                "SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL"
            );
            return $result['count'] ?? 0;
        } catch (PDOException $e) {
            error_log('Get user count error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get a user with their roles and permissions
     * 
     * @param int $userId
     * @return array|false
     */
    public static function getUserWithPermissions($userId)
    {
        try {
            // Get user
            $user = Database::fetch("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
            if (!$user) {
                return false;
            }

            // Get roles
            $roles = Database::fetchAll(
                "
                SELECT r.id, r.name, r.slug, r.description
                FROM roles r
                INNER JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
                ",
                [$userId]
            );

            // Get all permissions for this user through their roles
            $permissions = Database::fetchAll(
                "
                SELECT DISTINCT p.id, p.name, p.slug, p.resource, p.action
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                INNER JOIN roles r ON rp.role_id = r.id
                INNER JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
                ORDER BY p.resource, p.action
                ",
                [$userId]
            );

            $user['roles'] = $roles ?: [];
            $user['permissions'] = $permissions ?: [];
            return $user;
        } catch (PDOException $e) {
            error_log('Get user with permissions error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign roles to a user
     * 
     * @param int $userId
     * @param array $roleIds
     * @return bool
     */
    public static function assignRolesToUser($userId, $roleIds)
    {
        try {
            // Verify user exists
            $user = Database::fetch("SELECT id FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                return false;
            }

            // Remove existing roles
            Database::execute("DELETE FROM user_roles WHERE user_id = ?", [$userId]);

            // Add new roles
            foreach ($roleIds as $roleId) {
                Database::execute(
                    "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                    [$userId, $roleId]
                );
            }

            return true;
        } catch (PDOException $e) {
            error_log('Assign roles to user error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user status (activate/deactivate)
     * 
     * @param int $userId
     * @param bool $isActive
     * @return array|false
     */
    public static function updateUserStatus($userId, $isActive)
    {
        try {
            $query = "UPDATE users SET is_active = ? WHERE id = ?";
            Database::execute($query, [$isActive ? 1 : 0, $userId]);

            return Database::fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        } catch (PDOException $e) {
            error_log('Update user status error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a user (soft delete)
     * 
     * @param int $userId
     * @return bool
     */
    public static function deleteUser($userId)
    {
        try {
            // Prevent deletion of the system super admin
            $user = Database::fetch("SELECT email FROM users WHERE id = ?", [$userId]);
            if ($user && $user['email'] === 'super.admin@ordinatrack.com') {
                error_log('Cannot delete super admin account');
                return false;
            }

            Database::execute(
                "UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$userId]
            );

            return true;
        } catch (PDOException $e) {
            error_log('Delete user error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a role has a specific permission
     * 
     * @param int $roleId
     * @param int $permissionId
     * @return bool
     */
    public static function roleHasPermission($roleId, $permissionId)
    {
        try {
            $result = Database::fetch(
                "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?",
                [$roleId, $permissionId]
            );
            return $result !== false;
        } catch (PDOException $e) {
            error_log('Check role permission error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get system statistics
     * 
     * @return array
     */
    public static function getSystemStats()
    {
        try {
            $stats = [
                'total_users' => 0,
                'active_users' => 0,
                'total_roles' => 0,
                'total_permissions' => 0,
                'total_organizations' => 0,
            ];

            $stats['total_users'] = Database::fetch(
                "SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL"
            )['count'] ?? 0;

            $stats['active_users'] = Database::fetch(
                "SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND deleted_at IS NULL"
            )['count'] ?? 0;

            $stats['total_roles'] = Database::fetch(
                "SELECT COUNT(*) as count FROM roles"
            )['count'] ?? 0;

            $stats['total_permissions'] = Database::fetch(
                "SELECT COUNT(*) as count FROM permissions"
            )['count'] ?? 0;

            $stats['total_organizations'] = Database::fetch(
                "SELECT COUNT(*) as count FROM organizations WHERE deleted_at IS NULL"
            )['count'] ?? 0;

            return $stats;
        } catch (PDOException $e) {
            error_log('Get system stats error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent activity logs
     * 
     * @param int $limit
     * @return array|false
     */
    public static function getRecentActivity($limit = 20)
    {
        try {
            $query = "
                SELECT 
                    al.id,
                    al.action,
                    al.resource_type,
                    al.created_at,
                    u.email,
                    u.first_name,
                    u.last_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT ?
            ";
            return Database::fetchAll($query, [$limit]);
        } catch (PDOException $e) {
            error_log('Get recent activity error: ' . $e->getMessage());
            return false;
        }
    }
}
